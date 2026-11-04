import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

// Execute the real Vue setup with synthetic metadata/promises. No browser or ERP traffic.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const require = createRequire(path.join(root, 'admin/package.json'))
const Vue = require('vue'), compiler = require('@vue/compiler-sfc'), ts = require('typescript')
const sourcePath = path.join(root, 'niucloud/addon/hsx_lead/admin/views/components/ConnectionWizard.vue')
const source = fs.readFileSync(sourcePath, 'utf8')
const transpile = text => ts.transpileModule(text, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const js = transpile(compiler.compileScript(compiler.parse(source, { filename: sourcePath }).descriptor, { id: 'auth-modes-test', inlineTemplate: false }).content)
const automaticJs = transpile(fs.readFileSync(path.join(root, 'niucloud/addon/hsx_lead/admin/composables/useAutomaticAuthorization.ts'), 'utf8'))
const renewalJs = transpile(fs.readFileSync(path.join(root, 'niucloud/addon/hsx_lead/admin/composables/authRenewal.ts'), 'utf8'))
let checks = 0
const check = (condition, label) => { assert.ok(condition, label); checks++ }
async function settle() { for (let index = 0; index < 6; index++) { await Promise.resolve(); await Vue.nextTick() } }
const deferred = () => { let resolve; const promise = new Promise(ok => { resolve = ok }); return { promise, resolve } }
const passwordFields = [{ key: 'username', label: '登录手机号', required: true, identity: true }, { key: 'password', label: '登录密码', required: true, secret: true }]
const tokenFields = [{ key: 'access_token', label: '授权 Token', required: true, secret: true, identity: true }]
const schema = { key: 'fixture-multi-mode', name: '合成多方式 ERP', auth_mode: 'password', fields: passwordFields,
    scope_fields: [{ key: 'store_id', label: '来源门店 ID', required: false }],
    auth_modes: [{ key: 'password', label: '账号密码登录', fields: passwordFields, scope_fields: [{ key: 'store_id', label: '来源门店 ID', required: false }], notes: ['合成密码登录说明'] },
        { key: 'token', label: '已有授权 Token（临时测试）', fields: tokenFields, scope_fields: [{ key: 'store_id', label: '来源门店 ID', required: true }], notes: ['合成临时测试说明'] }] }
const singleSchema = { key: 'fixture-single-mode', name: '合成普通 ERP', fields: passwordFields, scope_fields: [] }
const proof = () => ({ data: { probe_token: 'synthetic-proof', expires_at: Math.floor(Date.now() / 1000) + 600,
    preview: { identity: { store_id: '42' }, stores: [{ store_id: '42', name: '合成门店' }], first_page_count: 0, empty_inventory: true, can_activate: true }, impact: {} } })
function harness(connection = { connected: false, credentials: {}, scope: {} }) {
    const props = Vue.reactive({ modelValue: true, merchantId: 1, merchantName: '合成商户' }), calls = [], lifecycle = []
    const control = { probe: async () => proof() }
    const api = { getProviders: async () => ({ data: { items: [schema, singleSchema] } }), getConnection: async () => ({ data: connection }),
        probeConnection: (...args) => { calls.push({ action: 'probe', args: structuredClone(args) }); return control.probe(...args) },
        activateConnection: async (...args) => { calls.push({ action: 'activate', args: structuredClone(args) }); return { data: {} } },
        getAuthorizationChallenge: async () => { throw new Error('No image challenge belongs to ordinary auth modes') },
        saveSyncSettings: async () => ({ data: {} }) }
    const load = text => { const module = { exports: {} }; vm.runInNewContext(`(function(require,module,exports){${text}\n})`, { Date, setTimeout, clearTimeout })(localRequire, module, module.exports); return module.exports }
    const localRequire = name => name === 'vue' ? { ...Vue, onBeforeUnmount: callback => lifecycle.push(callback) } : name === '../../api' ? api
        : name.includes('useLeadUi') ? { time: value => String(value), errorText: (error, fallback) => error?.msg || error?.message || fallback }
            : name.includes('useAutomaticAuthorization') ? load(automaticJs)
                : name.includes('authRenewal') ? load(renewalJs)
                : name.includes('HsxDrawer') || name.includes('CaptchaSettings.vue') ? { default: {} } : (() => { throw Error(name) })()
    const state = load(js).default.setup(props, { expose() {}, emit(name, value) { if (name === 'update:modelValue') props.modelValue = value } })
    return { state, props, calls, control, dispose() { lifecycle.forEach(callback => callback()) } }
}

check(!/wanji|soujike|jixiaoer|aiguanji/.test(source), 'auth options are generic metadata rather than ERP-name branches')
check(source.includes('authModes.length > 1') && source.includes('{{ mode.label }}'), 'selector labels come from the provider contract')
check(!/localStorage|sessionStorage|console\.(log|debug)/.test(source), 'no browser persistence or logging of credentials')
let h = harness(); await settle(); let s = h.state; s.step.value = 1
check(s.authMode.value === 'password' && !s.supportsInteractive.value && !s.interactiveMode.value, 'new multi-mode connection defaults to ordinary password login without CAPTCHA')
check(s.activeFields.value.map(field => field.key).join(',') === 'username,password', 'password mode shows only account and password')
check(s.activeScopeFields.value[0].required === false, 'password login may infer source store without mandatory manual entry')
await s.probe(); check(h.calls.length === 0 && s.error.value.includes('登录手机号'), 'missing account is rejected before API')
s.credentials.username = '18800000001'; await s.probe()
check(h.calls.length === 0 && s.error.value.includes('登录密码'), 'missing new password is rejected before API')
s.credentials.password = ' synthetic-password-with-spaces '; s.credentials.access_token = 'hidden-test-token'
await s.probe()
const passwordRequest = h.calls.at(-1).args[1]
check(passwordRequest.credentials.auth_mode === 'password' && passwordRequest.credentials.username === '18800000001', 'request carries explicit password mode and account')
check(passwordRequest.credentials.password === ' synthetic-password-with-spaces ' && !('access_token' in passwordRequest.credentials), 'password whitespace is opaque and hidden Token never enters request')
check(!passwordRequest.authorization && s.step.value === 2 && s.probeToken.value === 'synthetic-proof', 'ordinary password login reaches normal proof flow without CAPTCHA payload')
check(!h.calls.some(call => call.action === 'activate'), 'successful preview does not automatically replace the connection')
s.authMode.value = 'token'
check(!s.probeToken.value && !s.confirmed.value && !s.busy.value, 'changing auth mode invalidates prior proof and busy state')
check(!Object.keys(s.credentials).length && !Object.keys(s.scope).length, 'mode switch removes every hidden credential and previous store scope')
check(s.activeFields.value.map(field => field.key).join(',') === 'access_token' && s.activeScopeFields.value[0].required === true, 'test Token mode requests Token plus explicit store')
s.step.value = 1; await s.probe()
check(h.calls.length === 1 && s.error.value.includes('授权 Token'), 'new Token cannot reuse the old password configuration')
s.credentials.access_token = 'synthetic-manual-token'; await s.probe()
check(h.calls.length === 1 && s.error.value.includes('来源门店'), 'manual Token mode requires a source store')
s.scope.store_id = '42'; await s.probe()
const tokenRequest = h.calls.at(-1).args[1]
check(tokenRequest.credentials.auth_mode === 'token' && tokenRequest.credentials.access_token === 'synthetic-manual-token', 'manual Token test remains available through existing probe endpoint')
check(!('username' in tokenRequest.credentials) && !('password' in tokenRequest.credentials) && tokenRequest.scope.store_id === '42', 'manual Token payload excludes hidden password/account and keeps selected store')
s.authMode.value = 'password'
check(!s.credentials.access_token && !s.scope.store_id && !s.probeToken.value, 'return to password mode does not keep hidden Token or its scope')
h.dispose()

h = harness({ provider: schema.key, connected: true, credentials: { auth_mode: 'token' }, configured_fields: ['access_token'], scope: { store_id: '42' } }); await settle(); s = h.state; s.step.value = 1
check(s.authMode.value === 'token' && s.scope.store_id === '42', 'editing an existing Token connection preserves its explicit mode')
check(s.canReuseSecret(tokenFields[0]), 'unchanged saved Token may stay blank without revealing it')
await s.probe()
check(h.calls.length === 1 && h.calls[0].args[1].credentials.auth_mode === 'token' && !('access_token' in h.calls[0].args[1].credentials), 'saved Token reuse sends only mode, never reads or fabricates its secret')
s.authMode.value = 'password'; s.step.value = 1; s.credentials.username = '18800000002'; await s.probe()
check(h.calls.length === 1 && s.error.value.includes('登录密码'), 'saved Token must not satisfy required password after a mode switch')
s.authMode.value = 'token'
check(s.scope.store_id === '42' && s.credentials.auth_mode === 'token' && !s.credentials.username, 'switching back to the original mode restores only server-public original identity and scope')
s.choose(singleSchema.key); s.credentials.username = 'other-account'; s.credentials.password = 'other-password'; s.choose(schema.key)
check(s.authMode.value === 'token' && !s.credentials.password && !s.credentials.access_token, 'ERP selection returns to saved mode without retaining another provider secret')
h.dispose()

h = harness({ provider: schema.key, connected: true, credentials: { auth_mode: 'password', username: '18800000003' }, configured_fields: ['username', 'password'], scope: { store_id: '42' } }); await settle(); s = h.state; s.step.value = 1
check(s.authMode.value === 'password' && s.canReuseSecret(passwordFields[1]), 'same-account saved password may remain blank')
await s.probe(); check(h.calls.length === 1 && !('password' in h.calls[0].args[1].credentials), 'same identity does not resend saved password into the browser')
s.previous(); s.credentials.username = '18800000004'
check(!s.canReuseSecret(passwordFields[1]) && !s.scope.store_id, 'changing account invalidates secret reuse and previous store scope')
await s.probe(); check(h.calls.length === 1 && s.error.value.includes('登录密码'), 'new account requires a fresh password')
s.authMode.value = 'token'; s.credentials.access_token = 'temporary-test'; s.authMode.value = 'password'
check(s.credentials.username === '18800000003' && s.scope.store_id === '42' && !s.credentials.access_token && s.canReuseSecret(passwordFields[1]), 'return to original password mode restores public identity and same-mode reuse only')
s.authMode.value = 'unknown-mode'; s.credentials.username = '18800000004'; s.credentials.password = 'synthetic-password'; await s.probe()
check(h.calls.length === 1 && s.error.value.includes('可用的授权方式'), 'unsupported mode is rejected before network submission')
h.dispose()

h = harness(); await settle(); s = h.state; s.step.value = 1; s.credentials.username = '18800000005'; s.credentials.password = 'synthetic-password'
const pending = deferred(); h.control.probe = () => pending.promise
const old = s.probe(); check(s.busy.value && h.calls.length === 1, 'probe begins one synthetic in-flight request')
s.authMode.value = 'token'; pending.resolve(proof()); await old
check(s.step.value === 1 && !s.probeToken.value && !s.busy.value, 'late response from a different auth mode cannot become activatable proof')
check(!Object.keys(s.credentials).length && !Object.keys(s.scope).length, 'late response does not restore cleared hidden inputs or scope')
h.dispose()

console.log(`PASS ${checks} generic auth-mode UI checks; real Vue setup, synthetic metadata/promises only. No ERP calls, credentials, database or live acceptance.`)
