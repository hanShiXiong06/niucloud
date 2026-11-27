import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { webcrypto } from 'node:crypto'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
import { createAdminApiSourceFixture } from './api-source-fixture.mjs'

// Real Vue setup/watch/ref execution, synthetic promises and a deterministic clock. No external services.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const require = createRequire(path.join(root, 'admin/package.json'))
const Vue = require('vue'), compiler = require('@vue/compiler-sfc'), ts = require('typescript')
const { compile: compileDom } = require('@vue/compiler-dom'), { renderToString } = require('@vue/server-renderer')
const base = path.join(root, 'niucloud/addon/hsx_lead/admin')
const read = file => fs.readFileSync(path.join(base, file), 'utf8')
const wizardSource = read('views/components/ConnectionWizard.vue'), settingsSource = read('views/components/CaptchaSettings.vue'), automaticSource = read('composables/useAutomaticAuthorization.ts')
const transpile = source => ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const compile = (source, name) => transpile(compiler.compileScript(compiler.parse(source, { filename: name }).descriptor, { id: name, inlineTemplate: false }).content)
const wizardJs = compile(wizardSource, 'ConnectionWizard.vue'), settingsJs = compile(settingsSource, 'CaptchaSettings.vue'), automaticJs = transpile(automaticSource)
const inputNumberJs = transpile(read('directives/inputNumberA11y.ts'))
const renewalJs = transpile(read('composables/authRenewal.ts'))
let checks = 0
const check = (condition, message) => { assert.ok(condition, message); checks++ }
const deferred = () => { let resolve, reject; const promise = new Promise((a, b) => { resolve = a; reject = b }); return { promise, resolve, reject } }
async function settle() { for (let index = 0; index < 8; index++) { await Promise.resolve(); await Vue.nextTick() } }
const token = 'a'.repeat(64)
const schema = { key: 'synthetic-interactive', name: '合成 ERP', fields: [{ key: 'username', label: '账号', identity: true, required: true }, { key: 'access_token', label: 'Token', secret: true, required: true }], scope_fields: [], interactive_auth: { type: 'image_captcha', automatic_recognition: true, credential_fields: ['username'], fields: [{ key: 'password', label: '密码', secret: true, required: true }], default: true } }
const publicConfig = { provider: '2captcha', enabled: true, configured: true, key_hint: '••••demo', daily_limit: 20, used_today: 0, remaining_today: 20, revision: 1, notice: '纯合成测试，不识别或计费。' }
function harness(options = {}) {
    let now = Date.now(), nextTimer = 0
    const timers = new Map(), lifecycle = [], calls = [], events = []
    class ClockDate extends Date { static now() { return now } }
    const clock = { get now() { return now }, jump(ms) { now += ms }, async tick(ms) { now += ms; for (let i = 0; i < 30; i++) { const next = [...timers].find(([, item]) => item.at <= now); if (!next) break; timers.delete(next[0]); next[1].fn(); await settle() } }, get count() { return timers.size } }
    const props = Vue.reactive({ modelValue: true, merchantId: 1, merchantName: '合成测试商户' })
    const processing = (stage = 'recognizing') => ({ data: { task_token: token, status: 'processing', stage, message: '合成异步阶段', expires_at: Math.floor(now / 1000) + 180, retry_after: 5 } })
    const proof = () => ({ probe_token: 'synthetic-proof', expires_at: Math.floor(now / 1000) + 600, preview: { first_page_count: 0, empty_inventory: true, can_activate: true, identity: { store_id: '43' } }, impact: {} })
    const controls = { config: async () => ({ data: options.config || publicConfig }), start: async () => processing('preparing'), advance: async () => calls.filter(row => row.action === 'advance').length === 1 ? processing() : { data: { task_token: token, status: 'completed', result: proof() } }, cancel: async () => ({ data: {} }), save: async (_data) => ({ data: publicConfig }), balance: async () => ({ data: { balance: '7.25', currency: 'TEST' } }), probe: async () => ({ data: proof() }) }
    if (options.configFailure) controls.config = async () => { throw { msg: '合成权限不足' } }
    const call = (action, fn) => (...args) => { calls.push({ action, args: structuredClone(args) }); return fn(...args) }
    const api = { getProviders: async () => ({ data: { items: [options.schema || schema, ...(options.extraProviders || [])] } }), getConnection: async () => ({ data: options.connection || { connected: false, credentials: {}, scope: {} } }),
        getCaptchaConfig: call('config', (...args) => controls.config(...args)), startAutomaticAuthorization: call('start', (...args) => controls.start(...args)),
        advanceAutomaticAuthorization: call('advance', (...args) => controls.advance(...args)), cancelAutomaticAuthorization: call('cancel', (...args) => controls.cancel(...args)),
        saveCaptchaConfig: call('save', (...args) => controls.save(...args)), checkCaptchaBalance: call('balance', (...args) => controls.balance(...args)),
        probeConnection: call('probe', (...args) => controls.probe(...args)), activateConnection: call('activate', async () => ({ data: {} })), saveSyncSettings: async () => ({ data: {} }) }
    const globals = { Date: ClockDate, crypto: webcrypto, Uint8Array, setTimeout: (fn, delay) => { const id = ++nextTimer; timers.set(id, { fn, at: now + delay }); return id }, clearTimeout: id => timers.delete(id) }
    const load = source => { const module = { exports: {} }; vm.runInNewContext(`(function(require,module,exports){${source}\n})`, globals)(localRequire, module, module.exports); return module.exports }
    const localRequire = key => key === 'vue' ? { ...Vue, onBeforeUnmount: callback => lifecycle.push(callback) } : key === '../../api' ? api
        : key.includes('useLeadUi') ? { time: value => String(value), errorText: (cause, fallback) => cause?.msg || cause?.message || fallback }
            : key.includes('useAutomaticAuthorization') ? load(automaticJs) : key.includes('inputNumberA11y') ? load(inputNumberJs) : key.includes('authRenewal') ? load(renewalJs) : key.includes('HsxDrawer') || key.includes('CaptchaSettings.vue') ? { default: {} } : (() => { throw Error(key) })()
    const state = load(options.settings ? settingsJs : wizardJs).default.setup(props, { expose() {}, emit(event, value) { events.push({ event, value }); if (event === 'update:modelValue') props.modelValue = value } })
    return { state, props, calls, controls, clock, proof, processing, events, dispose() { lifecycle.forEach(callback => callback()); timers.clear() } }
}
function fill(state) { state.step.value = 1; state.credentials.username = 'synthetic-account'; state.authorizationInput.password = 'synthetic-password' }
const actions = (h, action) => h.calls.filter(call => call.action === action)
async function wizardHtml(state) {
    const render = new Function('Vue', compileDom(compiler.parse(wizardSource).descriptor.template.content, { mode: 'function', prefixIdentifiers: true }).code)(Vue)
    const app = Vue.createSSRApp({ render, setup: () => ({ ...state, merchantName: '合成商户' }) })
    app.config.warnHandler = () => {}
    const passthrough = { props: ['label'], setup: (props, { slots }) => () => Vue.h('div', [props.label || '', slots.default?.(), slots.footer?.()]) }
    const button = { props: ['disabled', 'loading'], setup: (props, { slots }) => () => Vue.h('button', { disabled: props.disabled || props.loading }, slots.default?.()) }
    const input = { props: ['modelValue', 'type', 'placeholder'], setup: props => () => Vue.h('input', { type: props.type, value: props.modelValue || '', placeholder: props.placeholder }) }
    for (const name of [...new Set(wizardSource.match(/el-[a-z-]+/g))]) app.component(name, name === 'el-button' ? button : name === 'el-input' ? input : passthrough)
    app.component('HsxDrawer', passthrough); app.component('CaptchaSettings', { render: () => null })
    return renderToString(app)
}

for (const file of ['views/components/ConnectionWizard.vue', 'views/components/CaptchaSettings.vue', 'composables/useAutomaticAuthorization.ts', 'api/index.ts']) check(read(file) === fs.readFileSync(path.join(root, 'niucloud/addon/hsx_lead/admin', file), 'utf8'), `${file}: source and package match`)
check(!/localStorage|sessionStorage|console\.(log|debug)/.test(wizardSource + settingsSource + automaticSource), 'no secrets persist in browser storage or logs')
check(read('api/index.ts').includes("automatic/advance`, data, { ...quiet, timeout: 45000 }") && read('api/index.ts').includes('resume_only') === false, 'long advance uses explicit timeout; resume remains in task state machine')
check(wizardSource.includes('automatic_recognition === true'), 'automatic login requires the explicit provider capability')

let h = harness(); await settle(); let s = h.state
check(s.automaticMode.value && s.recognitionMode.value === 'automatic', 'configured supported provider defaults to automatic, with no user mode selection needed')
fill(s); s.credentials.access_token = 'unused-synthetic-token'
const startPending = deferred(); h.controls.start = () => startPending.promise
const login = s.probe()
check(s.authorizationInput.password === undefined && s.automatic.active.value, 'password disappears synchronously while start is still pending')
check(actions(h, 'start').length === 1 && /^[a-f0-9]{32}$/.test(actions(h, 'start')[0].args[1].request_id), 'start creates one secure 32-hex idempotency key')
const body = actions(h, 'start')[0].args[1]
check(body.password === 'synthetic-password' && Object.keys(body.credentials).join(',') === 'username', 'password is one-time, permanent request credentials contain only username')
await s.probe(); check(actions(h, 'start').length === 1, 'duplicate clicks do not create another recognition task')
startPending.resolve(h.processing('preparing')); await login
check(s.automatic.stage.value === 'preparing' && !actions(h, 'advance').length, 'local task creation does not immediately advance or pretend recognition finished')
await h.clock.tick(4999); check(!actions(h, 'advance').length, 'first poll cannot happen before five seconds')
await h.clock.tick(1); check(actions(h, 'advance').length === 1 && s.automatic.stage.value === 'recognizing', 'first timed advance updates the visible recognition stage')
await h.clock.tick(4999); check(actions(h, 'advance').length === 1, 'successive polls are at least five seconds apart')
await h.clock.tick(1); check(s.step.value === 2 && s.probeToken.value === 'synthetic-proof', 'completed automatic login reaches the same verified preview')
check(!actions(h, 'activate').length && !s.automatic.active.value && !Object.keys(s.authorizationInput).length, 'login does not activate connection or retain temporary inputs')
check(h.clock.count === 0, 'successful task leaves no polling timer')
h.dispose()

h = harness(); await settle(); s = h.state; fill(s)
let html = await wizardHtml(s)
check(html.includes('自动登录已就绪') && html.includes('自动登录并预览') && !html.includes('填写图片中的 4 位验证码'), 'real ready template defaults to one-click login, not a visible CAPTCHA input')
await s.probe(); html = await wizardHtml(s)
check(html.includes('正在自动登录') && html.includes('停止本次登录') && html.includes('lead-auth-spinner'), 'real processing template renders visible progress and stop control')
check(!html.includes('synthetic-password'), 'rendered processing state cannot leak the cleared password')
s.recognitionMode.value = 'manual'; html = await wizardHtml(s)
check(html.includes('填写图片中的 4 位验证码') && html.includes('获取验证码') && !html.includes('lead-auth-spinner'), 'manual mode renders the normal image CAPTCHA without stale progress'); h.dispose()

for (const config of [{ ...publicConfig, enabled: false }, { ...publicConfig, configured: false }, { ...publicConfig, remaining_today: 0 }]) {
    h = harness({ config }); await settle(); check(!h.state.automaticMode.value && h.state.interactiveMode.value, 'disabled, unconfigured or exhausted service still allows manual CAPTCHA'); h.dispose()
}
h = harness({ schema: { ...schema, interactive_auth: { ...schema.interactive_auth, automatic_recognition: false } } }); await settle(); check(!h.state.automaticMode.value, 'global readiness cannot enable an unsupported provider'); h.dispose()
h = harness({ configFailure: true }); await settle(); check(!h.state.automaticMode.value && !h.state.error.value && h.state.interactiveMode.value, 'config permission failure is non-blocking and does not suppress manual login'); h.dispose()

h = harness(); const slowConfig = deferred(); h.controls.config = () => slowConfig.promise; await settle(); s = h.state
s.recognitionChoice.value = 'manual'; s.credentials.username = 'synthetic-manual-choice'
slowConfig.resolve({ data: publicConfig }); await settle()
check(s.automaticReady.value && !s.automaticMode.value && !s.captchaConfigLoading.value, 'late configuration neither overrides a manual choice nor gets invalidated by typing an account'); h.dispose()

h = harness(); await settle(); s = h.state; fill(s)
s.recognitionMode.value = 'manual'; check(!s.automaticMode.value && !s.authorizationInput.password, 'manual fallback clears transient automatic input')
s.credentials.access_token = 'synthetic-fallback-token'; s.authMode.value = 'token'; s.credentials.access_token = 'synthetic-fallback-token'; await s.probe()
check(actions(h, 'probe').length === 1 && !actions(h, 'start').length, 'existing Token validation remains a separate non-recognition path'); h.dispose()

h = harness(); await settle(); s = h.state; fill(s)
let startCount = 0; h.controls.start = async () => { if (++startCount === 1) throw Error('合成响应中断'); return h.processing() }
await s.probe(); check(s.automatic.status.value === 'paused' && !s.authorizationInput.password, 'lost start response pauses without recovering the password')
const originalRequestId = actions(h, 'start')[0].args[1].request_id
await s.automatic.resume(); const resumed = actions(h, 'start')[1].args[1]
check(resumed.resume_only === true && resumed.request_id === originalRequestId && !('password' in resumed), 'ambiguous start resumes only the original idempotency key and never resends a password')
check(s.automatic.taskToken.value === token && actions(h, 'start').length === 2, 'resumed task continues under its original server token'); h.dispose()

h = harness(); await settle(); s = h.state; fill(s); await s.probe()
let advances = 0; h.controls.advance = async () => { if (++advances === 1) throw Error('合成查询超时'); return h.processing('logging_in') }
await h.clock.tick(5000); check(s.automatic.status.value === 'paused' && s.automatic.taskToken.value === token && h.clock.count === 0, 'advance timeout retains token and stops automatic retries')
await s.automatic.resume(); check(actions(h, 'advance').length === 1, 'immediate resume respects the minimum polling interval')
await h.clock.tick(5000); check(actions(h, 'advance').length === 2 && s.automatic.stage.value === 'logging_in' && actions(h, 'start').length === 1, 'continue queries the same task, not a second paid start'); h.dispose()

h = harness(); await settle(); s = h.state; fill(s); await s.probe()
h.controls.advance = async () => ({ data: { status: 'failed', message: '合成识别未成功', manual_available: true } })
await h.clock.tick(5000); check(s.automatic.status.value === 'failed' && s.automatic.message.value.includes('未成功') && !s.automatic.active.value, 'server failure ends task with actionable manual fallback')
await h.clock.tick(30000); check(actions(h, 'advance').length === 1 && actions(h, 'start').length === 1, 'failed task never implicitly loops recognition'); h.dispose()

h = harness(); await settle(); s = h.state; fill(s); await s.probe(); h.clock.jump(181000); await h.clock.tick(0)
check(s.automatic.status.value === 'expired' && !actions(h, 'advance').length, 'expired task cannot send another advance'); h.dispose()

for (const change of ['account', 'mode', 'provider', 'close', 'merchant', 'unmount']) {
    h = harness({ extraProviders: [{ ...schema, key: 'other-synthetic-provider' }] }); await settle(); s = h.state; fill(s); await s.probe()
    if (change === 'account') s.credentials.username = 'synthetic-second-account'
    if (change === 'mode') s.recognitionMode.value = 'manual'
    if (change === 'provider') s.choose('other-synthetic-provider')
    if (change === 'close') s.visible.value = false
    if (change === 'merchant') h.props.merchantId = 2
    if (change === 'unmount') h.dispose()
    await settle()
    check(actions(h, 'cancel').length === 1 && actions(h, 'cancel')[0].args[0] === 1 && actions(h, 'cancel')[0].args[1].task_token === token, `${change}: cancel captures the original merchant and opaque token`)
    check(!s.automatic.active.value && !s.authorizationInput.password, `${change}: stop timers and clear temporary login state`)
    await h.clock.tick(6000); check(!actions(h, 'advance').length, `${change}: old automatic task cannot continue polling`); h.dispose()
}

h = harness(); await settle(); s = h.state; fill(s)
const lateStart = deferred(); h.controls.start = () => lateStart.promise; const oldStart = s.probe(); s.credentials.username = 'synthetic-replacement'
lateStart.resolve(h.processing()); await oldStart
check(!s.automatic.active.value && !s.automatic.taskToken.value && actions(h, 'cancel').length === 1, 'late start is ignored and its now-known task is cancelled'); h.dispose()

h = harness(); await settle(); s = h.state; fill(s); await s.probe()
const lateAdvance = deferred(); h.controls.advance = () => lateAdvance.promise; await h.clock.tick(5000); s.visible.value = false
lateAdvance.resolve({ data: { task_token: token, status: 'completed', result: h.proof() } }); await settle()
check(s.step.value !== 2 && !s.probeToken.value && !s.automatic.active.value, 'late completed result cannot reopen a closed wizard or install a proof'); h.dispose()

h = harness(); await settle(); s = h.state; fill(s); await s.probe()
h.controls.advance = async () => ({ data: { task_token: token, status: 'completed', result: { authorization_required: true, status: 'choose_company', challenge_token: 'synthetic-company', expires_at: Math.floor(h.clock.now / 1000) + 120, companies: [{ id: '43', name: '合成公司' }] } } })
await h.clock.tick(5000); check(s.step.value === 1 && s.companies.value.length === 1 && !s.authorizationInput.password, 'automatic multi-company result returns to explicit human company selection')
s.selectedCompany.value = '43'; await s.probe(); const companyAnswer = actions(h, 'probe')[0].args[1].authorization
check(Object.keys(companyAnswer).sort().join(',') === 'challenge_token,company_id' && companyAnswer.company_id === '43', 'company followup sends only the returned ID and short-lived challenge')
check(s.step.value === 2 && actions(h, 'start').length === 1, 'company completion reuses normal proof flow without another recognition'); h.dispose()

h = harness({ connection: { provider: schema.key, connected: true, credentials: { username: 'synthetic-account-A' }, scope: { store_id: '42' }, configured_fields: ['username', 'access_token'] } }); await settle(); s = h.state
s.recognitionMode.value = 'manual'; check(s.scope.store_id === '42', 'same-account recognition mode change does not destroy saved source scope')
s.credentials.username = 'synthetic-account-B'; s.recognitionMode.value = 'automatic'; s.authorizationInput.password = 'synthetic-password'; await s.probe()
check(!('store_id' in actions(h, 'start')[0].args[1].scope), 'changing account removes old saved company from automatic payload as well'); h.dispose()

// Settings exercise real setup, synthetic endpoints, no key persistence or billing.
h = harness({ settings: true }); await settle(); s = h.state
check(s.form.api_key === '' && s.config.value.configured && s.config.value.key_hint === '••••demo', 'saved settings display a mask, never recover a plaintext key')
check(settingsSource.includes('<el-input-number v-input-number-a11y') && !!s.vInputNumberA11y, 'quota input binds the existing plugin accessibility directive after async loading')
const attrs = { 'aria-disabled': 'true' }; let nativeChanges = 0, businessEvents = 0, focusChanges = 0
const nativeInput = { get disabled() { return false }, set disabled(_value) { nativeChanges++ }, removeAttribute(name) { delete attrs[name] }, setAttribute(name, value) { attrs[name] = value }, dispatchEvent() { businessEvents++ }, focus() { focusChanges++ } }
s.vInputNumberA11y.updated({ querySelectorAll: () => [nativeInput] }); await settle()
check(!('aria-disabled' in attrs), 'quota loading release removes stale aria-disabled on an actually enabled native input')
check(nativeChanges === 0 && businessEvents === 0 && focusChanges === 0, 'ARIA repair never changes native disabled, dispatches business events or changes focus')
s.config.value.key_hint = '已配置（••••）'; check(s.keyStatus.value === '已配置（••••），不会回显完整密钥。', 'real backend saved-key hint does not repeat the configured prefix')
s.form.api_key = 'b'.repeat(32); await s.save(); check(!actions(h, 'save').length && s.error.value.includes('确认'), 'enabling identification requires explicit fee/privacy consent')
s.consent.value = true; s.form.daily_limit = 1001; await s.save(); check(!actions(h, 'save').length && s.error.value.includes('1–1000'), 'daily quota has bounded integer validation')
s.form.daily_limit = 20; await s.save(); check(actions(h, 'save').length === 1 && actions(h, 'save')[0].args[0].consent === true && !s.form.api_key, 'successful save sends explicit consent and immediately clears the new key'); h.dispose()
h = harness({ settings: true }); await settle(); s = h.state; s.form.enabled = false; await s.save()
check(actions(h, 'save').length === 1 && actions(h, 'save')[0].args[0].consent === false, 'disabling a service does not manufacture recognition consent'); h.dispose()
h = harness({ settings: true }); await settle(); s = h.state; await s.balance()
check(actions(h, 'balance').length === 1 && s.balanceText.value === '余额 7.25 TEST', 'balance is explicitly queried and displays only returned currency without conversion')
check(!actions(h, 'start').length && !actions(h, 'advance').length, 'balance query cannot start recognition')
s.form.api_key = 'synthetic-unsaved-key'; await s.balance(); check(actions(h, 'balance').length === 1, 'unsaved replacement key must be saved before balance query')
s.visible.value = false; check(s.form.api_key === '' && !s.consent.value && !s.balanceText.value, 'closing settings clears editable key, consent and balance'); h.dispose()
h = harness({ settings: true, configFailure: true }); await settle(); check(h.state.error.value.includes('权限') && !h.state.form.api_key, 'settings permission failure is clear and does not reveal keys'); h.dispose()
check(settingsSource.includes('可能由人工识别') && settingsSource.includes('按识别次数扣费') && settingsSource.includes('不需要填写公网回调地址'), 'privacy, per-use charge and polling setup are explained in plain language')

h = harness({ settings: true }); await settle(); s = h.state; s.consent.value = true; s.form.api_key = 'not-a-provider-key'; await s.save()
check(!actions(h, 'save').length && s.error.value.includes('32 位'), 'malformed recognition key is rejected before sending a save')
s.form.api_key = 'b'.repeat(32); s.form.clear_key = true; await s.save()
check(!actions(h, 'save').length && s.error.value.includes('不能同时'), 'clear and replacement cannot send contradictory instructions'); h.dispose()
h = harness({ settings: true }); await settle(); s = h.state; h.controls.balance = async () => ({ data: { balance: '4.50', currency: null, message: '单位以服务商账户为准' } }); await s.balance()
check(s.balanceText.value === '余额 4.50 · 单位以服务商账户为准', 'missing currency never becomes an invented unit and preserves the backend clarification'); h.dispose()

// The retired demo server is gone. Verify the real plugin wrappers, not a fake server's implementation.
const transport = createAdminApiSourceFixture(root, ts), endpoints = transport.api
await endpoints.getCaptchaConfig()
check(transport.calls.at(-1).method === 'get' && transport.calls.at(-1).args[0] === 'hsx_lead/captcha/config', 'saved recognition config is read through the current plugin endpoint')
await endpoints.saveCaptchaConfig({ enabled: true, api_key: 'b'.repeat(32), consent: true })
check(transport.calls.at(-1).method === 'put' && transport.calls.at(-1).args[0] === 'hsx_lead/captcha/config' && transport.calls.at(-1).args[1].consent === true, 'recognition setup forwards explicit consent through the configuration PUT')
await endpoints.checkCaptchaBalance()
check(transport.calls.at(-1).method === 'post' && transport.calls.at(-1).args[0] === 'hsx_lead/captcha/balance' && transport.calls.at(-1).args[2].timeout === 45000, 'balance lookup uses its own bounded endpoint, not a recognition request')
const submit = { provider: schema.key, credentials: { username: 'synthetic-api-account' }, scope: {}, password: 'synthetic-password', request_id: '1'.repeat(32) }
await endpoints.startAutomaticAuthorization(17, submit)
check(transport.calls.at(-1).method === 'post' && transport.calls.at(-1).args[0] === 'hsx_lead/merchants/17/connection/auth/automatic', 'automatic start is bound to the selected merchant')
check(transport.calls.at(-1).args[1].request_id === submit.request_id && transport.calls.at(-1).args[1].password === submit.password, 'the explicitly started request preserves its idempotency key and one-time password')
const recovery = { ...submit, password: undefined, resume_only: true }; delete recovery.password
await endpoints.startAutomaticAuthorization(17, recovery)
check(transport.calls.at(-1).args[1].resume_only && !('password' in transport.calls.at(-1).args[1]), 'same-request recovery needs no hidden or reconstructed password')
await endpoints.advanceAutomaticAuthorization(17, { task_token: token })
check(transport.calls.at(-1).args[0] === 'hsx_lead/merchants/17/connection/auth/automatic/advance' && transport.calls.at(-1).args[2].timeout === 45000, 'polling uses the original task endpoint with a bounded wait')
await endpoints.cancelAutomaticAuthorization(17, { task_token: token })
check(transport.calls.at(-1).args[0] === 'hsx_lead/merchants/17/connection/auth/automatic/cancel' && transport.calls.at(-1).args[1].task_token === token, 'explicit stop forwards the original opaque task token')
check(transport.calls.every(call => call.args.at(-1).showErrorMessage === false), 'current auth wrappers leave actionable error presentation to their consuming components')
console.log(`PASS ${checks} automatic-auth UI checks; real Vue, synthetic promises and clock only. No real CAPTCHA, third-party traffic, charges, database writes or visual acceptance.`)
