// 只读前端定向编译检查，不运行 publish.cjs，不覆盖打包目录。
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '..')
const compiler = require(path.join(root, 'admin/node_modules/@vue/compiler-sfc'))
const ts = require(path.join(root, 'admin/node_modules/typescript'))
const vue = require(path.join(root, 'admin/node_modules/vue'))
const files = [
    'niucloud/addon/phone_shop/admin/views/intake/list.vue',
    'niucloud/addon/phone_shop/admin/views/intake/components/IntakeMaterialFields.vue',
    'niucloud/addon/phone_shop/admin/views/intake/components/IntakeBuildDialog.vue',
    'niucloud/addon/hsx_erp/admin/views/erp/config/rules.vue',
    'niucloud/addon/hsx_erp/admin/views/erp/stock/list.vue',
    'site-uniapp/src/addon/hsx_erp/pages/stock/detail.vue'
]
for (const file of files) {
    const filename = path.join(root, file)
    const { descriptor, errors } = compiler.parse(fs.readFileSync(filename, 'utf8'), { filename })
    if (errors.length) throw errors[0]
    const script = compiler.compileScript(descriptor, { id: 'erp-mall-smoke' })
    const diagnostics = ts.transpileModule(script.content, {
        compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ESNext }, reportDiagnostics: true, fileName: filename + '.ts'
    }).diagnostics || []
    if (diagnostics.length) throw new Error(diagnostics.map(item => ts.flattenDiagnosticMessageText(item.messageText, '\n')).join('\n'))
    const template = compiler.compileTemplate({ source: descriptor.template.content, filename, id: 'erp-mall-smoke', compilerOptions: { bindingMetadata: script.bindings } })
    if (template.errors.length) throw template.errors[0]
    const src = path.join(root, file.startsWith('site-uniapp/') ? 'site-uniapp/src' : 'admin/src')
    for (const style of descriptor.styles) {
        const result = compiler.compileStyle({ source: style.content, filename, id: 'data-v-smoke', preprocessLang: style.lang,
            preprocessOptions: { importer: url => url.startsWith('@/') ? { file: path.join(src, url.slice(2)) } : null } })
        if (result.errors.length) throw result.errors[0]
    }
    console.log('PASS Vue script/template/style: ' + file)
    if (file === 'site-uniapp/src/addon/hsx_erp/pages/stock/detail.vue') {
        const scope = vue.effectScope(), env = { exports: {}, require(name) {
            if (name === 'vue') return vue
            if (name === '@dcloudio/uni-app') return { onLoad() {}, onShow() {} }
            if (name.endsWith('/hooks/useErpMallListingMedia')) return { erpMallListingInitialFields: () => ({}) }
            return {}
        } }
        vm.runInNewContext(ts.transpileModule(script.content, { fileName: filename + '.ts', compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS } }).outputText, env)
        const state = scope.run(() => env.exports.default.setup({}, { expose() {}, emit() {} }))
        assert.deepEqual([state.costExpanded.value, state.purchaseExpanded.value, state.inspectionExpanded.value, state.ledgerExpanded.value], [false, false, false, false], '移动详情扩展区默认收起')
        const items = [{ key: 'normal', severity: 'normal' }, { key: 'warn', severity: 'general' }, { key: 'bad', severity: 'abnormal' }, { key: 'unknown', severity: 'unknown' }, { key: 'normal2', severity: 'normal' }]
        state.asset.value = { id: 666, total_cost: 4500, inspection: { count: items.length, items } }
        assert.deepEqual(Array.from(state.inspectionPreview.value, item => item.key), ['bad', 'warn', 'unknown'], '首屏最多三条并优先异常和需关注项')
        assert.deepEqual(items.map(item => item.key), ['normal', 'warn', 'bad', 'unknown', 'normal2'], '预览排序不改动原质检报告顺序')
        state.asset.value.inspection.items = [{ key: 'a', severity: 'abnormal' }, { key: 'b', severity: 'abnormal' }, { key: 'c', severity: 'general' }, { key: 'd', severity: 'normal' }]
        assert.deepEqual(Array.from(state.inspectionPreview.value, item => item.key), ['a', 'b', 'c'], '相同严重级别保持原顺序')
        assert.equal(state.canViewCost.value, false, '折叠重构不扩大成本权限')
        state.stockCapabilities.value.view_cost = 1
        assert.equal(state.canViewCost.value, true, '有权限的员工仍可展开查看成本事实')
        assert.equal(state.costExpanded.value, false, '授予查看权限不强行展开全部成本')
        state.productMediaBusy.value = true
        assert.equal(state.productBusy.value, true, '视频处理中仍保护上架提交不抢跑')
        scope.stop()
        console.log('PASS mobile stock detail: collapsed cost/purchase/history, inspection severity-first 3, permissions and upload-busy guard; no API calls')
    }
}
const mirrors = [
    ['phone_shop', 'api/device_intake.ts'], ['phone_shop', 'views/intake/list.vue'],
    ['phone_shop', 'views/intake/components/IntakeMaterialFields.vue'],
    ['phone_shop', 'views/intake/components/material-options.ts'],
    ['phone_shop', 'views/intake/components/IntakeBuildDialog.vue'],
    ['hsx_erp', 'views/erp/config/rules.vue'], ['hsx_erp', 'views/erp/stock/list.vue']
]
for (const [addon, file] of mirrors) {
    if (!fs.readFileSync(path.join(root, `niucloud/addon/${addon}/admin/${file}`)).equals(fs.readFileSync(path.join(root, `admin/src/addon/${addon}/${file}`)))) {
        throw new Error('Plugin/source mismatch: ' + addon + '/' + file)
    }
}
console.log('PASS ' + mirrors.length + ' plugin/source mirrors. No publishing or database writes.')
