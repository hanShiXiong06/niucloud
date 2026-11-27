'use strict'
// Offline compile/helper/mirror regression. No server, database, orders or printing.
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), source = path.join(__dirname, '../admin'), mirror = path.join(root, 'admin/src/addon/hsx_express')
const ts = require(path.join(root, 'admin/node_modules/typescript'))
const sfc = require(path.join(root, 'admin/node_modules/@vue/compiler-sfc'))
const sass = require(path.join(root, 'admin/node_modules/sass'))
let passed = 0
const check = fn => { fn(); passed++ }
const transpile = text => ts.transpileModule(text, { reportDiagnostics: true, compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } })
const helpers = transpile(fs.readFileSync(path.join(source, 'utils/presentation.ts'), 'utf8'))
check(() => assert.equal(helpers.diagnostics.length, 0))
const context = { exports: {}, URL }
vm.runInNewContext(helpers.outputText, context)
const ui = context.exports
const defaults = ui.configDefaults()
check(() => assert.equal(defaults.carrier, '', 'do not require SF before selecting own carrier'))
check(() => assert.equal(defaults.exp_type, ''))
check(() => assert.equal(defaults.enabled, 0))
const draft = ui.configForEditing({ carrier: 'zhongtong', enabled: '1', use_ack: '1', partner_id: 'zto', net: 'station', key: '******', secret: '******', partner_key: '******', partner_secret: '******', unexpected: 'discard' })
for (const field of ui.secretFields) check(() => assert.equal(draft[field], '', 'mask not resubmitted: ' + field))
check(() => assert.equal(draft.net, 'station'))
check(() => assert.equal(draft.enabled, 1))
check(() => assert.equal(draft.use_ack, 1))
check(() => assert.equal(draft.unexpected, undefined))
const switching = { ...defaults, carrier: 'zhongtong', key: 'shared-key', secret: 'shared-secret', device_id: 'same-printer', callback_base_url: 'https://example.test', partner_id: 'old', partner_key: 'old-key', partner_secret: 'old-secret', partner_name: 'old-name', net: 'old-net', code: 'sf_secret', check_man: 'old', template_id: 'old-template', exp_type: '顺丰标快', enabled: 1, use_ack: 1 }
ui.applyCarrierSelection(switching, { products: [{ value: '标准快递' }, { value: '中通标快' }] })
for (const field of ['partner_id', 'partner_key', 'partner_secret', 'partner_name', 'net', 'code', 'check_man', 'template_id', 'exp_type']) check(() => assert.equal(switching[field], '', 'clear courier-specific field: ' + field))
for (const field of ['enabled', 'use_ack']) check(() => assert.equal(switching[field], 0))
for (const [field, expected] of Object.entries({ key: 'shared-key', secret: 'shared-secret', device_id: 'same-printer', callback_base_url: 'https://example.test' })) check(() => assert.equal(switching[field], expected))
ui.applyCarrierSelection(switching, { products: [{ value: '标准快递' }] })
check(() => assert.equal(switching.exp_type, '标准快递', 'single documented product can be preselected, not enabled'))
check(() => assert.equal(switching.enabled, 0))
for (const state of ['cancelled', 'cancel_unknown', 'cancelling', 'unknown', 'failed']) {
    check(() => assert.equal(ui.canReprint({ state, can_reprint: true }), false))
    check(() => assert.equal(ui.canCancel({ state, can_cancel: true }), false))
}
check(() => assert.equal(ui.canRecover({ state: 'unknown', can_recover: true }), true))
check(() => assert.equal(ui.canRecover({ state: 'cancel_unknown', can_recover: true }), false))
check(() => assert.equal(ui.canCancel({ state: 'ready', can_cancel: false }), false, 'unsupported carrier cannot cancel even if ready'))
check(() => assert.equal(ui.canCancel({ state: 'ready', can_cancel: true }), true))
for (const value of ['https://api.kuaidi100.com/label/test', 'http://ckd.im/test']) check(() => assert.equal(ui.labelLinks(value).length, 1))
for (const value of ['https://kuaidi100.com.evil.test/x', 'https://evil-kuaidi100.com/x', 'javascript:alert(1)', 'https://user:pass@api.kuaidi100.com/x', '/relative']) check(() => assert.equal(ui.labelLinks(value).length, 0))
check(() => assert.equal(ui.requestError({ msg: '明确原因' }, '兜底'), '明确原因'))
check(() => assert.equal(ui.requestError(null, '兜底'), '兜底'))
for (const relative of ['views/config/index.vue', 'views/tasks/index.vue', 'components/saved-credential-input.vue', 'components/sf-config-panel.vue', 'utils/presentation.ts', 'api/index.ts']) {
    const filename = path.join(source, relative), text = fs.readFileSync(filename, 'utf8')
    check(() => assert.equal(fs.readFileSync(path.join(mirror, relative), 'utf8'), text, 'runtime mirror ' + relative))
    if (relative.endsWith('.vue')) {
        const { descriptor, errors } = sfc.parse(text, { filename })
        check(() => assert.equal(errors.length, 0))
        check(() => assert.ok(sfc.compileScript(descriptor, { id: 'qa', inlineTemplate: true }).content))
        for (const style of descriptor.styles) check(() => {
            const css = style.lang === 'scss' ? sass.compileString(style.content).css : style.content
            assert.equal(sfc.compileStyle({ source: css, filename, id: 'data-v-qa', scoped: style.scoped }).errors.length, 0)
        })
    } else check(() => assert.equal(transpile(text).diagnostics.length, 0))
}
console.log(`PASS: ${passed} admin UI checks; carrier selection, credential isolation, operation guards, SFC/Sass compilation and source/runtime mirrors. No external IO.`)
