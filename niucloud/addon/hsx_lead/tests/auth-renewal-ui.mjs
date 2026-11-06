import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

// Real plugin SFC setup/render with public synthetic capabilities, no API or database access.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const require = createRequire(path.join(root, 'admin/package.json'))
const Vue = require('vue'), compiler = require('@vue/compiler-sfc'), ts = require('typescript')
const { compile } = require('@vue/compiler-dom'), { renderToString } = require('@vue/server-renderer')
let checks = 0
const check = (condition, label) => { assert.ok(condition, label); checks++ }
function moduleOf(source, dependency = () => { throw Error('Unexpected dependency') }) {
    const js = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const module = { exports: {} }
    vm.runInNewContext('(function(require,module,exports){' + js + '})', { Date, Number, String, Math, Set, Map, JSON, console })(dependency, module, module.exports)
    return module.exports
}
const directory = path.join(root, 'niucloud/addon/hsx_lead/admin')
const helperFile = path.join(directory, 'composables/authRenewal.ts')
const helper = moduleOf(fs.readFileSync(helperFile, 'utf8'))
check(helper.authRenewalLabel({ automatic: true, ready: true, status: 'automatic' }) === '自动续登已配置', 'only configured automatic mode gets a positive label')
check(helper.authRenewalLabel({ automatic: true, ready: false, status: 'credentials_required' }) === '账号密码待补齐', 'missing credentials do not look enabled')
check(helper.authRenewalLabel({ status: 'manual_token', ready: false }).includes('不能续登'), 'manual token is explicitly not renewable')
check(helper.authRenewalLabel({ status: 'interactive_required', ready: false }) === '到期需登录验证', 'interactive verification is not presented as background renewal')
check(helper.authRenewalLabel({ status: 'credential_error', ready: false }) === '授权需重新配置', 'unreadable ciphertext has an actionable label')
check(helper.authRenewalLabel({}).includes('待确认'), 'missing metadata never promises renewal')

const unexpected = async () => { throw Error('No real or mocked writes are permitted in this UI test') }
const automatic = { active: Vue.ref(false), status: Vue.ref('idle'), working: Vue.ref(false), stage: Vue.ref(''), message: Vue.ref(''), stop() {}, start: unexpected, resume: unexpected }
const rows = Vue.ref([])
function setup(name) {
    const file = path.join(directory, 'views/components', name + '.vue'), source = fs.readFileSync(file, 'utf8')
    const { descriptor, errors } = compiler.parse(source, { filename: file })
    check(!errors.length, name + ' SFC parses')
    const script = compiler.compileScript(descriptor, { id: name })
    const template = compiler.compileTemplate({ source: descriptor.template.content, filename: file, id: name, compilerOptions: { bindingMetadata: script.bindings, expressionPlugins: ['typescript'] } })
    check(!template.errors.length, name + ' template compiles')
    const shared = { errorText: e => String(e?.message || e), time: String, readyLabels: {}, region: () => '合成地区', syncLabel: String,
        useLeadList: () => ({ rows, total: Vue.ref(0), loading: Vue.ref(false), error: Vue.ref(''), query: Vue.reactive({}), load: unexpected, search: unexpected }) }
    const dependencies = { vue: { ...Vue, onMounted() {}, onBeforeUnmount() {} }, 'element-plus': { ElMessage: {}, ElMessageBox: {} }, '@element-plus/icons-vue': { Plus: {} }, '../../api': {},
        '../../composables/useLeadUi': shared, '../../composables/authRenewal': helper,
        '../../composables/cooperation': { coopBusiness:value=>String(value) },
        '../../composables/useAutomaticAuthorization': { useAutomaticAuthorization: () => automatic },
        '../../composables/filtering': { hasListFilters: () => false }, '../../composables/serviceScope': { serviceScopeFor: () => 'default' } }
    const module = moduleOf(script.content, key => key.endsWith('.vue') ? { default: {} } : key in dependencies ? dependencies[key] : (() => { throw Error('Unexpected dependency ' + key) })())
    const props = { modelValue: false, merchantId: 7, merchantName: '合成商户', initialFilter: {} }
    const state = module.default.setup(props, { expose() {}, emit() {} })
    return { state, source, template: descriptor.template.content }
}
const Box = Vue.defineComponent({ setup(_props, { slots }) { return () => Vue.h('section', [slots.reference?.(), slots.default?.(), slots.footer?.()]) } })
const Alert = Vue.defineComponent({ props: ['title', 'description'], setup: props => () => Vue.h('aside', [props.title, props.description]) })
const Table = Vue.defineComponent({ props: ['data'], setup: (props, { slots }) => () => Vue.h('table', (props.data || []).flatMap(row => slots.connection?.({ row }) || [])) })
async function render(view) {
    const fn = new Function('Vue', compile(view.template, { mode: 'function', prefixIdentifiers: true }).code)(Vue)
    const app = Vue.createSSRApp({ setup: () => ({ ...view.state, merchantName: '合成商户' }), render: fn })
    for (const tag of ['HsxDrawer', 'HsxPagination', 'ConnectionWizard', 'SyncProgressDrawer', 'LeadSectionHeader', 'LeadFilterBar', 'LeadTimestamp', 'RegionSelect', 'CaptchaSettings', 'BusinessCapabilities',
        'el-steps', 'el-step', 'el-radio-group', 'el-radio-button', 'el-radio', 'el-form', 'el-form-item', 'el-input', 'el-collapse', 'el-collapse-item', 'el-button', 'el-result', 'el-switch', 'el-input-number', 'el-empty', 'el-tooltip', 'el-dropdown', 'el-dropdown-menu', 'el-dropdown-item', 'el-tag', 'el-option', 'el-select', 'el-descriptions', 'el-descriptions-item', 'el-table-column', 'el-table', 'el-checkbox']) app.component(tag, Box)
    app.component('el-alert', Alert); app.component('HsxTable', Table)
    app.config.warnHandler = () => {}
    return renderToString(app)
}
const wizard = setup('ConnectionWizard'), state = wizard.state
state.providers.value = [{ key: 'synthetic', name: '合成 ERP', auth_mode: 'password', renewal: { automatic: true, mode: 'password_on_expiry' }, fields: [], scope_fields: [],
    auth_modes: [{ key: 'password', label: '账号密码登录', fields: [], renewal: { automatic: true, mode: 'password_on_expiry' } }, { key: 'token', label: '临时授权', fields: [], renewal: { automatic: false, mode: 'manual_token' } }] }]
state.provider.value = 'synthetic'; state.authMode.value = 'password'; state.step.value = 1
let html = await render(wizard)
check(html.includes('支持自动续登') && html.includes('验证并启用后'), 'password onboarding describes future activation, not already configured renewal')
check(html.includes('账号密码将加密保存') && !html.includes('仅 Token 不能自动续登'), 'password mode explains encrypted storage and renewal')
state.authMode.value = 'token'; html = await render(wizard)
check(html.includes('仅 Token 不能自动续登') && !html.includes('支持自动续登'), 'switching the same provider to token changes the actual rendered capability')
state.providers.value = [{ key: 'interactive', name: '合成验证码 ERP', auth_mode: 'interactive', fields: [], scope_fields: [], interactive_auth: { type: 'image_captcha', fields: [] }, renewal: { automatic: false, mode: 'interactive_login' } }]
state.provider.value = 'interactive'; state.authMode.value = 'interactive'; html = await render(wizard)
check(html.includes('会话到期后需重新验证') && html.includes('不等于后台自动续登'), 'captcha login is never mistaken for unattended background renewal')
state.authMode.value = 'token'; html = await render(wizard)
check(html.includes('仅 Token 不能自动续登'), 'interactive provider existing-token mode remains explicit')
state.step.value = 3; state.activatedRenewal.value = { automatic: false, ready: false, status: 'credentials_required', message: '合成：尚未保存登录密码' }; html = await render(wizard)
check(html.includes('账号密码待补齐') && html.includes('尚未保存登录密码'), 'final enabled step uses authoritative backend renewal result')
check(!html.includes('自动续登已配置'), 'enabling sync alone cannot claim automatic renewal')
state.activatedRenewal.value = { automatic: true, ready: true, status: 'automatic', message: '合成：可在失效时重新登录' }; html = await render(wizard)
check(html.includes('自动续登已配置') && html.includes('可在失效时重新登录'), 'final configured step renders renewal readiness separately from connection activation')

const merchant = setup('MerchantPanel')
rows.value = [{ id: 7, name: '合成商户', provider: 'synthetic', connection_id: 3, last_sync_status: 'completed', auth_renewal: { automatic: false, ready: false, status: 'manual_token', message: '合成 Token 无法自动续登' } }]
html = await render(merchant)
check(html.includes('仅 Token，不能续登') && !html.includes('自动续登已配置'), 'merchant sync column renders a concise warning even after successful synchronization')
rows.value[0].auth_renewal = { automatic: true, ready: true, status: 'automatic', message: '合成自动续登已配置' }; html = await render(merchant)
check(html.includes('自动续登已配置'), 'merchant positive label follows authoritative readiness')
merchant.state.settingsRenewal.value = { automatic: false, ready: false, status: 'interactive_required', message: '合成：过期需要登录验证' }
html = await render(merchant)
check(html.includes('到期需登录验证') && html.includes('过期需要登录验证'), 'sync settings explains renewal limitations instead of promising every enabled schedule can log in')
check(!/soujike|wanji|aiguanji|jixiaoer/.test(fs.readFileSync(helperFile, 'utf8')), 'presentation helper contains no provider-name conditionals')
console.log(`PASS ${checks} auth renewal UI checks; actual SFC setup/render, synthetic public capabilities only, no HTTP/database access.`)
