import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
import { createAdminApiSourceFixture } from './api-source-fixture.mjs'

// Actual Vue setup/ref/watch logic with isolated API promises. No browser, ERP, DB or credentials.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const require = createRequire(path.join(root, 'admin/package.json'))
const Vue = require('vue'), compiler = require('@vue/compiler-sfc'), ts = require('typescript')
const sourcePath = path.join(root, 'niucloud/addon/hsx_lead/admin/views/components/ConnectionWizard.vue')
const source = fs.readFileSync(sourcePath, 'utf8')
const descriptor = compiler.parse(source, { filename: sourcePath }).descriptor
const compiled = compiler.compileScript(descriptor, { id: 'interactive-auth-test', inlineTemplate: false })
const js = ts.transpileModule(compiled.content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const automaticJs = ts.transpileModule(fs.readFileSync(path.join(root, 'niucloud/addon/hsx_lead/admin/composables/useAutomaticAuthorization.ts'), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const renewalJs = ts.transpileModule(fs.readFileSync(path.join(root, 'niucloud/addon/hsx_lead/admin/composables/authRenewal.ts'), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
let checks = 0
function check(value, message) { assert.ok(value, message); checks++ }
const png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jAF8AAAAASUVORK5CYII='
const identityFields = [{ key: 'username', label: '登录账号', required: true, identity: true }, { key: 'access_token', label: '授权 Token', required: true, secret: true }]
const schemas = [{ key: 'fixture-token', name: '合成会话 ERP', fields: identityFields, scope_fields: [], interactive_auth: { type: 'image_captcha', credential_fields: ['username'], fields: [{ key: 'password', label: '登录密码', secret: true, required: true }], default: true } },
    { key: 'fixture-password', name: '合成密码 ERP', fields: [{ key: 'username', label: '登录账号', required: true, identity: true }, { key: 'password', label: '登录密码', required: true, secret: true }], scope_fields: [] }]
const challenge = (token = 'synthetic-challenge') => ({ data: { challenge_token: token, expires_at: Math.floor(Date.now() / 1000) + 180, captcha_image: png } })
const proof = () => ({ data: { probe_token: 'synthetic-proof', expires_at: Math.floor(Date.now() / 1000) + 600,
    preview: { identity: { store_id: '42' }, stores: [{ store_id: '42', name: '合成公司' }], first_page_count: 0, empty_inventory: true, can_activate: true }, impact: {} } })
function deferred() { let resolve, reject; const promise = new Promise((ok, fail) => { resolve = ok; reject = fail }); return { promise, resolve, reject } }
async function settle() { for (let i = 0; i < 5; i++) { await Promise.resolve(); await Vue.nextTick() } }
function harness(savedConnection = { connected: false, credentials: {}, scope: {} }) {
    const props = Vue.reactive({ modelValue: true, merchantId: 1, merchantName: '合成商户' })
    const calls = [], lifecycle = [], events = []
    const control = { challenge: async () => challenge(), probe: async () => proof(), activate: async () => ({ data: {} }) }
    const api = {
        getProviders: async () => ({ data: { items: schemas } }), getConnection: async () => ({ data: savedConnection }),
        getAuthorizationChallenge: (...args) => { calls.push({ action: 'challenge', args }); return control.challenge(...args) },
        probeConnection: (...args) => { calls.push({ action: 'probe', args }); return control.probe(...args) },
        activateConnection: (...args) => { calls.push({ action: 'activate', args }); return control.activate(...args) },
        saveSyncSettings: async (...args) => { calls.push({ action: 'settings', args }); return { data: {} } }
    }
    const module = { exports: {} }
    const localRequire = key => key === 'vue' ? { ...Vue, onBeforeUnmount: callback => lifecycle.push(callback) }
        : key === '../../api' ? api : key.includes('useLeadUi') ? { time: value => String(value), errorText: (cause, fallback) => cause?.msg || cause?.message || fallback }
            : key.includes('HsxDrawer') || key.includes('CaptchaSettings.vue') ? { default: {} }
                : key.includes('useAutomaticAuthorization') ? (() => { const child = { exports: {} }; vm.runInNewContext(`(function(require,module,exports){${automaticJs}\n})`, { Date, setTimeout, clearTimeout })(localRequire, child, child.exports); return child.exports })()
                    : key.includes('authRenewal') ? (() => { const child = { exports: {} }; vm.runInNewContext(`(function(require,module,exports){${renewalJs}\n})`, { Date })(localRequire, child, child.exports); return child.exports })()
                    : (() => { throw new Error('Unexpected dependency: ' + key) })()
    vm.runInNewContext(`(function(require,module,exports){${js}\n})`, { console, Date, setTimeout, clearTimeout })(localRequire, module, module.exports)
    const state = module.exports.default.setup(props, { expose() {}, emit(event, value) { events.push({ event, value }); if (event === 'update:modelValue') props.modelValue = value } })
    return { state, props, calls, control, events, dispose: () => lifecycle.forEach(callback => callback()) }
}
function fill(state) { state.step.value = 1; state.credentials.username = 'synthetic-user'; state.authorizationInput.password = 'synthetic-temporary-password' }

check(!/wanji|soujike|jixiaoer|aiguanji/.test(source), 'interactive UI is capability driven, not provider name driven')
check(!/localStorage|sessionStorage|console\.(?:log|debug)|authorizationInput.+JSON\.stringify/.test(source), 'UI does not persist or log transient authentication')
check(source.includes('getAuthorizationChallenge') && source.includes('autocomplete="off"') && source.includes('interactive_auth'), 'challenge API and explicit input handling are wired')

let h = harness(); await settle(); let s = h.state
check(source.includes(':formatter="(_row, _column, value) => previewPrice(value)"'), 'preview price column uses the explicit missing-value formatter')
for (const value of [null, undefined, '', ' ', '***', NaN, Infinity, false, {}, '1.234', '-1']) {
    check(s.previewPrice(value) === '未提供', 'missing, masked or invalid snapshot prices are never displayed as zero')
}
for (const [value, expected] of [[0, '¥0.00'], ['0', '¥0.00'], ['0.00', '¥0.00'], ['0000.0', '¥0.00'], [9, '¥9.00'], [9.5, '¥9.50'], ['1234.56', '¥1234.56'], ['99999999999999.99', '¥99999999999999.99']]) {
    check(s.previewPrice(value) === expected, 'known zero and valid decimal prices remain explicit and exact')
}
check(s.provider.value === 'fixture-token' && s.interactiveMode.value, 'metadata enables interactive login by default')
check(s.interactiveCredentialFields.value.length === 1 && s.interactiveCredentialFields.value[0].key === 'username', 'interactive form never requests a permanent Token or stores password as permanent credentials')
await s.probe(); check(h.calls.length === 0 && s.error.value.includes('登录账号'), 'missing identity is rejected before any authorization call')
fill(s); await s.refreshChallenge()
check(h.calls[0].args[1].credentials.username === 'synthetic-user' && !('password' in h.calls[0].args[1].credentials), 'challenge sends only explicit nonsecret identity')
check(s.challengeToken.value === 'synthetic-challenge' && s.captchaImage.value === png, 'valid image challenge is displayed without changing stored credentials')
check(s.authorizationInput.password === 'synthetic-temporary-password', 'refresh keeps the current same-account password in memory only')
await s.probe(); check(h.calls.length === 1 && s.error.value.includes('4 位'), 'missing CAPTCHA cannot submit password login')
s.authorizationInput.code = '1234'; await s.probe()
const loginRequest = h.calls.find(call => call.action === 'probe').args[1]
check(loginRequest.credentials.username === 'synthetic-user' && !('password' in loginRequest.credentials) && !('access_token' in loginRequest.credentials), 'permanent credentials remain separate from the one-time answer')
check(loginRequest.authorization.password === 'synthetic-temporary-password' && loginRequest.authorization.code === '1234', 'explicit CAPTCHA submit carries this one-time password and code')
check(s.step.value === 2 && s.probeToken.value === 'synthetic-proof', 'successful authorization reaches the existing verified preview')
check(!Object.keys(s.authorizationInput).length && !s.challengeToken.value && !s.captchaImage.value, 'success immediately clears password, CAPTCHA and challenge state')
s.confirmed.value = true; await s.activate()
check(h.calls.find(call => call.action === 'activate').args[1].probe_token === 'synthetic-proof' && s.step.value === 3, 'activation reuses the existing bound proof, never resends a password')
h.dispose()

h = harness(); await settle(); s = h.state; fill(s)
h.control.probe = async () => ({ data: { authorization_required: true, status: 'choose_company', challenge_token: 'synthetic-company-step', expires_at: Math.floor(Date.now() / 1000) + 120, companies: [{ id: 42, name: '合成甲公司' }, { id: 43, name: '合成乙公司' }] } })
await s.refreshChallenge(); s.authorizationInput.code = '1234'; await s.probe()
check(s.step.value === 1 && s.companies.value.length === 2 && !s.probeToken.value, 'company response stays in authorization without inventing a verified preview')
check(!Object.keys(s.authorizationInput).length && s.challengeToken.value === 'synthetic-company-step', 'company selection rotates challenge and clears temporary password/code')
const beforeInvalidCompany = h.calls.length; s.selectedCompany.value = 'outside-list'; await s.probe()
check(h.calls.length === beforeInvalidCompany && s.error.value.includes('本次授权'), 'arbitrary company IDs are rejected before network submission')
h.control.probe = async () => proof(); s.selectedCompany.value = '43'; await s.probe()
const companyAnswer = h.calls.at(-1).args[1].authorization
check(Object.keys(companyAnswer).sort().join(',') === 'challenge_token,company_id' && companyAnswer.company_id === '43', 'company followup sends only the selected server candidate and rotated challenge')
check(s.step.value === 2 && !s.companies.value.length, 'verified company selection returns to the normal proof flow')
s.selectedStore.value = 'new-scope'; await s.reprobeStore()
check(s.step.value === 1 && s.scope.store_id === 'new-scope' && !s.challengeToken.value && s.error.value.includes('重新完成登录'), 'changing interactive source scope asks for a fresh login instead of replaying a consumed challenge')
h.dispose()

h = harness(); await settle(); s = h.state; fill(s)
const first = deferred(), second = deferred(); let number = 0
h.control.challenge = () => (++number === 1 ? first.promise : second.promise)
const firstRequest = s.refreshChallenge(), secondRequest = s.refreshChallenge()
second.resolve(challenge('synthetic-new')); await secondRequest
first.resolve(challenge('synthetic-old')); await firstRequest
check(s.challengeToken.value === 'synthetic-new' && !s.challengeLoading.value, 'late old refresh cannot overwrite a newer image challenge')
check(s.authorizationInput.code === undefined, 'refresh discards any answer for the previous image')
const third = deferred(); h.control.challenge = () => third.promise
const accountRequest = s.refreshChallenge(); s.credentials.username = 'synthetic-other-user'
third.resolve(challenge('synthetic-old-account')); await accountRequest
check(!s.challengeToken.value && !s.captchaImage.value && !Object.keys(s.authorizationInput).length, 'identity change synchronously invalidates in-flight challenge and password')
h.dispose()

h = harness(); await settle(); s = h.state; fill(s); await s.refreshChallenge(); s.authorizationInput.code = '1234'
const identityProbe = deferred(); h.control.probe = () => identityProbe.promise
const identityPending = s.probe(); s.credentials.username = 'synthetic-replacement'
identityProbe.resolve(proof()); await identityPending
check(!s.probeToken.value && s.step.value === 1 && !s.busy.value && !Object.keys(s.authorizationInput).length, 'changing identity also rejects an already submitted old-account authorization result')
h.dispose()

h = harness(); await settle(); s = h.state; fill(s); await s.refreshChallenge(); s.authorizationInput.code = '1234'
const pendingProbe = deferred(); h.control.probe = () => pendingProbe.promise
const probeRequest = s.probe(); h.props.merchantId = 2; await settle()
pendingProbe.resolve(proof()); await probeRequest
check(s.step.value === 0 && !s.probeToken.value && !Object.keys(s.authorizationInput).length, 'a different merchant cannot receive an older in-flight proof or password')
fill(s); const pendingClose = deferred(); h.control.challenge = () => pendingClose.promise
const closingRequest = s.refreshChallenge(); s.visible.value = false; pendingClose.resolve(challenge('synthetic-after-close')); await closingRequest
check(!s.challengeToken.value && !s.captchaImage.value && !Object.keys(s.authorizationInput).length && !h.props.modelValue, 'closing clears secrets immediately and ignores later HTTP results')
h.dispose()

h = harness(); await settle(); s = h.state; fill(s); await s.refreshChallenge(); s.authorizationInput.code = '1234'
h.control.probe = async () => { throw { msg: '合成验证码错误' } }
await s.probe()
check(s.error.value === '合成验证码错误' && !s.challengeToken.value && !Object.keys(s.authorizationInput).length, 'failed login consumes UI challenge and clears temporary credentials without automatic retry')
const failedCallCount = h.calls.length; await s.probe()
check(h.calls.length === failedCallCount && s.error.value.includes('获取有效验证码'), 'failed challenge cannot be silently replayed')
fill(s); await s.refreshChallenge(); s.challengeExpires.value = 1; s.authorizationInput.code = '1234'; await s.probe()
check(!s.challengeToken.value && s.error.value.includes('获取有效验证码'), 'expired challenge cannot submit credentials')
h.dispose()

h = harness(); await settle(); s = h.state; fill(s)
h.control.challenge = async () => ({ data: { ...challenge().data, captcha_image: 'https://outside.example/captcha?secret=not-allowed' } })
await s.refreshChallenge(); check(!s.captchaImage.value && s.error.value.includes('图片格式'), 'image URLs cannot leak authentication or trigger third-party image requests')
h.control.challenge = async () => ({ data: { ...challenge().data, captcha_image: 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=' } })
await s.refreshChallenge(); check(!s.captchaImage.value && s.error.value.includes('图片格式'), 'executable SVG CAPTCHA responses are not embedded')
h.control.challenge = async () => ({ data: { ...challenge().data, expires_at: Math.floor(Date.now() / 1000) - 1 } })
await s.refreshChallenge(); check(!s.challengeToken.value && s.error.value.includes('返回不完整'), 'expired server challenge response is rejected')
h.dispose()

h = harness(); await settle(); s = h.state; fill(s); await s.refreshChallenge()
s.authMode.value = 'token'
check(!s.interactiveMode.value && !s.challengeToken.value && !Object.keys(s.authorizationInput).length, 'advanced Token mode clears all interactive state')
s.credentials.access_token = 'synthetic-existing-token'; await s.probe()
check(!h.calls.at(-1).args[1].authorization && h.calls.at(-1).args[1].credentials.access_token === 'synthetic-existing-token', 'manual Token authorization retains its original contract')
s.authMode.value = 'interactive'
check(!s.credentials.access_token, 'changing authorization mode discards manually entered permanent secrets rather than retaining a hidden Token')
s.choose('fixture-password'); s.credentials.username = 'synthetic-password-user'; s.credentials.password = 'synthetic-existing-password'; await s.probe()
check(!s.supportsInteractive.value && !h.calls.at(-1).args[1].authorization && h.calls.at(-1).args[1].credentials.password === 'synthetic-existing-password', 'original password adapters stay unchanged and never receive a CAPTCHA branch')
s.credentials.username = 'synthetic-new-password-user'
check(!s.credentials.password, 'editing the account never silently carries a prior account password to the new identity')
h.dispose()

// Restoring the old source range is useful only while the permanent account identity stays the same.
h = harness({ connected: true, provider: 'fixture-token', credentials: { username: 'synthetic-account-A' }, configured_fields: ['username', 'access_token'], scope: { tenant_id: '42', store_id: '42' } }); await settle(); s = h.state
check(s.credentials.username === 'synthetic-account-A' && s.scope.store_id === '42' && s.scope.tenant_id === '42', 'same-account editing restores its existing verified source range')
s.authMode.value = 'token'
check(s.scope.store_id === '42' && s.scope.tenant_id === '42', 'switching authorization method is not mistaken for changing the source account')
s.scope.store_id = 'manually-selected-scope'; s.authMode.value = 'interactive'
check(s.scope.store_id === 'manually-selected-scope', 'same-account method changes preserve a normally edited source range')
s.scope.store_id = '42'; s.credentials.username = 'synthetic-account-B'
check(!Object.keys(s.scope).length, 'switching account clears the old restored tenant and store rather than binding the new company to the old account range')
s.step.value = 1; s.authorizationInput.password = 'synthetic-other-password'; await s.refreshChallenge(); s.authorizationInput.code = '1234'; await s.probe()
const replacementRequest = h.calls.find(call => call.action === 'probe').args[1]
check(replacementRequest.credentials.username === 'synthetic-account-B' && !Object.keys(replacementRequest.scope).length, 'new-account probe payload cannot silently send the prior company 42')
h.dispose()

// Current SFC rejects malformed company replies instead of testing the retired demo server.
for (const companies of [[{ id: '42', name: '合成公司' }, { id: 42, name: '重复公司' }], [{ id: '', name: '空标识' }], [{ id: 42, name: '' }]]) {
    h = harness(); await settle(); s = h.state; fill(s); await s.refreshChallenge(); s.authorizationInput.code = '1234'
    h.control.probe = async () => ({ data: { authorization_required: true, status: 'choose_company', challenge_token: 'synthetic-invalid-company', expires_at: Math.floor(Date.now() / 1000) + 120, companies } })
    await s.probe()
    check(s.step.value === 1 && !s.probeToken.value && !s.companies.value.length && !!s.error.value, 'ambiguous or incomplete server company lists cannot become a verified preview')
    check(!Object.keys(s.authorizationInput).length && !s.challengeToken.value, 'malformed company response clears the consumed one-time credentials')
    h.dispose()
}
const transport = createAdminApiSourceFixture(root, ts), endpoints = transport.api
await endpoints.getProviders()
check(transport.calls.at(-1).method === 'get' && transport.calls.at(-1).args[0] === 'hsx_lead/providers', 'provider metadata comes from the current enabled-source endpoint')
const identity = { provider: schemas[0].key, credentials: { username: 'synthetic-api-account' }, scope: {} }
await endpoints.getAuthorizationChallenge(17, identity)
check(transport.calls.at(-1).method === 'post' && transport.calls.at(-1).args[0] === 'hsx_lead/merchants/17/connection/auth/challenge' && !('authorization' in transport.calls.at(-1).args[1]), 'challenge wrapper sends only explicit merchant identity, not an answer or permanent token')
await endpoints.probeConnection(17, { ...identity, authorization: { challenge_token: 'synthetic-api-challenge', password: 'synthetic-password', code: '1234' } })
check(transport.calls.at(-1).args[0] === 'hsx_lead/merchants/17/connection/probe' && transport.calls.at(-1).args[1].authorization.challenge_token === 'synthetic-api-challenge', 'the current verification endpoint carries the short-lived challenge separately from credentials')
await endpoints.activateConnection(17, { probe_token: 'synthetic-api-proof', confirm_switch: true })
check(transport.calls.at(-1).args[0] === 'hsx_lead/merchants/17/connection/activate' && !JSON.stringify(transport.calls.at(-1).args[1]).includes('password'), 'explicit activation forwards proof only and cannot replay the transient login answer')
console.log(`PASS ${checks} interactive-auth UI checks; real Vue setup and synthetic APIs only, no live login/ERP/database or visual verification`)
