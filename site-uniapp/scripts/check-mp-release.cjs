#!/usr/bin/env node
// Local-only preflight. Raw directory sizes are not WeChat's post-transpile upload sizes.
const fs = require('node:fs')
const path = require('node:path')
const assert = require('node:assert/strict')
const postcss = require('postcss')
const selectorParser = require('postcss-selector-parser')

const root = path.resolve(process.argv[2] || path.join(__dirname, '../dist/build/mp-weixin'))
const hardLimit = 2 * 1024 * 1024
const qualityReference = 1.5 * 1024 * 1024
const app = JSON.parse(fs.readFileSync(path.join(root, 'app.json'), 'utf8'))
const project = JSON.parse(fs.readFileSync(path.join(root, 'project.config.json'), 'utf8'))
assert.equal(project.setting?.minified, true, '必须开启上传脚本压缩，避免二次转译后膨胀')
assert.equal(project.setting?.minifyWXSS, true, '必须开启上传样式压缩')
assert.equal(project.setting?.minifyWXML, true, '必须开启上传模板压缩')

const subpackages = (app.subPackages || app.subpackages || []).map(item => String(item.root).replace(/\/$/, ''))
assert.ok(subpackages.every(item => item && !item.startsWith('/') && !item.split('/').includes('..')), '分包路径无效')
const totals = new Map([['__APP__', 0], ...subpackages.map(item => [item, 0])])
const universalSelectors = []
let cssCount = 0
let fileCount = 0
function walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const file = path.join(dir, entry.name)
        if (entry.isDirectory()) { walk(file); continue }
        if (!entry.isFile()) continue
        const relative = path.relative(root, file).split(path.sep).join('/')
        const scope = subpackages.find(item => relative.startsWith(item + '/')) || '__APP__'
        totals.set(scope, totals.get(scope) + fs.statSync(file).size)
        fileCount++
        if (!relative.endsWith('.wxss')) continue
        cssCount++
        postcss.parse(fs.readFileSync(file, 'utf8'), { from: file }).walkRules(rule => {
            selectorParser(ast => ast.walkUniversals(() => {
                universalSelectors.push({ file: relative, selector: rule.selector })
            })).processSync(rule.selector)
        })
    }
}
walk(root)
console.log('本地原始构建目录检查：' + root)
for (const [scope, bytes] of totals) {
    console.log(`${scope}: ${bytes} B (${(bytes / 1024).toFixed(1)} KiB)`)
    assert.ok(bytes <= hardLimit, `${scope} 本地原始体积超过 2 MiB；停止发布并进一步检查`)
}
assert.deepEqual(universalSelectors, [], 'WXSS存在微信不支持的通配选择器：' + JSON.stringify(universalSelectors))
console.log(`PASS ${fileCount} files / ${cssCount} WXSS; upload compression enabled; no universal selectors`)
if (totals.get('__APP__') > qualityReference) console.log('提示：原始主包仍大于1.5 MiB质量参考值，请以开发者工具最新扫描为准。')
console.log('边界：不上传、不预览、不提交审核；仍需微信工具验证二次编译后的实际包体与全部上传检查。')
