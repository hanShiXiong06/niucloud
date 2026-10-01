// Offline regression for the real settings save handler; no browser, DB or network writes.
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const req = require('node:module').createRequire(path.join(root, 'admin/package.json'))
const { parse, compileScript, compileTemplate, compileStyle } = req('@vue/compiler-sfc')
const ts = req('typescript')
const vue = req('vue')
const file = path.join(root, 'admin/src/addon/hsx_recycle/views/order_config/submit.vue')
const source = fs.readFileSync(file, 'utf8')
const { descriptor, errors } = parse(source, { filename: file })
assert.deepEqual(errors, [])
const script = compileScript(descriptor, { id: 'delivery-save-qa' })
assert.deepEqual(compileTemplate({ source: descriptor.template.content, filename: file, id: 'delivery-save-qa', compilerOptions: { bindingMetadata: script.bindings } }).errors, [])
for (const style of descriptor.styles) {
    assert.deepEqual(compileStyle({ source: style.content, filename: file, id: 'delivery-save-qa', preprocessLang: style.lang, scoped: style.scoped }).errors, [])
}
assert.equal(source, fs.readFileSync(path.join(root, 'niucloud/addon/hsx_recycle/admin/views/order_config/submit.vue'), 'utf8'))
const executable = ts.transpileModule(descriptor.scriptSetup.content.replace(/^import .*$/gm, '') + '\n;({ form, load, save, dataLoaded, validationError, saving })', { compilerOptions: { target: ts.ScriptTarget.ES2020 } }).outputText
const fixture = {
    delivery_modes: { mail: 1, self: 1, logistics_vehicle: 0 },
    platform_delivery: { provider: 'yisu', provider_options: [], product_options: [], product_code: '', display_name: '' }
}
function setup(data = fixture) {
    const calls = []
    const state = { failRead: false, failSave: false }
    const context = {
        ref: vue.ref, reactive: vue.reactive, computed: vue.computed, nextTick: vue.nextTick,
        onMounted() {}, onBeforeUnmount() {}, onBeforeRouteLeave() {},
        useRouter: () => ({ push() {} }), useMediaQuery: () => vue.ref(false), useFeedback: () => ({ info() {} }),
        document: { getElementById: () => null },
        getOrderSubmitConfig: async () => { if (state.failRead) throw Error('offline'); return { data: JSON.parse(JSON.stringify(data)) } },
        saveOrderSubmitConfig: async value => { calls.push(value); if (state.failSave) throw { msg: '保存被拒绝' } },
    }
    return { page: vm.runInNewContext(executable, context), calls, state }
}
let checks = 0
function check(name, fn) { fn(); checks++; console.log('PASS', name) }
async function main() {
    check('settings SFC compiles and distribution mirror matches', () => assert.ok(script.content))
    const disabled = setup()
    await disabled.page.save()
    check('unloaded defaults cannot be saved', () => assert.equal(disabled.calls.length, 0))
    await disabled.page.load()
    disabled.page.form.platform_delivery.display_name = ''
    disabled.page.form.default_count = 3
    await disabled.page.save()
    check('no enabled courier line allows other settings to be saved', () => {
        assert.equal(disabled.calls.length, 1)
        assert.equal(disabled.calls[0].platform_delivery.product_code, '')
        assert.equal(disabled.calls[0].default_count, 3)
        assert.equal(disabled.calls[0].delivery_modes.mail, 1)
        assert.equal(disabled.page.validationError.value, '')
    })
    disabled.page.form.platform_delivery.product_code = 'disabled-old-code'
    await disabled.page.save()
    check('disabled old line is not submitted as an available courier route', () => assert.equal(disabled.calls[1].platform_delivery.product_code, ''))
    Object.assign(disabled.page.form.delivery_modes, { mail: 0, self: 0, logistics_vehicle: 1 })
    await disabled.page.save()
    check('logistics-vehicle only delivery saves without courier configuration', () => assert.equal(disabled.calls.length, 3))
    Object.assign(disabled.page.form.delivery_modes, { mail: 0, self: 0, logistics_vehicle: 0 })
    await disabled.page.save()
    check('at least one delivery channel is still required', () => { assert.equal(disabled.calls.length, 3); assert.ok(disabled.page.validationError.value.includes('至少')) })

    const enabled = setup({ ...fixture, platform_delivery: { provider: 'yisu', display_name: '测试快递', product_code: 'LINE-A', provider_options: [{ provider: 'yisu', provider_name: '测试服务商' }], product_options: [{ provider: 'yisu', product_code: 'LINE-A', product_name: '测试线路' }] } })
    await enabled.page.load()
    enabled.page.form.platform_delivery.display_name = ' '
    await enabled.page.save()
    check('active postal route still requires a customer-facing courier name', () => { assert.equal(enabled.calls.length, 0); assert.ok(enabled.page.validationError.value.includes('快递名称')) })
    enabled.page.form.platform_delivery.display_name = '测试快递'
    await enabled.page.save()
    check('enabled route is preserved during normal save', () => { assert.equal(enabled.calls.length, 1); assert.equal(enabled.calls[0].platform_delivery.product_code, 'LINE-A') })
    enabled.page.form.delivery_modes.mail = 0
    enabled.page.form.platform_delivery.display_name = ''
    await enabled.page.save()
    check('self-delivery only does not require courier fields even with a configured route', () => assert.equal(enabled.calls.length, 2))
    enabled.state.failSave = true
    await enabled.page.save()
    check('API failure is reported and saving state is reset', () => { assert.equal(enabled.page.validationError.value, '保存被拒绝'); assert.equal(enabled.page.saving.value, false) })
    const failed = setup()
    failed.state.failRead = true
    await failed.page.load(); await failed.page.save()
    check('failed config read cannot overwrite saved settings', () => { assert.equal(failed.calls.length, 0); assert.equal(failed.page.dataLoaded.value, false) })
    console.log(`Completed ${checks} checks. No business requests sent.`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
