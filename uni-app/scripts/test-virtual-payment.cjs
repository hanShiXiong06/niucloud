// Pure helper + real cashier/config SFC setup. No network, real account, upload or payment.
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '..')
const ts = require('../node_modules/typescript')
const vue = require('../node_modules/vue')
const sfc = require('../node_modules/@vue/compiler-sfc')
const helperFile = path.join(root, 'src/utils/virtual-payment.ts')
const helperSandbox = { exports: {} }
const helperSource = fs.readFileSync(helperFile, 'utf8')
const helperJs = ts.transpileModule(helperSource, { reportDiagnostics: true, compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } })
assert.deepEqual(helperJs.diagnostics, [])
vm.runInNewContext(helperJs.outputText, helperSandbox, { filename: helperFile })
const helper = helperSandbox.exports
const ACTION = helper.WECHAT_VIRTUAL_PAYMENT_ACTION
const signed = JSON.stringify({ offerId: '123456', buyQuantity: 1, env: 1, currencyType: 'CNY', outTradeNo: 'VP_TEST_0001', productId: 'member-v1-year', goodsPrice: 9900, attach: 'FIXTURE_ONLY' })
const response = () => ({ client_action: ACTION, pay_data: { mode: 'short_series_goods', signData: signed, paySig: 'a'.repeat(64), signature: 'b'.repeat(64) } })
let checks = 0
const check = async (name, fn) => { await fn(); checks++; console.log('PASS', name) }
const rejects = (fn, code) => assert.rejects(fn, error => error instanceof helper.VirtualPaymentClientError && error.code === code)
const flush = async () => { for (let i = 0; i < 20; i++) await Promise.resolve() }
function bridge(behavior = 'success') {
    const calls = { login: [], native: [] }
    return { calls, login(options) { calls.login.push(options); options.success({ code: 'FRESH_CODE_' + calls.login.length }) }, canIUse: () => true,
        requestVirtualPayment(options) { calls.native.push(options); if (behavior === 'success') options.success({}); else if (behavior === 'cancel') options.fail({ errMsg: 'requestVirtualPayment:fail cancel' }); else options.fail({ errMsg: 'PRIVATE_PROVIDER_ERROR' }) } }
}
function platformSource(source, platform) {
    const stack = [true]
    return source.split(/\r?\n/).filter(line => {
        const start = line.match(/\/\/\s*#(ifdef|ifndef)\s+([A-Z0-9-]+)/)
        if (start) { stack.push(stack.at(-1) && (start[1] === 'ifdef' ? start[2] === platform : start[2] !== platform)); return false }
        if (/\/\/\s*#endif/.test(line)) { stack.pop(); return false }
        return stack.at(-1)
    }).join('\n')
}
function compiledSetup(filename, requireModule, globals = {}, platform) {
    const source = fs.readFileSync(filename, 'utf8')
    const { descriptor, errors } = sfc.parse(platform ? platformSource(source, platform) : source, { filename })
    assert.deepEqual(errors, [])
    const script = sfc.compileScript(descriptor, { id: 'virtual-payment-test' })
    assert.deepEqual(sfc.compileTemplate({ source: descriptor.template.content, filename, id: 'virtual-payment-test', compilerOptions: { bindingMetadata: script.bindings } }).errors, [])
    for (const style of descriptor.styles) assert.deepEqual(sfc.compileStyle({ source: style.content, filename, id: 'virtual-payment-test', preprocessLang: style.lang }).errors, [])
    const code = ts.transpileModule(script.content, { reportDiagnostics: true, compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } })
    assert.deepEqual(code.diagnostics, [])
    const sandbox = { exports: {}, ...globals, require(id) { if (id === 'vue') return vue; return requireModule(id) } }
    vm.runInNewContext(code.outputText, sandbox, { filename })
    return { module: sandbox.exports.default, descriptor, source }
}
function cashier(options = {}) {
    const calls = { api: [], native: [], ordinary: [], login: [], redirects: [], toasts: [], emits: [], storage: new Map() }
    const items = options.items || [{ key: 'synthetic_virtual', name: '微信支付', client_action: ACTION }]
    const payInfo = { trade_type: 'synthetic_business', trade_id: 71, body: 'Synthetic order', money: '99.00', pay_type_list: items }
    const uni = { getStorageSync: key => calls.storage.get(key), setStorageSync: (key, value) => calls.storage.set(key, value), removeStorageSync: key => calls.storage.delete(key),
        showToast: value => calls.toasts.push(value.title), $on() {}, $u: { test: { isEmpty: value => value === undefined || value === null || value === '' } },
        login(args) { calls.login.push(args); if (options.login) return options.login(args); args.success({ code: 'LOGIN_' + calls.login.length }) },
        requestPayment(args) { calls.ordinary.push(args); args.success({}) }, navigateToMiniProgram() { throw Error('Unexpected mini program navigation') } }
    const wx = { canIUse: () => true, requestVirtualPayment(args) { calls.native.push(args); if (options.native) return options.native(args); args.success({}) } }
    const api = { async getPayInfo() { return { data: payInfo } }, async pay(data) { calls.api.push(data); return options.pay ? options.pay(data) : { data: response() } } }
    const compiled = compiledSetup(path.join(root, 'src/components/pay/pay.vue'), id => {
        if (id === '@/locale') return { t: key => key }
        if (id === '@/app/api/pay') return api
        if (id === '@/utils/common') return { img: x => x, redirect: x => calls.redirects.push(x), isWeixinBrowser: () => false, moneyFormat: x => x }
        if (id === '@/utils/wechat') return { default: { init() {}, pay() { throw Error('Unexpected H5 pay') } } }
        if (id === '@/utils/virtual-payment') return helper
        throw Error('Unexpected dependency ' + id)
    }, { uni, wx: options.noWx ? undefined : wx }, options.platform || 'MP-WEIXIN')
    const state = compiled.module.setup({ ignorePay: [], reformat: '' }, { expose() {}, emit: (...args) => calls.emits.push(args) })
    return { state, calls, payInfo, compiled }
}
async function open(instance) { instance.state.open('synthetic_business', 71); await flush(); assert.equal(instance.state.show.value, true) }

async function main() {
    await check('ordinary payment preparation does not request a WeChat login', async () => assert.deepEqual(JSON.parse(JSON.stringify(await helper.preparePaymentClientRequest(undefined))), {}))
    await check('unknown actions and unsupported clients stop before login', async () => { await rejects(() => helper.preparePaymentClientRequest('unknown', bridge()), 'unsupported'); await rejects(() => helper.preparePaymentClientRequest(ACTION), 'unsupported'); const b = bridge(); b.canIUse = () => false; await rejects(() => helper.preparePaymentClientRequest(ACTION, b), 'unsupported'); assert.equal(b.calls.login.length, 0) })
    await check('every explicit attempt obtains a fresh code without storage', async () => { const b = bridge(); assert.equal((await helper.preparePaymentClientRequest(ACTION, b)).login_code, 'FRESH_CODE_1'); assert.equal((await helper.preparePaymentClientRequest(ACTION, b)).login_code, 'FRESH_CODE_2'); assert.doesNotMatch(helperSource, /StorageSync|session_key|AppKey\s*[:=]/) })
    await check('missing/failed login cannot reach payment', async () => { const b = bridge(); b.login = args => args.success({}); await rejects(() => helper.preparePaymentClientRequest(ACTION, b), 'login_failed'); b.login = args => args.fail({ errMsg: 'PRIVATE_LOGIN_ERROR' }); await rejects(() => helper.preparePaymentClientRequest(ACTION, b), 'login_failed') })
    await check('signed bytes remain unchanged and only official client fields are forwarded', async () => { const b = bridge(); const data = response(); data.pay_data.secret = 'DO_NOT_FORWARD'; await helper.executePaymentClientAction(ACTION, data, b); assert.equal(b.calls.native[0].signData, signed); assert.deepEqual(Object.keys(b.calls.native[0]).sort(), ['fail', 'mode', 'paySig', 'signData', 'signature', 'success'].sort()); assert.ok(!JSON.stringify(b.calls.native).includes('DO_NOT_FORWARD')) })
    await check('invalid parameters are rejected rather than falling through to success', async () => { for (const invalid of [null, {}, { client_action: ACTION, pay_data: {} }, { ...response(), pay_data: { ...response().pay_data, signData: '{' } }, { ...response(), pay_data: { ...response().pay_data, paySig: 'bad' } }, { ...response(), pay_data: { ...response().pay_data, mode: 'unknown' } }]) { const b = bridge(); await rejects(() => helper.executePaymentClientAction(ACTION, invalid, b), 'invalid_response'); assert.equal(b.calls.native.length, 0) } })
    await check('zero/fractional/foreign-currency/invalid identifiers do not invoke native payment', async () => { for (const patch of [{ goodsPrice: 0 }, { goodsPrice: 0.1 }, { buyQuantity: 2 }, { currencyType: 'USD' }, { env: 8 }, { outTradeNo: '_invalid' }]) { const data = response(); data.pay_data.signData = JSON.stringify({ ...JSON.parse(signed), ...patch }); await rejects(() => helper.executePaymentClientAction(ACTION, data, bridge()), 'invalid_response') } })
    await check('native cancellation is distinguishable; provider error detail is not exposed', async () => { await rejects(() => helper.executePaymentClientAction(ACTION, response(), bridge('cancel')), 'cancelled'); try { await helper.executePaymentClientAction(ACTION, response(), bridge('error')) } catch (error) { assert.equal(error.code, 'payment_failed'); assert.doesNotMatch(error.message, /PRIVATE/) } })
    await check('real cashier SFC compiles and uses metadata rather than plugin names', async () => { const c = cashier(); assert.doesNotMatch(c.compiled.source, /hsx_lead|synthetic_virtual/); assert.match(c.compiled.source, /client_action/); assert.match(c.compiled.source, /login_code/) })
    await check('cashier virtual flow sends fresh code and only native action, then existing server-result page', async () => { const c = cashier(); await open(c); await c.state.confirmPay(); await flush(); assert.equal(c.calls.api.length, 1); assert.equal(c.calls.api[0].login_code, 'LOGIN_1'); assert.equal(c.calls.native.length, 1); assert.equal(c.calls.ordinary.length, 0); assert.equal(c.calls.redirects[0].url, '/app/pages/pay/result'); assert.equal(c.calls.redirects[0].param.trade_id, 71); assert.equal(c.state.loading.value, false); assert.equal(c.calls.api[0].type, 'synthetic_virtual') })
    await check('cashier cancellation unlocks and returns for original-order verification without a second native payment', async () => { const c = cashier({ native: args => args.fail({ errMsg: 'requestVirtualPayment:fail cancel' }) }); await open(c); await c.state.confirmPay(); await flush(); assert.equal(c.state.loading.value, false); assert.equal(c.state.requiresOrderCheck.value, true); assert.equal(c.calls.redirects.length, 0); assert.equal(c.calls.toasts.length, 0); await c.state.confirmPay(); await flush(); assert.deepEqual(c.calls.api.map(x => x.login_code), ['LOGIN_1']); assert.equal(c.calls.native.length, 1); assert.equal(c.calls.ordinary.length, 0); assert.equal(c.state.show.value, false); assert.equal(c.calls.emits.at(-1)[0], 'close') })
    await check('cashier failure never switches to ordinary payment or claims paid', async () => { const c = cashier({ native: args => args.fail({ errMsg: 'PRIVATE_PAY_ERROR' }) }); await open(c); await c.state.confirmPay(); await flush(); assert.equal(c.calls.ordinary.length, 0); assert.equal(c.calls.redirects.length, 0); assert.equal(c.calls.emits.length, 0); assert.equal(c.state.loading.value, false); assert.doesNotMatch(c.calls.toasts.join(''), /PRIVATE/) })
    await check('H5 cannot request a virtual prepayment or fall back to JSAPI', async () => { const c = cashier({ platform: 'H5' }); await open(c); await c.state.confirmPay(); await flush(); assert.equal(c.calls.api.length, 0); assert.equal(c.calls.login.length, 0); assert.equal(c.calls.native.length, 0) })
    await check('unknown metadata action fails before API call', async () => { const c = cashier({ items: [{ key: 'future', name: '微信支付', client_action: 'future_action' }] }); await open(c); await c.state.confirmPay(); await flush(); assert.equal(c.calls.api.length, 0); assert.equal(c.calls.redirects.length, 0) })
    await check('unexpected server action and missing virtual parameters never become paid', async () => { for (const server of [{ client_action: 'unknown' }, { jsapi: { timeStamp: 'wrong-path' } }, true]) { const c = cashier({ pay: () => ({ data: server }) }); await open(c); await c.state.confirmPay(); await flush(); assert.equal(c.calls.native.length, 0); assert.equal(c.calls.ordinary.length, 0); assert.equal(c.calls.redirects.length, 0); assert.equal(c.state.loading.value, false) } })
    await check('ordinary WeChat and successful balance payment keep their existing routes', async () => { const normal = cashier({ items: [{ key: 'wechatpay', name: '微信支付' }], pay: () => ({ data: { timeStamp: 'fixture' } }) }); await open(normal); await normal.state.confirmPay(); await flush(); assert.equal(normal.calls.login.length, 0); assert.equal(normal.calls.ordinary.length, 1); assert.equal(normal.calls.native.length, 0); const balance = cashier({ items: [{ key: 'balancepay', name: '余额' }], pay: () => ({ data: true }) }); await open(balance); await balance.state.confirmPay(); await flush(); assert.equal(balance.calls.redirects.length, 1) })
    await check('unknown default driver response does not navigate to success', async () => { const c = cashier({ items: [{ key: 'unknown', name: 'Unknown' }], pay: () => ({ data: {} }) }); await open(c); await c.state.confirmPay(); await flush(); assert.equal(c.calls.redirects.length, 0); assert.equal(c.calls.emits.length, 0); assert.equal(c.state.loading.value, false) })
    await check('closing during login prevents a late API request and duplicate clicks share one attempt', async () => { let login; const c = cashier({ login: args => { login = args } }); await open(c); const pending = c.state.confirmPay(); c.state.confirmPay(); assert.equal(c.calls.login.length, 1); c.state.handleClose(); login.success({ code: 'LATE_LOGIN' }); await pending; await flush(); assert.equal(c.calls.api.length, 0); assert.equal(c.calls.native.length, 0) })
    await check('closing after prepay prevents a late native payment or result navigation', async () => { let resolve; const c = cashier({ pay: () => new Promise(done => { resolve = done }) }); await open(c); await c.state.confirmPay(); await flush(); assert.equal(c.calls.api.length, 1); c.state.handleClose(); resolve({ data: response() }); await flush(); assert.equal(c.calls.native.length, 0); assert.equal(c.calls.redirects.length, 0) })
    await check('configuration source and mounted copy are identical, and SFC script/template/styles compile', async () => {
        const file = path.resolve(root, '../niucloud/addon/hsx_lead/admin/views/setting/components/pay-hsx-lead-virtualpay.vue')
        assert.equal(fs.readFileSync(file, 'utf8'), fs.readFileSync(path.resolve(root, '../admin/src/addon/hsx_lead/views/setting/components/pay-hsx-lead-virtualpay.vue'), 'utf8'))
        const emitted = [], messages = []
        const { loadAdminUtility } = await import('../../niucloud/addon/hsx_lead/tests/admin-utils.mjs')
        const compiled = compiledSetup(file, id => {
            if (id === 'element-plus') return { ElMessage: { warning: x => messages.push(x) } }
            if (id === 'lodash-es') return { cloneDeep: x => JSON.parse(JSON.stringify(x)) }
            if (id === '../../../composables/useRemoteOptions') return loadAdminUtility('useRemoteOptions')
            if (id === '@/stores/modules/user') return { default: () => ({ token: 'SYNTHETIC_ADMIN', siteInfo: { site_id: 100005 } }) }
            if (id === '@/utils/storage') return { default: { get: key => key === 'siteId' ? 100005 : 'SYNTHETIC_ADMIN' } }
            if (id === '@/addon/hsx_lead/api/cooperation') return { getCooperationConfig: async () => ({ data: { membership_levels: [{ code: 'ML1', name: '青铜', cycles: [{ key: 'year', name: '年付', enabled: true, months: 12, price: '99.00', product_key:'SYS_1_YEAR' }] }] } }) }
            throw Error('Unexpected config dependency ' + id)
        })
        const state = compiled.module.setup({}, { expose() {}, emit: (...args) => emitted.push(args) })
        await state.setFormData(null)
        assert.equal(state.formData.status, 0); assert.equal(state.formData.config.payment_mode, 'standard'); assert.equal(state.formData.config.env, 1)
        assert.equal(state.formData.config.allow_deposit, false); assert.equal(state.formData.config.allow_experience, false); assert.equal(state.enableVerify(), true)
        state.formData.config.payment_mode = 'virtual'; assert.equal(state.enableVerify(), false)
        const mask = '*****************************'
        await state.setFormData({ key: 'hsx_lead_virtualpay', redio_key: 'weapp_hsx_lead_virtualpay', status: 0, config: { payment_mode: 'virtual', env: 1, offer_id: '123456', sandbox_app_key: mask, callback_token: mask, encoding_aes_key: mask, products: [{ product_id: 'v1', amount_cents: 9900, level_code: 'ML1', cycle: 'year', enabled: true }] } })
        assert.equal(state.enableVerify(), true); state.confirm(); assert.equal(emitted.at(-1)[1].config.sandbox_app_key, mask); assert.equal(emitted.at(-1)[1].config.products[0].amount_cents, 9900); assert.ok(!('_key' in emitted.at(-1)[1].config.products[0]))
        assert.equal(state.catalogRows.value.length, 1); assert.equal(state.catalogRows.value[0].amount_cents, 9900)
        state.updateBinding(state.catalogRows.value[0], 'product_id', 'bad/id'); assert.equal(state.catalogRows.value[0].product_id, 'bad/id'); assert.equal(state.catalogRows.value[0].enabled, false); assert.equal(state.enableVerify(), false)
        state.updateBinding(state.catalogRows.value[0], 'product_id', 'lead_v1_year99'); assert.equal(state.catalogRows.value[0].product_id, 'lead_v1_year99'); assert.equal(state.catalogRows.value[0].enabled, false); assert.equal(state.enableVerify(), false)
        state.updateBinding(state.catalogRows.value[0], 'enabled', true); assert.equal(state.enableVerify(), true)
        state.cancel(); assert.equal(emitted.at(-1)[1].type, 'hsx_lead_virtualpay'); assert.equal(emitted.at(-1)[1].config.products.length, 1)
        assert.ok(!('app_id' in emitted.at(-1)[1].config))
    })
    console.log('完成：' + checks + '项通用客户端/实际收银台/后台配置隔离检查；未联网、未发起真实支付或上传。')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
