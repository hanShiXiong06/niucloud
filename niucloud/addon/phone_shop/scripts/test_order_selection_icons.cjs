#!/usr/bin/env node
// 图标名称/空心圆回归：不运行订单或资金接口。
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const app = path.resolve(__dirname, '../../../../site-uniapp')
const ts = require(path.join(app, 'node_modules/typescript'))
const compiler = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
const moduleObject = { exports: {} }
const code = ts.transpileModule(fs.readFileSync(path.join(app, 'node_modules/uview-plus/components/u-icon/icons.js'), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText
vm.runInNewContext(code, { module: moduleObject, exports: moduleObject.exports })
const icons = moduleObject.exports.default
let checks = 0
function check(condition, message) { assert.ok(condition, message); checks++ }
check(!icons['uicon-circle'], '真实库不存在纯circle图标')
check(Boolean(icons['uicon-checkmark-circle-fill']), '勾选图标确实存在')
for (const [name, expectedCircles] of [['OrderActionPanel.vue', 2], ['OfflineOrderProcessPopup.vue', 1]]) {
    const filename = path.join(app, 'src/addon/phone_shop/components', name)
    const source = fs.readFileSync(filename, 'utf8')
    const { descriptor, errors } = compiler.parse(source, { filename })
    check(!errors.length, name + ' SFC解析')
    const script = compiler.compileScript(descriptor, { id: name })
    const template = compiler.compileTemplate({ id: name, filename, source: descriptor.template.content, compilerOptions: { bindingMetadata: script.bindings } })
    check(!template.errors.length, name + ' 模板编译')
    for (const tag of descriptor.template.content.match(/<u-icon\b[^>]*>/g) || []) {
        check(!tag.includes(':name='), name + ' 图标不再依赖未验证的动态名称')
        const match = tag.match(/\bname="([^"]+)"/)
        if (match) check(Boolean(icons['uicon-' + match[1]]), name + ' 图标存在：' + match[1])
    }
    check((descriptor.template.content.match(/class="selection-circle"/g) || []).length === expectedCircles, name + ' 每个未选中分支都有空心圆')
    check(/\.selection-circle\s*\{[^}]*width:22px;[^}]*height:22px;[^}]*flex-shrink:0;[^}]*border:[^;]+;[^}]*border-radius:50%/.test(source), name + ' 空心圆可见且不被挤压')
}
console.log(`[通过] ${checks} 项订单图标/空心圆检查；无业务接口调用。`)
