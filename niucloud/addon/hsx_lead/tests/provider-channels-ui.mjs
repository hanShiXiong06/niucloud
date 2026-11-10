import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { loadMobileUtility } from './mobile-utils.mjs'
import { profileConfigApi } from './profile-config-fixture.mjs'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..'), require = createRequire(path.join(root, 'admin/package.json'))
const Vue = require('vue'), compiler = require('@vue/compiler-sfc'), ts = require('typescript')
const { compile } = require('@vue/compiler-dom'), { renderToString } = require('@vue/server-renderer')
let checks = 0
const check = (value, label) => { assert.ok(value, label); checks++ }
const clone = value => JSON.parse(JSON.stringify(value))
const parsed = new Map()
function setup(relative, props, dependencies, globals = {}) {
    const file = path.join(root, relative), source = fs.readFileSync(file, 'utf8')
    const { descriptor, errors } = compiler.parse(source, { filename: file })
    check(!errors.length, relative + ' SFC parses')
    const script = compiler.compileScript(descriptor, { id: 'provider-channel' })
    const template = compiler.compileTemplate({ source: descriptor.template.content, filename: file, id: 'provider-channel', compilerOptions: { bindingMetadata: script.bindings, expressionPlugins: ['typescript'] } })
    check(!template.errors.length, relative + ' template compiles')
    const js = ts.transpileModule(script.content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const module = { exports: {} }, emitted = []
    const imports = { vue: { ...Vue, onBeforeUnmount() {} }, ...dependencies }
    vm.runInNewContext('(function(require,module,exports){' + js + '})', { Date, Number, String, JSON, Set, Map, Math, console, ...globals })(name => name.startsWith('../../utils/') ? loadMobileUtility(path.basename(name)) : name.endsWith('.vue') ? { default: {} } : imports[name] || (() => { throw Error('Unexpected dependency ' + name) })(), module, module.exports)
    const state = module.exports.default.setup(props, { emit: (...args) => emitted.push(args), expose() {} })
    parsed.set(relative, source)
    return { state, emitted, source, template: descriptor.template.content }
}
async function flush() { for (let i = 0; i < 8; i++) { await Promise.resolve(); await Vue.nextTick() } }
const Box = Vue.defineComponent({ setup(_props, { slots }) { return () => Vue.h('section', [slots.default?.(), slots.reference?.(), slots.footer?.()]) } })
const Alert = Vue.defineComponent({ props: ['title', 'description'], setup: props => () => Vue.h('aside', [props.title, props.description]) })
const Table = Vue.defineComponent({ props: ['data'], setup: (props, { slots }) => () => Vue.h('table', (props.data || []).flatMap(row => ['channel', 'connections', 'enabled'].flatMap(key => slots[key]?.({ row }) || []))) })
async function render(view) {
    const fn = new Function('Vue', compile(view.template, { mode: 'function', prefixIdentifiers: true }).code)(Vue)
    const app = Vue.createSSRApp({ setup: () => ({ ...view.state, canEdit: true }), render: fn })
    for (const tag of ['HsxDrawer', 'el-button', 'el-switch', 'el-input']) app.component(tag, Box)
    app.component('el-alert', Alert); app.component('HsxTable', Table); app.config.warnHandler = () => {}
    return renderToString(app)
}
const component = 'niucloud/addon/hsx_lead/admin/views/components/ProviderChannelsSettings.vue'
function fixture(canEdit = true) {
    const calls = [], confirmations = [], messages = []
    let server = { revision: 'synthetic-revision-1', items: [
        { key: 'first', name: '合成渠道一', display_name: '合成渠道一', technical_name: '合成原接口一', enabled: true, connection_count: 3, automatic_sync_count: 2, renewal: { automatic: true } },
        { key: 'second', name: '合成渠道二', display_name: '合成渠道二', technical_name: '合成原接口二', enabled: true, connection_count: 1, automatic_sync_count: 0 }
    ], policy_message: '关闭暂停采集，保留历史和原商户开关' }
    let rejectWrite = false, rejectRead = false, rejectConfirmation = false
    const api = {
        getProviderChannels: async () => { calls.push('read'); if (rejectRead) { rejectRead = false; throw Error('合成：读取失败') }; return { data: clone(server) } },
        saveProviderChannels: async input => { calls.push(['save', clone(input)]); if (rejectWrite) throw Error('合成：配置已被其他管理员修改'); server = { ...server, revision: 'synthetic-revision-2', items: server.items.map(item => { const value=input.items.find(next => next.key === item.key);return { ...item, enabled:value.enabled,display_name:value.display_name,name:value.display_name } }) }; return { data: clone(server) } }
    }
    const view = setup(component, Vue.reactive({ modelValue: true, canEdit }), {
        'element-plus': { ElMessage: { success: value => messages.push(value) }, ElMessageBox: { confirm: async (...args) => { confirmations.push(args); if (rejectConfirmation) throw Error('cancel') } } },
        '../../api/cooperation': api, '../../composables/cooperation': { coopError: (cause, fallback) => cause?.message || fallback }
    })
    return { ...view, calls, confirmations, messages, rejectWrite: () => { rejectWrite = true }, rejectRead: () => { rejectRead = true }, rejectConfirmation: () => { rejectConfirmation = true } }
}
const normal = fixture(); await flush()
check(normal.state.rows.value.length === 2 && normal.state.ready.value, 'initial standalone channel read succeeds without reading or saving financial rules')
normal.state.rows.value[0].enabled = false
check(normal.state.closing.value.length === 1 && normal.state.impact.value.connections === 3 && normal.state.impact.value.automatic === 2, 'closing draft explains exact current connection impact')
let html = await render(normal)
check(html.includes('本次将关闭：合成渠道一') && html.includes('3 个连接'), 'impact appears in actual rendered drawer')
await normal.state.save()
check(normal.confirmations.length === 1 && normal.confirmations[0][0].includes('历史和账号保留'), 'closing requires explicit informed confirmation')
check(normal.calls.map(call => Array.isArray(call) ? call[0] : call).join(',') === 'read,save,read', 'save is followed by a fresh GET instead of trusting the click')
check(!normal.state.dirty.value && normal.messages.length === 1 && normal.state.revision.value === 'synthetic-revision-2', 'readback publishes authoritative revision and completion')
check(!Object.keys(normal.calls[1][1]).some(key => /fee|commission|password|token/.test(key)), 'channel request does not carry pricing rules or credentials')
normal.state.rows.value[1].enabled = false; await normal.state.save()
check(normal.state.rows.value.every(item => !item.enabled) && !normal.state.dirty.value, 'all channels may be explicitly closed')
html = await render(normal); check(html.includes('商户将没有可新增接入'), 'all-disabled state is explained, not silently treated as no data')

const failed = fixture(); await flush(); failed.state.rows.value[0].enabled = false; failed.rejectWrite(); await failed.state.save()
check(failed.state.awaitingReadback.value && failed.state.rows.value[0].enabled === false, 'uncertain or conflict save retains draft and requires readback')
await failed.state.save(); check(failed.calls.filter(call => Array.isArray(call)).length === 1, 'unconfirmed save cannot be blindly submitted again')
await failed.state.reload()
check(!failed.state.awaitingReadback.value && failed.state.rows.value[0].enabled === false && failed.state.baseline.value[0].enabled === true, 'checking unchanged server state preserves unsaved draft for deliberate review')
check(failed.state.error.value.includes('草稿已保留'), 'concurrent-change mismatch is actionable')

const lostRead = fixture(); await flush(); lostRead.state.rows.value[0].enabled = false; lostRead.rejectRead(); await lostRead.state.save()
check(lostRead.state.awaitingReadback.value && lostRead.messages.length === 0, 'successful PUT with failed readback is not called completed')
await lostRead.state.reload(); check(!lostRead.state.awaitingReadback.value && !lostRead.state.dirty.value && lostRead.calls.filter(call => Array.isArray(call)).length === 1, 'readback recovery confirms original save without repeating it')

const cancel = fixture(); await flush(); cancel.state.rows.value[0].enabled = false; cancel.rejectConfirmation(); await cancel.state.save()
check(!cancel.calls.some(Array.isArray) && cancel.state.dirty.value, 'cancelling closure confirmation performs no write and retains changes')
const readOnly = fixture(false); await flush(); readOnly.state.rows.value[0].enabled = false; await readOnly.state.save()
check(!readOnly.calls.some(Array.isArray), 'view-only configuration permission cannot invoke channel save')
const renamed=fixture();await flush();renamed.state.rows.value[0].display_name='自营 W 平台'
check(renamed.state.dirty.value && renamed.state.closing.value.length===0, 'changing only a display alias is a savable draft without pausing a channel')
await renamed.state.save()
check(renamed.calls.find(Array.isArray)[1].items[0].display_name==='自营 W 平台' && !renamed.state.dirty.value && renamed.confirmations.length===0, 'alias travels in the existing section API and is freshly read back without a closure confirmation')
check(renamed.state.rows.value[0].key==='first' && renamed.state.rows.value[0].enabled===true && renamed.state.rows.value[0].technical_name==='合成原接口一', 'renaming preserves canonical identifier, technical identity and enable switch')
html=await render(renamed)
check(html.includes('<details') && html.includes('接口识别信息') && html.includes('合成原接口一') && !html.includes('<details open'), 'real technical identity is available only in collapsed administrator detail, not the primary display title')
const invalidName=fixture();await flush()
for(const value of ['', '   ', '<b>平台</b>', '平台\n名称', '平台\u200b', '界'.repeat(25)]) {
    invalidName.state.rows.value[0].display_name=value;await invalidName.state.save()
    check(!invalidName.calls.some(Array.isArray) && !invalidName.state.awaitingReadback.value, 'invalid display alias is rejected before any write: '+JSON.stringify(value))
}
const aliasConflict=fixture();await flush();aliasConflict.state.rows.value[0].display_name='草稿 W';aliasConflict.rejectWrite();await aliasConflict.state.save();await aliasConflict.state.reload()
check(aliasConflict.state.rows.value[0].display_name==='草稿 W' && aliasConflict.state.baseline.value[0].display_name==='合成渠道一' && aliasConflict.state.dirty.value, 'uncertain alias write preserves the alias draft alongside enable flags after readback')
const apiSource = fs.readFileSync(path.join(root, 'niucloud/addon/hsx_lead/admin/api/cooperation.ts'), 'utf8')
check(apiSource.includes("section: 'erp_channels'") && apiSource.includes('getProviderChannels') && apiSource.includes('saveProviderChannels'), 'API uses explicit section within existing authorized configuration endpoint')
check(fs.readFileSync(path.join(root, 'niucloud/addon/hsx_lead/admin/views/cooperation/Settings.vue'), 'utf8').includes('<ProviderChannelsSettings') && !normal.source.includes('saveCooperationConfig'), 'business settings opens independent reusable channel component')

let getCalls = 0, advances = 0, timers = 0, paused = true
const progress = setup('niucloud/addon/hsx_lead/admin/views/components/SyncProgressDrawer.vue', Vue.reactive({ modelValue: true, runId: 9 }), {
    '../../api': { getSync: async () => { getCalls++; return { data: { id: 9, status: paused ? 'paused' : 'running', channel_enabled: !paused, page_count: 1, record_count: 100 } } }, advanceSync: async () => { advances++; return { data: { id: 9, status: 'paused', channel_enabled: false } } } },
    '../../composables/useLeadUi': { errorText: String, syncLabel: String, time: String }
}, { setTimeout: () => { timers++; return 1 }, clearTimeout() {} })
await flush(); check(getCalls === 1 && advances === 0 && timers === 0, 'opening a paused sync never advances or arms polling')
paused = false; await progress.state.poll(); check(advances === 1 && timers === 0, 'closure between read and advance stops the following poll loop')

const mobileFile = 'niucloud/addon/hsx_lead/uni-app/components/cooperation/CoopConnectionPanel.vue', mobileCalls = []
const mobileApi = { coopDate: String, coopError: (cause, fallback) => cause?.message || fallback }
for (const method of ['cooperationConnection', 'cooperationChallenge', 'cooperationProbe', 'cooperationActivate', 'cooperationSync', 'cooperationSyncInfo', 'cooperationSyncAdvance', 'cooperationConnectionSettings']) mobileApi[method] = async () => { mobileCalls.push(method); throw Error('Unexpected source mutation') }
const providers = [{ key: 'open', name: '开放渠道', auth_modes: [{ key: 'password', fields: [{ key: 'password', required: true }], renewal: { automatic: true } }] }]
const mobile = setup(mobileFile, Vue.reactive({ show: true, profile: {}, providers }), { '../../api/cooperation': mobileApi }, { uni: { showToast() {} } })
mobile.state.connection.value = { connected: true, provider: 'closed', provider_name: '原渠道', enabled: true, channel_enabled: false, channel_message: '合成：渠道关闭采集暂停' }; mobile.state.provider.value = 'closed'
check(mobile.state.channelClosed.value && !mobile.state.selectedChannelAvailable.value && mobile.state.syncStateLabel.value === '该 ERP 渠道已暂停', 'merchant sees source-policy pause separately from saved synchronization preference')
await mobile.state.probe(); await mobile.state.challenge(); await mobile.state.sync(); await mobile.state.resumeSetup()
check(mobileCalls.length === 0 && mobile.state.error.value.includes('渠道关闭'), 'paused merchant cannot trigger auth or acquisition requests by calling handlers directly')
mobile.state.changeProvider('open'); check(mobile.state.selectedChannelAvailable.value && mobile.state.provider.value === 'open', 'merchant can choose a different open source without altering old connection')
mobile.state.changeProvider('closed'); check(mobile.state.provider.value === 'open', 'closed source cannot be reselected through a direct picker handler call')

const profile = setup('niucloud/addon/hsx_lead/uni-app/components/cooperation/CoopProfilePanel.vue', Vue.reactive({
    profile: { id: 7, provider_requested: 'closed', name: '合成门店', contact_name: '合成联系人', mobile: '18800000001', province_id:910000,city_id:910100,district_id: 910101, address: '合成地址', connection: { provider: 'closed', provider_name: '原渠道', channel_enabled: false } }, config: {}, providers, editing:true,onboarding: false, busy: false, error: ''
}), { '../../api/cooperation': { ...profileConfigApi, coopDate: String, coopMoney: String }, '@/utils/common': { img: value => value } }, {uni:{getStorageSync:()=>'',setStorageSync(){},removeStorageSync(){}}})
await flush()
profile.state.open()
check(profile.emitted.some(event=>event[0]==='edit'),'profile entry opens the dedicated edit route without mutating ERP')
const oldIndex = profile.state.providerOptions.value.findIndex(item => item.key === 'closed'), openIndex = profile.state.providerOptions.value.findIndex(item => item.key === 'open')
check(oldIndex > 0 && profile.state.providerOptions.value[oldIndex].disabled && profile.state.providerOptions.value[oldIndex].name.includes('已关闭'), 'existing closed ERP remains visibly labelled when editing merchant base profile')
profile.state.submit(); check(profile.emitted.some(event => event[0] === 'submit' && event[1].provider === 'closed'), 'existing merchant can retain original closed ERP while editing basic details')
profile.state.selectProvider(openIndex); profile.state.selectProvider(oldIndex)
check(profile.state.form.provider === 'open' && profile.state.localError.value.includes('已关闭'), 'profile cannot switch back to an unavailable source once choosing an open one')
check(profile.state.connectionLabel.value.includes('同步暂停'), 'merchant profile entry does not call paused source successfully synchronized')
console.log(`PASS ${checks} provider channel UI checks; actual Vue SFC setup/render with synthetic APIs only, no network/database/ERP actions.`)
