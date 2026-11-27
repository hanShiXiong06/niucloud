#!/usr/bin/env node
'use strict'

// A failed build/publish must not remove the running administration frontend.
// Hashed assets are additive; index.html is the final, atomic commit point.
const fs = require('node:fs/promises')
const path = require('node:path')
const crypto = require('node:crypto')
const { constants } = require('node:fs')

const inside = (parent, child) => child === parent || child.startsWith(parent + path.sep)
const digest = bytes => crypto.createHash('sha256').update(bytes).digest('hex')
const missing = error => error && error.code === 'ENOENT'

async function statOrNull(file, io) {
    try { return await io.lstat(file) } catch (error) { if (missing(error)) return null; throw error }
}

async function noSymlinkAncestors(file, io, allowMissing = false) {
    const absolute = path.resolve(file)
    const root = path.parse(absolute).root
    let current = root
    for (const segment of absolute.slice(root.length).split(path.sep).filter(Boolean)) {
        current = path.join(current, segment)
        const stat = await statOrNull(current, io)
        if (!stat && allowMissing) continue
        if (!stat) throw new Error(`目录或文件不存在：${current}`)
        if (stat.isSymbolicLink()) throw new Error(`拒绝通过符号链接发布：${current}`)
    }
}

async function collectFiles(directory, io, prefix = '') {
    const result = []
    for (const entry of await io.readdir(directory, { withFileTypes: true })) {
        const relative = path.posix.join(prefix, entry.name)
        const absolute = path.join(directory, entry.name)
        if (entry.isSymbolicLink()) throw new Error(`构建产物包含符号链接：${relative}`)
        if (entry.isDirectory()) result.push(...await collectFiles(absolute, io, relative))
        else if (entry.isFile()) result.push(relative)
        else throw new Error(`构建产物包含非常规文件：${relative}`)
    }
    return result.sort()
}

// Only call on the private payload created by this invocation. Source modes
// and a service umask must not publish unreadable 0600/0700 static resources.
// Existing public directories and old assets are deliberately never chmod'ed.
async function readableOwnedPayload(directory, io) {
    await io.chmod(directory, 0o755)
    for (const entry of await io.readdir(directory, { withFileTypes: true })) {
        const file = path.join(directory, entry.name)
        if (entry.isSymbolicLink()) throw new Error(`临时发布目录包含符号链接：${file}`)
        if (entry.isDirectory()) await readableOwnedPayload(file, io)
        else if (entry.isFile()) await io.chmod(file, 0o644)
        else throw new Error(`临时发布目录包含非常规文件：${file}`)
    }
}

function normalizeHtml(html, files, base = '/admin') {
    if (!/^\/[a-zA-Z0-9_/-]+$/.test(base) || base.split('/').includes('..')) throw new Error('发布资源前缀不合法')
    base = base.replace(/\/+$/, '')
    let scriptCount = 0
    const resource = raw => {
        const value = raw.trim()
        if (!value || value.startsWith('#') || /^(?:https?:|\/\/|data:|blob:)/i.test(value)) return raw
        if (/^[a-z][a-z0-9+.-]*:/i.test(value)) throw new Error(`不支持的资源地址：${value}`)
        const index = value.search(/[?#]/)
        const pathname = index < 0 ? value : value.slice(0, index)
        const suffix = index < 0 ? '' : value.slice(index)
        let decoded
        try { decoded = decodeURIComponent(pathname) } catch { throw new Error(`资源地址编码不合法：${value}`) }
        if (decoded.includes('\\') || decoded.includes('\0') || decoded.split('/').includes('..')) throw new Error(`资源路径越界：${value}`)
        if (decoded.startsWith(base + '/')) decoded = decoded.slice(base.length + 1)
        else decoded = decoded.replace(/^\//, '').replace(/^\.\//, '')
        if (!files.has(decoded)) throw new Error(`构建产物引用的资源不存在：${decoded}`)
        return base + '/' + decoded.split('/').map(encodeURIComponent).join('/') + suffix
    }
    const rendered = html.replace(/<(script|link|img|source|video|audio)\b[^>]*>/gi, tag => {
        if (/^<script\b/i.test(tag) && /\ssrc\s*=/i.test(tag)) scriptCount++
        // Navigation links are not build resources. Link elements without these
        // rel values are still checked when they have an href (e.g. a favicon).
        if (/^<link\b/i.test(tag) && /\brel\s*=\s*['"](?:canonical|alternate)['"]/i.test(tag)) return tag
        return tag.replace(/(\s)(src|href|poster)\s*=\s*(?:(["'])(.*?)\3|([^\s>"']+))/gi, (_, space, name, quote, quoted, unquoted) => `${space}${name}=${quote || '"'}${resource(quoted === undefined ? unquoted : quoted)}${quote || '"'}`)
    })
    if (!/<html\b/i.test(html) || !scriptCount) throw new Error('index.html 不是完整的管理端构建入口（缺少 HTML 或脚本资源）')
    return rendered
}

async function publish(options = {}, io = fs) {
    const source = path.resolve(options.source || path.join(__dirname, '../dist'))
    const target = path.resolve(options.target || path.join(__dirname, '../../niucloud/public/admin'))
    const signal = options.signal
    const phase = options.onPhase || (() => {})
    const check = () => { if (signal && signal.aborted) { const error = new Error('发布已取消；旧后台入口保持不变'); error.code = 'ABORT_ERR'; throw error } }
    if (inside(source, target) || inside(target, source)) throw new Error('源目录与发布目录不能相同或互相包含')
    await noSymlinkAncestors(source, io)
    await noSymlinkAncestors(target, io, true)
    const sourceStat = await io.lstat(source)
    if (!sourceStat.isDirectory()) throw new Error('构建源路径必须是目录')
    const targetParent = path.dirname(target)
    if (!(await io.lstat(targetParent)).isDirectory()) throw new Error('发布目标的父目录必须已存在')
    const files = await collectFiles(source, io)
    if (!files.includes('index.html')) throw new Error('构建产物缺少 index.html，未执行发布')
    const renderedHtml = normalizeHtml(await io.readFile(path.join(source, 'index.html'), 'utf8'), new Set(files), options.base)
    check()
    let stage
    let committed = false
    const createdFiles = []
    const createdDirectories = []
    const temporaryFiles = new Set()
    const cleanupErrors = []
    try {
        stage = await io.mkdtemp(path.join(targetParent, '.admin-publish-'))
        const payload = path.join(stage, 'payload')
        await io.mkdir(payload)
        for (const relative of files) {
            check()
            const stagedFile = path.join(payload, relative)
            await io.mkdir(path.dirname(stagedFile), { recursive: true })
            if (relative === 'index.html') await io.writeFile(stagedFile, renderedHtml, { flag: 'wx', mode: 0o644 })
            else await io.copyFile(path.join(source, relative), stagedFile, constants.COPYFILE_EXCL)
        }
        await readableOwnedPayload(payload, io)
        // Validate what will actually be published, not just the source listing.
        normalizeHtml(await io.readFile(path.join(payload, 'index.html'), 'utf8'), new Set(await collectFiles(payload, io)), options.base)
        await phase('staged')
        check()
        const targetStat = await statOrNull(target, io)
        if (!targetStat) {
            await phase('before-commit')
            check()
            await noSymlinkAncestors(target, io, true)
            await io.rename(payload, target)
            committed = true
            return { published: true, target, addedFiles: files.length, previousAssetsPreserved: true }
        }
        if (!targetStat.isDirectory() || targetStat.isSymbolicLink()) throw new Error('发布目标必须是普通目录')
        const liveIndex = path.join(target, 'index.html')
        const liveIndexStat = await statOrNull(liveIndex, io)
        if (liveIndexStat && (!liveIndexStat.isFile() || liveIndexStat.isSymbolicLink())) throw new Error('现有 index.html 不是普通文件')
        const additions = []
        // Refuse non-hashed name collisions rather than change files still used
        // by the current page before committing its new index. No old file is deleted.
        for (const relative of files.filter(file => file !== 'index.html')) {
            const destination = path.join(target, relative)
            await noSymlinkAncestors(destination, io, true)
            const existing = await statOrNull(destination, io)
            if (existing) {
                if (!existing.isFile() || existing.isSymbolicLink()) throw new Error(`发布路径不是普通文件：${relative}`)
                if (digest(await io.readFile(destination)) !== digest(await io.readFile(path.join(payload, relative)))) throw new Error(`同名资源内容已变化，未覆盖旧文件：${relative}；请使用带内容哈希的新文件名`)
            } else additions.push(relative)
        }
        for (const relative of additions) {
            check()
            const destination = path.join(target, relative)
            let directory = target
            for (const segment of relative.split('/').slice(0, -1)) {
                directory = path.join(directory, segment)
                const stat = await statOrNull(directory, io)
                if (!stat) { await io.mkdir(directory); createdDirectories.push(directory); await io.chmod(directory, 0o755) }
                else if (!stat.isDirectory() || stat.isSymbolicLink()) throw new Error(`资源目录不是普通目录：${directory}`)
            }
            const temp = path.join(path.dirname(destination), '.' + path.basename(destination) + '.publish-' + crypto.randomBytes(10).toString('hex'))
            // Reserve exclusively before recording ownership; a rare EEXIST
            // must never make cleanup unlink somebody else's file.
            const handle = await io.open(temp, 'wx', 0o644)
            temporaryFiles.add(temp)
            await handle.close()
            await io.chmod(temp, 0o644)
            await io.copyFile(path.join(payload, relative), temp)
            check()
            // A hard link is atomic and fails on EEXIST, unlike rename which may
            // overwrite a concurrent writer's file. The temp and live file share a device.
            const stat = await io.lstat(temp)
            const hash = digest(await io.readFile(temp))
            await io.link(temp, destination)
            createdFiles.push({ path: destination, ino: stat.ino, dev: stat.dev, hash })
            await io.unlink(temp)
            temporaryFiles.delete(temp)
        }
        const indexTemp = path.join(target, '.index.publish-' + crypto.randomBytes(10).toString('hex'))
        const indexHandle = await io.open(indexTemp, 'wx', 0o644)
        temporaryFiles.add(indexTemp)
        await indexHandle.close()
        await io.chmod(indexTemp, 0o644)
        await io.copyFile(path.join(payload, 'index.html'), indexTemp)
        await phase('before-commit')
        check()
        await noSymlinkAncestors(liveIndex, io, true)
        await io.rename(indexTemp, liveIndex)
        committed = true
        temporaryFiles.delete(indexTemp)
        return { published: true, target, addedFiles: additions.length, previousAssetsPreserved: true }
    } finally {
        for (const temp of temporaryFiles) {
            try { await io.unlink(temp) } catch (error) { if (!missing(error)) cleanupErrors.push(error.message) }
        }
        if (!committed) {
            for (const created of createdFiles.reverse()) {
                try {
                    const stat = await statOrNull(created.path, io)
                    if (stat && stat.ino === created.ino && stat.dev === created.dev && digest(await io.readFile(created.path)) === created.hash) await io.unlink(created.path)
                } catch (error) { if (!missing(error)) cleanupErrors.push(error.message) }
            }
            for (const directory of createdDirectories.reverse()) {
                try { await io.rmdir(directory) } catch (error) { if (!missing(error) && error.code !== 'ENOTEMPTY') cleanupErrors.push(error.message) }
            }
        }
        if (stage) {
            try { await io.rm(stage, { recursive: true, force: true }) } catch (error) { cleanupErrors.push(error.message) }
        }
        if (cleanupErrors.length) console.error('[警告] 本次发布临时文件未能全部清理：' + cleanupErrors.join('; '))
    }
}

function parseArgs(argv) {
    const options = {}
    for (let i = 0; i < argv.length; i++) {
        const key = argv[i]
        if (!['--source', '--target', '--base'].includes(key) || !argv[i + 1] || argv[i + 1].startsWith('--')) throw new Error('Usage: node scripts/publish-safe.cjs --source <build-directory> --target <public-directory> [--base /admin]')
        options[key.slice(2)] = argv[++i]
    }
    return options
}

if (require.main === module) {
    const controller = new AbortController()
    const cancel = () => controller.abort()
    process.once('SIGINT', cancel)
    process.once('SIGTERM', cancel)
    Promise.resolve().then(() => publish({ ...parseArgs(process.argv.slice(2)), signal: controller.signal }))
        .then(result => console.log(JSON.stringify(result)))
        .catch(error => { console.error('[发布未完成] ' + error.message); process.exitCode = error.code === 'ABORT_ERR' ? 130 : 1 })
        .finally(() => { process.removeListener('SIGINT', cancel); process.removeListener('SIGTERM', cancel) })
}

module.exports = { publish, publishSafe: publish, normalizeHtml, parseArgs }
