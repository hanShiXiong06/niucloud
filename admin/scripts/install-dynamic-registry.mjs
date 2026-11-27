import fs from 'node:fs'
import path from 'node:path'
import crypto from 'node:crypto'
import { createRequire } from 'node:module'
import { fileURLToPath, pathToFileURL } from 'node:url'

const require = createRequire(import.meta.url)
const { parse } = require('@vue/compiler-sfc')
const ts = require('typescript')
const DEFAULT_ADMIN = fs.realpathSync(path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..'))
const GLOB = '@/**/*.vue'
const MODULE = '@/utils/dynamic-components'
const EXPORT = 'dynamicComponentModules'
const EXCLUDED = new Set(['node_modules', 'vendor', 'dist', '.git', '.build-safe'])

function isGlob(call) {
    return ts.isCallExpression(call) && ts.isPropertyAccessExpression(call.expression)
        && call.expression.name.text === 'glob' && ts.isMetaProperty(call.expression.expression)
        && call.expression.expression.keywordToken === ts.SyntaxKind.ImportKeyword
        && call.expression.expression.name.text === 'meta'
}
function isTarget(call) {
    return isGlob(call) && call.arguments.length > 0 && ts.isStringLiteral(call.arguments[0]) && call.arguments[0].text === GLOB
}
function syntax(source, filename) {
    return ts.createSourceFile(filename, source, ts.ScriptTarget.Latest, true, /\.tsx$/.test(filename) ? ts.ScriptKind.TSX : ts.ScriptKind.TS)
}
function hasComment(source) {
    const scanner = ts.createScanner(ts.ScriptTarget.Latest, false, ts.LanguageVariant.Standard, source)
    for (let token = scanner.scan(); token !== ts.SyntaxKind.EndOfFileToken; token = scanner.scan()) {
        if (token === ts.SyntaxKind.SingleLineCommentTrivia || token === ts.SyntaxKind.MultiLineCommentTrivia) return true
    }
    return false
}

export function rewriteDynamicRegistry(source, filename = 'component.vue') {
    const parsed = parse(source, { filename })
    const edits = [], manual = []
    if (parsed.errors.length) return { source, changed: false, replacements: 0, manual: [{ line: 1, reason: 'Vue 单文件组件解析失败，未修改：' + String(parsed.errors[0].message || parsed.errors[0]) }] }
    for (const block of [parsed.descriptor.script, parsed.descriptor.scriptSetup].filter(Boolean)) {
        if (block.src) continue
        const ast = syntax(block.content, block.lang === 'tsx' ? filename + '.tsx' : filename + '.ts')
        if (ast.parseDiagnostics.length) {
            manual.push({ line: block.loc.start.line, reason: 'script 语法解析失败，未修改此代码块' })
            continue
        }
        const accepted = new Set()
        for (const statement of ast.statements) {
            if (!ts.isVariableStatement(statement) || statement.modifiers?.length
                || !(statement.declarationList.flags & ts.NodeFlags.Const) || statement.declarationList.declarations.length !== 1) continue
            const declaration = statement.declarationList.declarations[0]
            if (!ts.isIdentifier(declaration.name) || !declaration.initializer || !isTarget(declaration.initializer)
                || declaration.initializer.arguments.length !== 1 || declaration.initializer.typeArguments?.length
                || hasComment(block.content.slice(statement.getStart(ast), statement.end))) continue
            accepted.add(declaration.initializer)
            edits.push({ start: block.loc.start.offset + statement.getStart(ast), end: block.loc.start.offset + statement.end,
                text: `import { ${EXPORT} as ${declaration.name.text} } from '${MODULE}'` })
        }
        const inspect = node => {
            if (isTarget(node) && !accepted.has(node)) {
                const position = ast.getLineAndCharacterOfPosition(node.getStart(ast))
                manual.push({ line: block.loc.start.line + position.line, reason: '不是顶层单独 const 或带有额外参数／类型包装；请手工核对，未强行替换' })
            }
            ts.forEachChild(node, inspect)
        }
        inspect(ast)
    }
    let result = source
    for (const edit of edits.sort((a, b) => b.start - a.start)) result = result.slice(0, edit.start) + edit.text + result.slice(edit.end)
    return { source: result, changed: edits.length > 0, replacements: edits.length, manual }
}

function within(parent, child) { return child === parent || child.startsWith(parent + path.sep) }
function exists(file) { try { return fs.lstatSync(file) } catch (error) { if (error.code === 'ENOENT') return null; throw error } }
function safePath(boundary, file, allowMissing = false) {
    if (!within(boundary, file)) throw Error('路径超出当前项目，未修改：' + file)
    let current = boundary
    for (const segment of ['', ...path.relative(boundary, file).split(path.sep).filter(Boolean)]) {
        if (segment) current = path.join(current, segment)
        const stat = exists(current)
        if (!stat) { if (allowMissing) continue; throw Error('文件不存在：' + current) }
        if (stat.isSymbolicLink()) throw Error('拒绝通过符号链接读写：' + current)
        if (current !== file && !stat.isDirectory()) throw Error('中间路径不是目录：' + current)
    }
}
function snapshot(file, boundary) {
    safePath(boundary, file)
    const stat = fs.lstatSync(file)
    if (!stat.isFile()) throw Error('不是普通文件：' + file)
    const bytes = fs.readFileSync(file), text = bytes.toString('utf8')
    if (!Buffer.from(text).equals(bytes)) throw Error('不是完整 UTF-8 源文件，未修改：' + file)
    return { file, boundary, bytes, text, stat }
}
function unchanged(record, expected = record.bytes, metadata = record.stat) {
    safePath(record.boundary, record.file)
    const stat = fs.lstatSync(record.file)
    if (!stat.isFile() || stat.dev !== metadata.dev || stat.ino !== metadata.ino || stat.mode !== metadata.mode
        || stat.size !== metadata.size || stat.mtimeMs !== metadata.mtimeMs || !fs.readFileSync(record.file).equals(expected)) throw Error('检测到并发修改，未覆盖：' + record.file)
}
function ensureDirectory(boundary, dir) {
    safePath(boundary, dir, true)
    let current = boundary
    for (const segment of path.relative(boundary, dir).split(path.sep).filter(Boolean)) {
        current = path.join(current, segment)
        if (!exists(current)) fs.mkdirSync(current, { mode: 0o700 })
        else if (!fs.lstatSync(current).isDirectory()) throw Error('备份目录不是普通目录：' + current)
    }
}
function atomicReplace(record, bytes, expected = record.bytes, metadata = record.stat) {
    unchanged(record, expected, metadata)
    const temporary = path.join(path.dirname(record.file), `.${path.basename(record.file)}.registry-${crypto.randomUUID()}.tmp`)
    let fd
    try {
        fd = fs.openSync(temporary, 'wx', 0o600)
        fs.writeFileSync(fd, bytes)
        fs.fchmodSync(fd, metadata.mode & 0o777)
        fs.fsyncSync(fd)
        fs.closeSync(fd); fd = undefined
        unchanged(record, expected, metadata)
        fs.renameSync(temporary, record.file)
        return fs.lstatSync(record.file)
    } finally {
        if (fd !== undefined) fs.closeSync(fd)
        if (exists(temporary)) fs.unlinkSync(temporary)
    }
}
function registrySnapshot(admin) {
    const file = path.join(admin, 'src/utils/dynamic-components.ts'), record = snapshot(file, admin)
    const ast = syntax(record.text, file)
    const valid = !ast.parseDiagnostics.length && ast.statements.some(statement => ts.isVariableStatement(statement)
        && statement.modifiers?.some(modifier => modifier.kind === ts.SyntaxKind.ExportKeyword)
        && statement.declarationList.declarations.some(declaration => ts.isIdentifier(declaration.name) && declaration.name.text === EXPORT
            && declaration.initializer && isTarget(declaration.initializer) && declaration.initializer.arguments.length === 1))
    if (!valid) throw Error(`请先上传 src/utils/dynamic-components.ts，且导出 ${EXPORT}；未修改页面`)
    return record
}
function rejectBuild(lock) {
    if (exists(lock) || exists(lock + '.recover')) throw Error('存在活动或尚未核实的 build.lock，请先停止／核实构建；不会删除原锁')
}
function plan(admin) {
    const boundary = path.dirname(admin), registry = registrySnapshot(admin), candidates = new Set(), skipped = []
    const walk = dir => {
        safePath(admin, dir)
        for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
            const file = path.join(dir, entry.name)
            if (entry.isSymbolicLink()) { skipped.push({ file: path.relative(boundary, file), reason: '符号链接，未跟随' }); continue }
            if (entry.isDirectory() && !EXCLUDED.has(entry.name)) walk(file)
            else if (entry.isFile() && entry.name.endsWith('.vue')) {
                candidates.add(file)
                const parts = path.relative(path.join(admin, 'src/addon'), file).split(path.sep)
                if (within(path.join(admin, 'src/addon'), file) && parts.length > 1) {
                    const canonical = path.join(boundary, 'niucloud/addon', parts[0], 'admin', ...parts.slice(1))
                    if (exists(canonical)) {
                        try { safePath(boundary, canonical); if (fs.lstatSync(canonical).isFile()) candidates.add(canonical) }
                        catch (error) { skipped.push({ file: path.relative(boundary, canonical), reason: error.message }) }
                    }
                }
            }
        }
    }
    walk(path.join(admin, 'src'))
    const changes = [], manual = []
    for (const file of [...candidates].sort()) {
        const record = snapshot(file, boundary), result = rewriteDynamicRegistry(record.text, file)
        manual.push(...result.manual.map(item => ({ file: path.relative(boundary, file), ...item })))
        if (result.changed) changes.push({ ...record, after: Buffer.from(result.source), replacements: result.replacements })
    }
    return { admin, boundary, registry, changes, report: { mode: 'check', scanned: candidates.size, changedFiles: changes.map(record => ({ file: path.relative(boundary, record.file), replacements: record.replacements })), manual, skipped, backup: null, applied: false } }
}

// adminDirectory and onPlanned are programmatic test seams, never CLI options.
export function installDynamicRegistry({ apply = false, adminDirectory = DEFAULT_ADMIN, onPlanned } = {}) {
    const admin = fs.realpathSync(adminDirectory), state = path.join(admin, '.build-safe'), lock = path.join(state, 'build.lock')
    safePath(admin, state, true); rejectBuild(lock)
    const planned = plan(admin)
    onPlanned?.(planned.report)
    if (!apply || !planned.changes.length) return { ...planned.report, mode: apply ? 'apply' : 'check' }
    unchanged(planned.registry)
    ensureDirectory(admin, state)
    rejectBuild(lock)
    const token = crypto.randomUUID()
    // Share the build lock for the whole edit transaction. A concurrent build
    // sees a live coordinator and refuses to compile half-migrated source.
    fs.mkdirSync(lock, { mode: 0o700 })
    fs.writeFileSync(path.join(lock, 'owner.json'), JSON.stringify({ pid: process.pid, token, unit: null, purpose: 'install-dynamic-registry' }), { flag: 'wx', mode: 0o600 })
    let backup, committed = false
    const written = [], rollbackErrors = []
    try {
        unchanged(planned.registry)
        for (const record of planned.changes) unchanged(record)
        const backups = path.join(state, 'setup-backups')
        ensureDirectory(admin, backups)
        backup = fs.mkdtempSync(path.join(backups, new Date().toISOString().replace(/[:.]/g, '-') + '-'))
        for (const record of planned.changes) {
            unchanged(record)
            const target = path.join(backup, 'originals', path.relative(planned.boundary, record.file))
            ensureDirectory(backup, path.dirname(target))
            fs.writeFileSync(target, record.bytes, { flag: 'wx', mode: 0o600 })
        }
        fs.writeFileSync(path.join(backup, 'manifest.json'), JSON.stringify({ created: new Date().toISOString(), admin, files: planned.changes.map(record => ({ file: path.relative(planned.boundary, record.file), mode: record.stat.mode & 0o777, beforeSha256: crypto.createHash('sha256').update(record.bytes).digest('hex'), afterSha256: crypto.createHash('sha256').update(record.after).digest('hex') })) }, null, 2) + '\n', { flag: 'wx', mode: 0o600 })
        for (const record of planned.changes) {
            unchanged(planned.registry)
            const afterStat = atomicReplace(record, record.after)
            written.push({ record, afterStat })
        }
        committed = true
        return { ...planned.report, mode: 'apply', backup, applied: true }
    } catch (error) {
        for (const { record, afterStat } of written.reverse()) {
            try { atomicReplace(record, record.bytes, record.after, afterStat) } catch (rollback) { rollbackErrors.push(rollback.message) }
        }
        throw Error(`${error.message}${backup ? `；原文件备份：${backup}` : ''}${rollbackErrors.length ? '；部分文件已有并发修改，未强行回滚：' + rollbackErrors.join('；') : ''}`)
    } finally {
        // Never clear a lock somebody else replaced.
        try { const owner = JSON.parse(fs.readFileSync(path.join(lock, 'owner.json'), 'utf8')); if (owner.token === token) fs.rmSync(lock, { recursive: true }) }
        catch (error) { console.error('[保护] 安装锁未自动清理：' + error.message) }
        if (!committed && backup) console.error('[未应用完成] 已保留本次源文件备份：' + backup)
    }
}

export function main(argv = process.argv.slice(2)) {
    if (argv.some(arg => !['--check', '--apply', '--help'].includes(arg)) || argv.includes('--check') && argv.includes('--apply')) throw Error('用法：node scripts/install-dynamic-registry.mjs [--check | --apply]；不接受外部扫描路径')
    if (argv.includes('--help')) { console.log('默认 --check 只读查看 AST 最小替换计划；--apply 先备份、再修改本站源码。不会构建、发布、改数据库或整页覆盖。'); return }
    const result = installDynamicRegistry({ apply: argv.includes('--apply') })
    console.log(JSON.stringify(result, null, 2))
    if (result.manual.length) console.log('[待手工核对] 已在 manual 列出不支持的声明形状；未强改。')
}
if (process.argv[1] && pathToFileURL(path.resolve(process.argv[1])).href === import.meta.url) {
    try { main() } catch (error) { console.error('[未完成] ' + error.message); process.exitCode = 1 }
}
