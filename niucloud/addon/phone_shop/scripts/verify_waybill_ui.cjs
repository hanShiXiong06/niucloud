// Offline UI contract checks: no browser navigation, network, orders, or printing.
const fs = require('fs')
const path = require('path')
const vm = require('vm')
const assert = require('assert/strict')
const root = path.resolve(__dirname, '../../../..')
const ts = require(path.join(root, 'admin/node_modules/typescript'))
const sfc = require(path.join(root, 'admin/node_modules/@vue/compiler-sfc'))
const helperPath = path.join(__dirname, '../admin/utils/electronic-sheet-provider.ts')
const helperSource = fs.readFileSync(helperPath, 'utf8')
const compiled = ts.transpileModule(helperSource, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const sandbox = { exports: {}, URL }
vm.runInNewContext(compiled, sandbox)
const { trustedWaybillLabels, WAYBILL_CREATE_CONFIRM, WAYBILL_REPRINT_CONFIRM } = sandbox.exports
const { electronicSheetProviderState } = sandbox.exports
const { defaultDeliveryWay } = sandbox.exports
let passed = 0
const check = (fn) => { fn(); passed++ }
for (const url of ['http://api.kuaidi100.com/label/1', 'http://ckd.im/abc', 'https://kuaidi100.com/label/1', 'https://x.ckd.im/a', 'https://API.KUAIDI100.COM/label/1']) {
    check(() => assert.equal(trustedWaybillLabels(url).length, 1, url))
}
for (const url of ['https://example.com/label', 'https://evil-kuaidi100.com/a', 'https://kuaidi100.com.evil.com/a', 'https://fakeckd.im/a', 'https://ckd.im.evil.com/a', 'https://user:pass@api.kuaidi100.com/a', 'https://user@ckd.im/a', 'javascript:alert(1)', 'data:text/html,a', '/relative', 'ftp://ckd.im/a', 'https://ckd.im/' + 'a'.repeat(2050)]) {
    check(() => assert.equal(trustedWaybillLabels(url).length, 0, url))
}
check(() => assert.equal(trustedWaybillLabels(['http://ckd.im/1', 'https://example.com/1', 42, 'http://ckd.im/1']).length, 1))
check(() => assert.equal(trustedWaybillLabels('http://ckd.im/1, https://api.kuaidi100.com/label/2').length, 2))
check(() => assert.match(WAYBILL_CREATE_CONFIRM, /计费.*实际交给快递员后仍需确认发货/))
check(() => assert.match(WAYBILL_REPRINT_CONFIRM, /先核实.*再次出纸.*不会重新申请运单/))
const original = { key: 'kdbird', label: '快递鸟（原有方式）', external: false }
const extended = { key: 'test_external', label: '扩展服务', external: true }
check(() => assert.equal(electronicSheetProviderState({ interface_type: 'kdbird' }).registryAvailable, false, 'old API cannot appear integrated'))
check(() => assert.equal(electronicSheetProviderState({ providers: null }).registryAvailable, false))
check(() => assert.equal(electronicSheetProviderState({ providers: [] }).registryAvailable, false))
check(() => assert.equal(electronicSheetProviderState({ providers: [original] }).registryAvailable, true))
check(() => assert.equal(electronicSheetProviderState({ providers: [original] }).hasExternal, false, 'authorization/registration differs from old backend'))
check(() => assert.equal(electronicSheetProviderState({ providers: [original, extended] }).hasExternal, true))
check(() => assert.equal(electronicSheetProviderState({ providers: [null, original, extended] }).providers.length, 2))
check(() => assert.equal(defaultDeliveryWay({ default_for_delivery: true }, 'add'), 'plugin_waybill'))
check(() => assert.equal(defaultDeliveryWay({ default_for_delivery: true }, 'edit'), 'manual_write', 'editing shipment never allocates waybill'))
check(() => assert.equal(defaultDeliveryWay({ default_for_delivery: false }, 'add'), 'manual_write', 'disabled SF is not default'))
check(() => assert.equal(defaultDeliveryWay(null, 'add'), 'manual_write', 'configured KDBird/manual remains available'))
const component = path.join(__dirname, '../admin/views/order/components/provider-waybill-panel.vue')
const source = fs.readFileSync(component, 'utf8')
check(() => assert.ok(source.includes('@click="createTask"') && source.includes('@click="reprintTask"')))
check(() => assert.ok(source.includes("await operate(operation, '', operation === 'reprint' ? 1 : 0)")))
check(() => assert.ok(source.includes("reason = '', confirm = 0") && source.includes('{ operation, reason, confirm,')))
check(() => assert.match(source, /仅选择上方公司不能代替编码关联/))
check(() => assert.match(source, /freightPayment = ref\('receiver'\)/))
check(() => assert.match(source, /freight_payment: freightPayment.value/))
check(() => assert.match(source, /sender_monthly_card_ready/))
check(() => assert.match(source, /hasActiveTask \|\| failedToLoad \|\| !!paymentIssue/))
check(() => assert.match(source, /originalPayment !== freightPayment.value/))
const { descriptor, errors } = sfc.parse(source, { filename: component })
check(() => assert.equal(errors.length, 0))
check(() => assert.ok(sfc.compileScript(descriptor, { id: 'waybill-ui-test', inlineTemplate: true }).content))
const configComponent = path.join(__dirname, '../admin/views/delivery/electronic_sheet_config.vue')
const configSource = fs.readFileSync(configComponent, 'utf8')
const configSfc = sfc.parse(configSource, { filename: configComponent })
check(() => assert.equal(configSfc.errors.length, 0))
check(() => assert.ok(sfc.compileScript(configSfc.descriptor, { id: 'waybill-config-test', inlineTemplate: true }).content))
check(() => assert.match(configSource, /商城接口尚未完成升级/))
check(() => assert.match(configSource, /暂未发现可用的物流插件/))
check(() => assert.match(configSource, /loading.value \|\| loadError.value \|\| !formEl/))
const deliveryComponent = path.join(__dirname, '../admin/views/order/components/delivery-action.vue')
const deliverySource = fs.readFileSync(deliveryComponent, 'utf8')
const deliverySfc = sfc.parse(deliverySource, { filename: deliveryComponent })
check(() => assert.equal(deliverySfc.errors.length, 0))
check(() => assert.ok(sfc.compileScript(deliverySfc.descriptor, { id: 'waybill-delivery-test', inlineTemplate: true }).content))
check(() => assert.match(deliverySource, /v-else-if="externalProvider && showType === 'add'"/, 'unavailable provider cannot dereference its label'))
for (const relative of ['utils/electronic-sheet-provider.ts', 'views/order/components/provider-waybill-panel.vue', 'views/delivery/electronic_sheet_config.vue', 'views/order/components/delivery-action.vue']) {
    check(() => assert.equal(fs.readFileSync(path.join(root, 'admin/src/addon/phone_shop', relative), 'utf8'), fs.readFileSync(path.join(__dirname, '../admin', relative), 'utf8')))
}
console.log(`PASS: ${passed} UI checks; no network or printing.`)
