#!/usr/bin/env node
'use strict'

// 只修复生成文件中的已知重复注册，不删除组件目录，不读写数据库。
const fs = require('node:fs')
const path = require('node:path')
const IMPORT = /^[\t ]*import\s+(\w+)\s+from\s+['"]@\/addon\/([^/'"]+)\/components\/diy\/([^/'"]+)\/index\.vue['"];?[\t ]*\r?\n/gm
const BLOCK = /^[\t ]*<template v-if="component\.componentName == '([^']+)'">\r?\n[\t ]*<(diy-[a-z0-9-]+)\b[^\n]*\/>(?:\r?\n)[\t ]*<\/template>\r?\n/gm
const componentName = slug => slug.split('-').map(part => part[0].toUpperCase() + part.slice(1)).join('')
const readImports = source => [...source.matchAll(IMPORT)].map(match => ({text: match[0], name: match[1], addon: match[2], slug: match[3]}))

function repair(source, manifest, exists = () => true) {
    if (manifest?.version !== 1 || !manifest.replaced_directories || typeof manifest.replaced_directories !== 'object' || Array.isArray(manifest.replaced_directories)) {
        throw new Error('registration.json 格式错误，未修改文件')
    }
    for (const [old, replacement] of Object.entries(manifest.replaced_directories)) {
        if (!/^[a-z][a-z0-9-]*$/.test(old) || typeof replacement !== 'string' || !/^[a-z][a-z0-9-]*$/.test(replacement) || old === replacement) {
            throw new Error('registration.json 替代目录不正确，未修改文件')
        }
    }
    const imports = readImports(source)
    const obsolete = imports.filter(item => item.addon === 'phone_shop' && Object.prototype.hasOwnProperty.call(manifest.replaced_directories, item.slug))
    const removed = new Set(obsolete)
    const kept = imports.filter(item => !removed.has(item))
    const affected = new Map()
    for (const item of obsolete) {
        const replacement = manifest.replaced_directories[item.slug]
        const canonical = kept.find(row => row.addon === 'phone_shop' && row.slug === replacement && row.name === 'diy' + componentName(replacement))
        if (item.name !== 'diy' + componentName(item.slug) || !canonical || !exists(replacement)) {
            throw new Error(`更新不完整：请补齐 phone_shop/components/diy/${replacement}/index.vue 及对应注册后重试，未修改文件`)
        }
        const remaining = kept.filter(row => row.name === item.name)
        if (remaining.length > 1) throw new Error(`存在未知组件重名 ${item.name}，未修改文件`)
        // 原商城同名块保持一份；仅 phone_shop 曾有的旧名称移除旧注册。
        affected.set(componentName(item.slug), {keep: remaining.length, expected: imports.filter(row => row.name === item.name).length, seen: 0, tag: 'diy-' + item.slug})
    }
    let result = source
    for (const item of obsolete) result = result.replace(item.text, '')
    result = result.replace(BLOCK, (block, name, tag) => {
        const state = affected.get(name)
        if (!state) return block
        if (tag !== state.tag) throw new Error(`模板 ${name} 结构与预期不一致，未修改文件`)
        state.seen++
        return state.seen <= state.keep ? block : ''
    })
    for (const [name, state] of affected) {
        if (state.seen !== state.expected) throw new Error(`模板 ${name} 数量与导入不一致，未修改文件，请人工核对`)
    }
    const identifiers = new Set()
    for (const item of readImports(result)) {
        const key = item.name.toLowerCase()
        if (identifiers.has(key)) throw new Error(`仍有组件重名 ${item.name}，未修改文件`)
        identifiers.add(key)
    }
    const blocks = new Map()
    for (const match of result.matchAll(BLOCK)) {
        if (blocks.has(match[1])) throw new Error(`仍有重复模板 ${match[1]}，未修改文件`)
        blocks.set(match[1], match[2])
    }
    for (const item of readImports(result)) {
        if (blocks.get(componentName(item.slug)) !== 'diy-' + item.slug) {
            throw new Error(`组件 ${item.slug} 缺少唯一匹配模板，未修改文件`)
        }
    }
    return {source: result, removed: obsolete.map(item => item.slug)}
}

function main(args) {
    if (args.some(arg => !['--check', '--write'].includes(arg)) || (args.includes('--check') && args.includes('--write'))) {
        throw new Error('用法：node scripts/repair-phone-shop-diy.cjs [--check|--write]；默认仅检查')
    }
    const root = path.resolve(__dirname, '..')
    const file = path.join(root, 'src/addon/components/diy/group/index.vue')
    const components = path.join(root, 'src/addon/phone_shop/components/diy')
    if (fs.lstatSync(file).isSymbolicLink()) throw new Error('目标是软链接，请人工核对，未修改文件')
    const manifest = JSON.parse(fs.readFileSync(path.join(components, 'registration.json'), 'utf8'))
    const original = fs.readFileSync(file, 'utf8')
    const result = repair(original, manifest, slug => fs.existsSync(path.join(components, slug, 'index.vue')))
    if (result.source === original) {
        console.log('检查通过：没有重复注册，无需修改。')
        return
    }
    console.log(`检测到 ${result.removed.length} 个 phone_shop 旧目录注册：\n${result.removed.join('\n')}`)
    if (!args.includes('--write')) {
        console.log('仅检查，未修改。执行同一命令并加 --write 后备份并修复。')
        process.exitCode = 2
        return
    }
    const backupDir = path.join(root, '.diy-backups')
    fs.mkdirSync(backupDir, {recursive: true})
    const stamp = Date.now() + '-' + process.pid
    const backup = path.join(backupDir, 'group-index-' + stamp + '.vue')
    fs.writeFileSync(backup, original, {flag: 'wx', mode: 0o600})
    const temp = file + '.tmp-' + stamp
    try {
        fs.writeFileSync(temp, result.source, {flag: 'wx', mode: fs.statSync(file).mode & 0o777})
        fs.renameSync(temp, file)
    } finally {
        if (fs.existsSync(temp)) fs.unlinkSync(temp)
    }
    console.log(`修复完成，仅修改 ${file}\n备份：${backup}\n原 shop 及其他插件注册均保留。请重新打包。`)
}

module.exports = {repair}
if (require.main === module) {
    try { main(process.argv.slice(2)) } catch (error) {
        console.error(error.message)
        process.exitCode = 1
    }
}
