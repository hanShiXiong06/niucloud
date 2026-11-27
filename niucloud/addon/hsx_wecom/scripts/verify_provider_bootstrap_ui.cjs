#!/usr/bin/env node
// Offline regression: no browser, network, database, production writes, or secret output.
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const { createRequire } = require('node:module')
const root = path.resolve(__dirname, '../../../../')
const adminRequire = createRequire(path.join(root, 'admin/package.json'))
const ts = adminRequire('typescript')
const vue = adminRequire('vue')
const { parse, compileScript, compileTemplate } = adminRequire('@vue/compiler-sfc')
const canonical = path.join(root, 'niucloud/addon/hsx_wecom/admin')
const mirror = path.join(root, 'admin/src/addon/hsx_wecom')
let assertions = 0
const check = (condition, message) => { assert.ok(condition, message); assertions += 1 }
const equal = (actual, expected, message) => { assert.deepEqual(actual, expected, message); assertions += 1 }
function evaluate(source, requireModule) {
    const output = ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS } })
    const exports = {}
    vm.runInNewContext(output.outputText, { exports, require: requireModule, console, URL, Date }, { timeout: 3000 })
    return exports
}
const helpers = evaluate(fs.readFileSync(path.join(canonical, 'utils/binding-session.ts'), 'utf8'), adminRequire)
const prepared = {
    id: 9, enabled: 0, status: 'preparing', setup_stage: 'preparing', callback_ready: true,
    channel_code: 'test_channel', provider_corp_id: 'provider-test-id', web_base_url: 'https://example.test',
    callback_token: '******', encoding_aes_key: '******', callback_token_configured: 1, encoding_aes_key_configured: 1,
    suite_id: '', suite_secret: '', suite_secret_configured: 0, admin_miniapp_appid: '', admin_miniapp_name: '',
    installation_callback_domain: 'example.test', data_callback_url: 'https://example.test/api/wecom/provider/data/test_channel',
    event_callback_url: 'https://example.test/api/wecom/provider/event/test_channel',
    auth_callback_url: 'https://example.test/api/wecom/provider/authorize/complete/test_channel',
    application_settings_url: 'https://example.test/admin/site/hsx_wecom/config'
}
const enabled = { ...prepared, enabled: 1, status: 'enabled', setup_stage: 'enabled', suite_id: 'suite-test-id', suite_secret: '******', suite_secret_configured: 1, admin_miniapp_appid: 'wx1234567890abcdef', admin_miniapp_name: '测试管理端' }
check(helpers.hasSavedProviderCallbackPreparation(prepared), 'saved preparing response is callback-ready')
check(!helpers.hasSavedProviderConfiguration(prepared), 'preparation is not formal configuration')
check(!helpers.hasSavedProviderCallbackPreparation({ ...prepared, id: 0 }), 'draft has no saved callback readiness')
check(!helpers.hasSavedProviderCallbackPreparation({ ...prepared, callback_ready: false }), 'server readiness is mandatory')
check(!helpers.hasSavedProviderCallbackPreparation({ ...prepared, encoding_aes_key_configured: 0 }), 'saved key must exist')
check(helpers.canPrepareProviderConfiguration(prepared), 'preparation can be updated')
check(!helpers.canPrepareProviderConfiguration(enabled), 'enabled suite cannot be downgraded')
check(!helpers.canPrepareProviderConfiguration({ ...enabled, enabled: 0, status: 'disabled' }), 'disabled suite cannot be downgraded')
check(!helpers.canPrepareProviderConfiguration({ suite_secret_configured: 1 }), 'stored SuiteSecret prevents preparation')
check(!helpers.canPrepareProviderConfiguration({ suite_id: 'suite-test-id' }), 'stored SuiteID prevents preparation')
check(helpers.hasSavedProviderConfiguration(enabled), 'formal enabled credentials are configured')
check(!helpers.hasSavedProviderConfiguration({ ...enabled, status: 'preparing' }), 'preparing never passes formal state guard')

const sources = {}
for (const relative of ['utils/binding-session.ts', 'views/config/index.vue', 'views/config/components/ConnectionGuide.vue']) {
    const source = fs.readFileSync(path.join(canonical, relative), 'utf8')
    equal(fs.readFileSync(path.join(mirror, relative), 'utf8'), source, `mirror matches ${relative}`)
    if (!relative.endsWith('.vue')) continue
    const { descriptor, errors } = parse(source, { filename: relative })
    equal(errors.length, 0, `${relative} parses`)
    const script = compileScript(descriptor, { id: 'provider-bootstrap-test' })
    const template = compileTemplate({ source: descriptor.template.content, filename: relative, id: 'provider-bootstrap-test', compilerOptions: { bindingMetadata: script.bindings } })
    equal(template.errors.length, 0, `${relative} template compiles`)
    sources[relative] = { source, script: script.content }
}
const page = sources['views/config/index.vue']
check(page.source.includes('savedProviderConfig[field.key]'), 'callback fields render saved response values')
check(page.source.includes('!providerCallbackReady || providerBaseChanged'), 'unsaved callback edits prevent copying')
check(page.source.includes('普通 SaaS 站点登录') && page.source.includes('不是企业微信管理员免登'), 'settings entry does not claim admin SSO')
check(page.source.includes('不接入聊天、会话存档或业务消息处理'), 'data callback scope is explicit')
let response = prepared
let failRead = false
const saves = []
const messages = []
const api = new Proxy({}, { get: (_target, key) => async (payload) => {
    if (key === 'getWecomProviderConfig' && failRead) throw new Error('simulated offline read failure')
    if (key === 'saveWecomProviderConfig') saves.push(payload)
    return { data: response }
} })
const component = evaluate(page.script, id => {
    if (id === 'vue') return { ...vue, onMounted() {} }
    if (id === 'vue-router') return { useRoute: () => ({ query: {} }), useRouter: () => ({ replace: async () => {} }) }
    if (id === 'element-plus') return { ElMessage: { success: value => messages.push(value), warning: value => messages.push(value), error: value => messages.push(value) } }
    if (id === '@element-plus/icons-vue') return new Proxy({}, { get: () => ({}) })
    if (id === '../../api') return api
    if (id === '../../utils/binding-session') return helpers
    if (id.endsWith('.vue')) return { default: {} }
    throw new Error(`Unexpected import ${id}`)
}).default
async function main() {
    const state = component.setup({}, { expose() {} })
    state.config.is_platform = 1
    await state.saveProviderPreparation()
    await state.saveProviderConfig()
    equal(saves.length, 0, 'initial unread configuration cannot be overwritten')
    failRead = true
    await state.loadProviderConfig()
    check(!state.providerLoaded.value && Boolean(state.providerLoadError.value), 'initial read failure is visible and blocks saves')
    failRead = false
    state.applyProviderConfig({ id: 0, enabled: 0, suite_id: '', suite_secret_configured: 0 })
    let fullValidations = 0
    let checkedFields = []
    state.providerFormRef.value = {
        validateField: async fields => { checkedFields = Array.from(fields); return true },
        validate: async () => { fullValidations += 1; return true }
    }
    Object.assign(state.providerConfig, { channel_code: 'test_channel', provider_corp_id: 'provider-test-id', web_base_url: 'https://example.test', callback_token: 'callback-test-token', encoding_aes_key: 'a'.repeat(43) })
    check(!state.providerCallbackReady.value, 'filled unsaved form is not callback-ready')
    await state.saveProviderPreparation()
    equal(checkedFields, ['channel_code', 'provider_corp_id', 'callback_token', 'encoding_aes_key', 'web_base_url'], 'prepare validates only five base fields')
    equal(fullValidations, 0, 'preparation does not validate Suite or miniapp fields')
    equal(saves.length, 1, 'preparation submits exactly once')
    equal(saves[0].prepare_only, 1, 'preparation is explicit')
    equal(saves[0].enabled, 0, 'preparation never enables channel')
    check(!('suite_id' in saves[0]) && !('suite_secret' in saves[0]) && !('admin_miniapp_appid' in saves[0]), 'preparation excludes formal fields')
    check(state.providerPreparing.value && state.providerCallbackReady.value, 'saved preparation state is displayed')
    check(!state.providerConfigured.value, 'saved preparation cannot test formal credentials')
    equal(state.pageStatus.value.label, '回调准备已保存 · 未启用', 'preparation label is honest')
    check(!state.providerBaseChanged.value, 'saved masked base values are clean')
    state.providerConfig.web_base_url = 'https://draft.test'
    check(state.providerBaseChanged.value, 'base edits are marked unsaved')
    equal(state.savedProviderConfig.value.application_settings_url, prepared.application_settings_url, 'draft domain cannot replace saved address')

    state.applyProviderConfig({ ...enabled, enabled: 0, status: 'disabled' })
    check(!state.canPrepareProvider.value, 'disabled existing Suite hides preparation action')
    const beforeBlocked = saves.length
    await state.saveProviderPreparation()
    equal(saves.length, beforeBlocked, 'programmatic preparation cannot downgrade an existing Suite')
    response = { ...enabled, enabled: 0, status: 'disabled' }
    await state.saveProviderConfig()
    equal(saves.at(-1).enabled, 0, 'existing disabled Suite stays disabled on formal save')
    equal(saves.at(-1).prepare_only, 0, 'existing Suite uses formal save')

    state.applyProviderConfig(prepared)
    response = enabled
    await state.saveProviderConfig()
    equal(fullValidations, 1, 'new formal enable validates entire form')
    equal(saves.at(-1).enabled, 1, 'second step formally enables channel')
    check(state.providerConfigured.value, 'full saved enable allows credential test')

    failRead = true
    await state.saveProviderConfig()
    check(state.providerLoadError.value.includes('保存已成功') && state.providerLoadError.value.includes('重新读取失败'), 'successful save and failed reload are distinguished')
    check(!state.providerLoaded.value, 'failed post-save reload requires retry before another write')
    failRead = false

    state.applyProviderConfig(prepared)
    state.providerFormRef.value.validate = async () => false
    const beforeInvalid = saves.length
    await state.saveProviderConfig()
    equal(saves.length, beforeInvalid, 'failed full validation cannot enable channel')
    state.providerFormRef.value.validateField = async () => false
    await state.saveProviderPreparation()
    equal(saves.length, beforeInvalid, 'failed base validation cannot save preparation')
    console.log(`PASS provider bootstrap UI: ${assertions} assertions; Vue compile, saved-state guards, scoped preparation, formal save, and mirrors`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
