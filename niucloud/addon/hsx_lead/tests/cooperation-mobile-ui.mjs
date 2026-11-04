import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { loadMobileUtility } from './mobile-utils.mjs'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

// Actual Vue state layer/SFC render with isolated API doubles. No network, ERP or payments.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const require = createRequire(path.join(root, 'uni-app/package.json'))
const Vue = require('vue'), ts = require('typescript'), compiler = require('@vue/compiler-sfc'), { renderToString } = require('@vue/server-renderer')
const mobile = path.join(root, 'niucloud/addon/hsx_lead/uni-app')
let checks = 0
function check(condition, message) { assert.ok(condition, message); checks++ }
function deferred() { let resolve, reject; const promise = new Promise((a, b) => { resolve = a; reject = b }); return { promise, resolve, reject } }
function execute(source, deps, globals = {}) { const module = { exports: {} }; const js = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText; vm.runInNewContext(`(function(require,module,exports){${js}\n})`, { Date, Math, Number, String, Map, Promise, console, setTimeout, clearTimeout, ...globals })(key => { if (key.startsWith('../../utils/')) return loadMobileUtility(path.basename(key)); if (/\.png$/.test(key)) return { default: '/synthetic-local-asset/' + path.basename(key) }; if (!(key in deps)) throw Error('Unexpected dependency ' + key); return deps[key] }, module, module.exports); return module.exports }
const apiSource = fs.readFileSync(path.join(mobile, 'api/cooperation.ts'), 'utf8')
const transport = [], request = Object.fromEntries(['get', 'post', 'put', 'upload'].map(method => [method, async (url, data) => { transport.push({ method, url, data }); return { data: {} } }]))
const api = execute(apiSource, { '@/utils/request': { default: request } })
await api.uploadCooperationAttachment('/tmp/synthetic.png')
check(transport[0].url === 'hsx_lead/cooperation/attachments' && transport[0].method === 'upload' && transport[0].data.name === 'file', 'attachment upload is plugin-owned and uses framework multipart transport')
await api.actCooperationLead(4, 'accept', 7)
check(transport[1].data.assignment_version === 7 && transport[1].data.action === 'accept', 'actions carry the displayed assignment version')
await api.uploadCooperationStorefront('/tmp/synthetic-storefront.png')
check(transport[2].url === 'hsx_lead/cooperation/storefront' && transport[2].method === 'upload' && transport[2].data.name === 'file', 'public storefront photos use a dedicated plugin upload endpoint, distinct from private lead screenshots')
check(api.coopMoney(null) === '—' && api.coopMoney('12.5') === '¥12.50', 'unknown money is not displayed as zero and decimal display is explicit')
check(api.coopRequestId().length <= 64, 'client idempotency key respects backend bound')
const profile = { id: 7, name: '合成商户', member_id: 19 }
const bootstrapData = { profile, config: { enabled: true, fee_confirmed: true, commission_minimum: '50.00' }, providers: [], stats: {}, wallet: {} }
function harness(initialTab = 'deliveries') {
    let identity = 'member-a'; const calls = [], controls = {
        bootstrap: async () => ({ data: bootstrapData }), leads: async data => ({ data: { data: [{ id: data.direction === 'incoming' ? 8 : 4, models: [{ id: 'cat_a', title: 'iPhone 16 Pro' }] }], total: 2 } }),
        detail: async id => ({ data: { id, status: 'assigned', can_accept: true, assignment_version: 3, mobile: '', attachments: [] } }),
        wallet: async data => ({ data: { available: '12.50', framework_cash_out: true, entries: { data: [{ id: data.page, status: 'paid', net_amount: '12.50' }], total: 2 } } }),
        action: async () => ({ data: {} }), write: async () => ({ data: { id: 4 } })
    }
    const doubles = { ...api,
        cooperationBootstrap: async () => { calls.push('bootstrap'); return controls.bootstrap() },
        cooperationLeads: async data => { calls.push({ name: 'leads', ...data }); return controls.leads(data) },
        cooperationLead: async id => { calls.push({ name: 'detail', id }); return controls.detail(id) },
        cooperationWallet: async data => { calls.push({ name: 'wallet', ...data }); return controls.wallet(data) },
        actCooperationLead: async (...args) => { calls.push({ name: 'action', args }); return controls.action(...args) },
        createCooperationLead: async data => { calls.push({ name: 'create', data }); return controls.write(data) },
        assignCooperationLead: async (...args) => { calls.push({ name: 'assign', args }); return controls.write(...args) }
    }
    const module = execute(fs.readFileSync(path.join(mobile, 'composables/useCooperation.ts'), 'utf8'), { vue: Vue, '@/utils/common': { getToken: () => identity }, '../api/cooperation': doubles })
    return { state: module.useCooperation(initialTab), calls, controls, setIdentity: value => { identity = value } }
}
let h = harness(), s = h.state
check(!s.ready.value && !s.rows.value.length && !s.profile.value.id, 'unloaded state contains no fabricated merchant or business facts')
h.setIdentity(''); s.resume(); check(await s.bootstrap() === false && !h.calls.length && !s.loggedIn.value, 'unauthenticated bootstrap cannot call merchant APIs')
h.setIdentity('member-a'); s.resume(); check(await s.bootstrap() && s.profile.value.id === 7 && s.ready.value, 'authenticated bootstrap reads server-owned profile and configuration')
await s.loadLeads(); check(s.rows.value[0].id === 4 && s.canLoadMore.value, 'outgoing list uses backend total rather than assuming one page')
await s.changeDirection('incoming'); check(s.rows.value[0].id === 8 && h.calls.filter(row => row?.name === 'leads').at(-1).direction === 'incoming', 'incoming/outgoing use an explicit server filter')
s.query.keyword = '型号'; s.query.status = 'following'; await s.loadLeads(true)
check(s.query.page === 2 && s.rows.value.length === 1 && h.calls.filter(row => row?.name === 'leads').at(-1).keyword === '型号', 'pagination retains filters and removes duplicate rows')
h.controls.leads = async () => { throw { msg: '合成网络失败' } }; await s.loadLeads()
check(s.listError.value === '合成网络失败' && s.rows.value.length === 1 && !s.listLoading.value, 'read failure is explicit and does not replace old data with fake empty data')
await s.loadDetail(9); check(s.detail.value.assignment_version === 3 && s.detail.value.mobile === '', 'detail trusts redacted server payload without fabricating a phone')
const detailCalls = h.calls.filter(row => row?.name === 'detail').length; await s.loadDetail(0)
check(h.calls.filter(row => row?.name === 'detail').length === detailCalls && !!s.detailError.value, 'invalid detail identifiers are blocked locally')
s.detail.value = { id: 9, assignment_version: 12, status: 'following' }; await s.action('deal')
check(h.calls.find(row => row?.name === 'action').args[2] === 12, 'a mutation uses an immutable copy of the current assignment version')
const hold = deferred(); h.controls.write = () => hold.promise; const first = s.createLead({ request_id: 'synthetic-1', model_ids: ['cat_a'] }); const second = await s.createLead({ request_id: 'synthetic-1' })
check(second === null && s.busy.value && h.calls.filter(row => row?.name === 'create').length === 1, 'a submission lock covers repeated taps')
hold.reject({ msg: '结果待核实' }); await first
check(!s.busy.value && s.writeError.value === '结果待核实', 'failed writes preserve an explicit cause rather than showing success')
const lateWrite = deferred(); h.controls.write = () => lateWrite.promise; const writing = s.createLead({ request_id: 'synthetic-2' }); h.setIdentity('member-b'); lateWrite.resolve({ data: { id: 99, mobile: 'sensitive' } }); check(await writing === null, 'late mutation result is discarded after member identity changes')
s.resume(); check(!s.ready.value && !s.profile.value.id && !s.rows.value.length && !s.detail.value.id, 'identity switch clears all previous account data')
await s.bootstrap(); const old = deferred(); h.controls.leads = () => old.promise; const reading = s.loadLeads(); s.pause(); old.resolve({ data: { data: [{ id: 999 }], total: 1 } }); await reading
check(!s.rows.value.some(row => row.id === 999) && !s.listLoading.value, 'backgrounded or unmounted page ignores late list reads')
s.resume(); await s.bootstrap(); await s.loadWallet(); check(s.wallet.value.entries.data.length === 1 && s.walletCanLoadMore.value, 'wallet exposes earlier-record availability')
await s.loadWallet(true); check(s.wallet.value.entries.data.length === 2 && !s.walletCanLoadMore.value && h.calls.filter(row => row?.name === 'wallet').at(-1).page === 2, 'wallet can page beyond its initial set without discarding prior entries')
const deferredBoot = deferred(); h.controls.bootstrap = () => deferredBoot.promise; const bootstrap = s.bootstrap(); s.dispose(); deferredBoot.resolve({ data: { profile: { id: 999 }, config: {} } }); await bootstrap; check(s.profile.value.id !== 999, 'disposed page cannot receive a late bootstrap identity')

const cache = new Map()
function component(file) {
    const filename = path.join(mobile, 'components/cooperation', file); if (cache.has(filename)) return cache.get(filename)
    const descriptor = compiler.parse(fs.readFileSync(filename, 'utf8'), { filename }).descriptor
    const script = compiler.compileScript(descriptor, { id: filename, inlineTemplate: true })
    const dependencies = { vue: Vue, '../../api/cooperation': api, '@/utils/common': { img: value => value, redirect() {} }, '@/app/api/system': { getAreaListByPid: async () => ({ data: [] }) } }
    for (const match of script.content.matchAll(/from\s+['"](\.\/[^'"]+\.vue)['"]/g)) dependencies[match[1]] = { default: component(path.basename(match[1])) }
    const result = execute(script.content, dependencies, { uni: { showToast() {}, previewImage() {}, makePhoneCall() {} } }).default; cache.set(filename, result); return result
}
async function html(Component, props) {
    const app = Vue.createSSRApp({ render: () => Vue.h(Component, props) }); app.config.warnHandler = () => {}
    app.component('u-icon', { props: ['name'], setup: props => () => Vue.h('i', { 'data-icon': props.name }) })
    app.component('u-loading-icon', { setup: () => () => Vue.h('i', {}, 'loading') })
    app.component('u-popup', { props: ['show'], setup: (props, { slots }) => () => props.show ? Vue.h('aside', {}, slots.default?.()) : null })
    app.component('u-button', { props: ['text', 'disabled'], setup: props => () => Vue.h('button', { disabled: props.disabled }, props.text) })
    return renderToString(app)
}
let rendered = await html(component('CoopLeadCard.vue'), { lead: { id: 1, customer_name: '合成客户', status: 'selecting', can_assign: true, models: [{ id: 'cat_a', title: 'iPhone 16 Pro', quantity: 99 }], region_text: '河北 / 石家庄 / 长安区' }, direction: 'outgoing' })
check(rendered.includes('选择门店') && rendered.includes('iPhone 16 Pro') && !rendered.includes('99') && !rendered.includes('台'), 'actual lead card only renders model labels, not demand quantity')
rendered = await html(component('CoopStoreCard.vue'), { store: { id: 2, name: '合成门店', grade_name: 'S级商户', is_reliable: true, all_models: true, region_text: '合成地区' }, selected: true, selectable: true })
check(rendered.includes('靠谱认证') && rendered.includes('S级商户') && rendered.includes('全型号交付能力'), 'reliable certification, grade and model capability remain separate')
for (const reliable of [false, 0, '0', 'false', '', undefined]) {
    rendered = await html(component('CoopMerchantAvatar.vue'), { name: '普通商家', image: '', reliable })
    check(!rendered.includes('is-reliable') && !rendered.includes('coop-merchant-avatar__badge') && !rendered.includes('coop-merchant-avatar__glint'), 'uncertified value ' + String(reliable) + ' cannot render a VIP ring, sweep or certification mark')
    rendered = await html(component('CoopStoreCard.vue'), { store: { id: 2, name: '普通商家', grade_name: 'S级商户', is_reliable: reliable, region_text: '合成地区' } })
    check(!rendered.includes('is-reliable') && !rendered.includes('靠谱认证') && !rendered.includes('coop-merchant-avatar__badge'), 'a high merchant grade never turns uncertified value ' + String(reliable) + ' into a reliable store badge')
}
for (const reliable of [true, 1, '1']) {
    rendered = await html(component('CoopMerchantAvatar.vue'), { name: '靠谱商家', image: 'upload/hsx_lead/owned.png', reliable })
    check(rendered.includes('is-reliable') && rendered.includes('coop-merchant-avatar__wing') && !rendered.includes('coop-merchant-avatar__badge') && rendered.includes('coop-merchant-avatar__glint') && rendered.includes('owned.png'), 'server-certified merchants get the storefront photo and exclusive treatment')
}
rendered = await html(component('CoopProfilePanel.vue'), { profile: { id: 7, name: '永久靠谱商户', is_reliable: true, certification_status: 'active', cert_expires_at: 0 }, config: {}, providers: [] })
check(rendered.includes('永久有效') && rendered.includes('is-reliable') && rendered.includes('已永久认证'), 'a server-certified zero-expiry profile is visibly permanent, cannot reapply, and retains its VIP avatar')
rendered = await html(component('CoopProfilePanel.vue'), { profile: { id: 7, name: '普通商户', is_reliable: false, certification_status: 'none', cert_expires_at: 0 }, config: {}, providers: [] })
check(!rendered.includes('永久有效') && !rendered.includes('is-reliable'), 'zero expiry alone does not grant a permanent reliable badge')
rendered = await html(component('CoopMerchantAvatar.vue'), { name: '未上传图片商家', reliable: false })
check(!rendered.includes('coop-merchant-avatar__image') && rendered.includes('data-icon="home"'), 'no storefront photo displays a neutral shop icon, not a phone image or broken thumbnail')
const merchantAvatar = fs.readFileSync(path.join(mobile, 'components/cooperation/CoopMerchantAvatar.vue'), 'utf8')
check(merchantAvatar.includes('@error=') && merchantAvatar.includes('prefers-reduced-motion') && merchantAvatar.includes('animation: none'), 'avatar handles image failure and supports reduced-motion settings')
const modelPicker = fs.readFileSync(path.join(mobile, 'components/cooperation/CoopModelSearchPicker.vue'), 'utf8'), storeCard = fs.readFileSync(path.join(mobile, 'components/cooperation/CoopStoreCard.vue'), 'utf8')
check(!modelPicker.includes('CoopDeviceIcon') && !modelPicker.includes('coop-device-thumb') && !storeCard.includes('CoopDeviceIcon'), 'merchant model search and capabilities remain text only without decorative phone renders')
const profilePanel = fs.readFileSync(path.join(mobile, 'components/cooperation/CoopProfilePanel.vue'), 'utf8'), storefrontUpload = fs.readFileSync(path.join(mobile, 'components/cooperation/CoopStorefrontUpload.vue'), 'utf8')
check(profilePanel.includes('storefront_image') && profilePanel.includes('CoopStorefrontUpload') && profilePanel.includes('uploading'), 'profile editor carries the storefront field and locks during upload')
check(storefrontUpload.includes('uploadCooperationStorefront') && /\.data\?\.path/.test(storefrontUpload) && storefrontUpload.includes('5242880'), 'single-photo uploader keeps an owned server reference and enforces the 5MB bound')
rendered = await html(component('CoopTaskDetail.vue'), { show: true, lead: { id: 3, status: 'assigned', can_accept: true, mobile: '', attachments: [], models: [{ id: 'cat_a', title: 'iPhone 16 Pro' }] }, config: { commission_minimum: '50.00' } })
check(rendered.includes('接单后查看联系电话') && rendered.includes('接受交付') && !rendered.includes('联系客户</text>'), 'unaccepted detail shows a privacy state and no contact action')
rendered = await html(component('CoopTaskDetail.vue'), { show: true, lead: { id: 3, status: 'following', can_report: true, mobile: '13800008000', models: [] }, config: {} })
check(rendered.includes('未成交') && rendered.includes('已成交，去付佣') && !rendered.includes('利润输入'), 'following detail has two clear outcomes without sales or profit fields')
rendered = await html(component('CoopTaskDetail.vue'), { show: true, lead: { id: 3, status: 'paid', commission_amount: '50.00', net_amount: '40.10', requires_manual_review: true, review_message: '收益尚未入账，勿重复付款', attention_reasons: ['收益尚未入账，勿重复付款'], models: [] }, config: {} })
check(rendered.includes('发布方应得净收益') && rendered.includes('收益尚未入账，勿重复付款') && !rendered.includes('发布方收益</text>'), 'paid-but-uncredited detail distinguishes expected net income and surfaces the server review warning')

const entry = fs.readFileSync(path.join(mobile, 'pages/workbench/index.vue'), 'utf8'), workbench = fs.readFileSync(path.join(mobile, 'components/cooperation/CooperationWorkbench.vue'), 'utf8')
const taskSheet = fs.readFileSync(path.join(mobile, 'components/cooperation/CoopTaskSheet.vue'), 'utf8')
check(taskSheet.includes(':z-index="taskSheetZIndex"') && /let taskSheetZIndex = 10090[\s\S]*\/\/ #ifdef H5[\s\S]*taskSheetZIndex = 998[\s\S]*\/\/ #endif/.test(taskSheet), 'H5 task layers remain below native uni picker and modal; mini-program layering is unchanged')
const pageHook = fs.readFileSync(path.join(mobile, 'composables/useCooperationPage.ts'), 'utf8')
check(pageHook.includes('onShow(') && pageHook.includes('cooperationRef.value?.resume') && pageHook.includes('onHide(') && pageHook.includes('pause'), 'shared page hook explicitly forwards lifecycle instead of relying on child page hooks')
check(pageHook.includes('collaboration_id') && !pageHook.includes('task_id') && !entry.includes('LegacyWorkbench'), 'fresh version only resolves current collaboration notifications')
check(!workbench.includes('查看原平台派发工单') && !workbench.includes('coop-legacy-entry') && !pageHook.includes('openLegacy'), 'normal merchant navigation never opens the retired platform-dispatch workflow')
function pageHarness(options) {
    const callbacks = {}, redirects = []
    const lifecycle = Object.fromEntries(['onHide', 'onLoad', 'onPullDownRefresh', 'onReachBottom', 'onShow'].map(name => [name, callback => { callbacks[name] = callback }]))
    const module = execute(pageHook, { vue: Vue, '@dcloudio/uni-app': lifecycle, '@/hooks/useShare': { useShare: () => ({ onShareAppMessage() {} }) }, '@/utils/common': { redirect: value => redirects.push(value) }, '../api/cooperation': api }, { uni: { stopPullDownRefresh() {} } })
    const page = module.useCooperationPage()
    callbacks.onLoad(options)
    return { page, callbacks, redirects }
}
let pageState = pageHarness({ collaboration_id: '92' })
check(pageState.page.collaborationId.value === 92, 'current notification opens its exact collaboration')
for (const collaboration_id of ['0', '-1', '1.5', '4294967296', 'abc']) {
    pageState = pageHarness({ collaboration_id })
    check(pageState.page.collaborationId.value === 0, 'invalid collaboration identifier is rejected: ' + collaboration_id)
}
check(workbench.includes("import FrameworkTabbar from '@/components/tabbar/tabbar.vue'") && workbench.includes('<FrameworkTabbar addon="hsx_lead"') && !workbench.includes('coop-bottom-nav') && !workbench.includes('const tabs ='), 'merchant navigation is rendered by the framework and never a static replacement')
check(workbench.includes('restoreCanonicalRoute()') && workbench.includes('!overlay.value && !childOverlay.value') && workbench.includes('!canonicalPending.value'), 'deep links and modal tasks hide the framework bar until safe canonical routing')
check(workbench.includes('locked = computed(() => ui.busy.value || confirming.value)') && /showFrameworkTabbar = computed\([^\n]*!locked\.value/.test(workbench), 'submission and confirmation locks hide navigation so framework clicks cannot abandon an active mutation')
check(workbench.includes('pageUrl = cooperationPageUrls[props.initialTab]') && workbench.includes('url: cooperationPageUrls.profile'), 'cashier returns to the current real page and publishing rights route to the profile page')
for (const [tab, folder] of [['deliveries', 'workbench'], ['stores', 'stores'], ['wallet', 'wallet'], ['profile', 'profile']]) {
    const wrapper = fs.readFileSync(path.join(mobile, 'pages', folder, 'index.vue'), 'utf8')
    check(wrapper.includes(`initial-tab="${tab}"`) && wrapper.includes('useCooperationPage'), 'real registered route shares lifecycle for ' + tab)
    const routed = harness(tab); routed.state.resume(); await routed.state.refresh()
    check(routed.state.tab.value === tab && (tab === 'wallet' ? routed.calls.some(row => row?.name === 'wallet') : tab === 'deliveries' ? routed.calls.some(row => row?.name === 'leads') : !routed.calls.some(row => row?.name === 'leads' || row?.name === 'wallet')), 'route initial state only reads its own business data for ' + tab)
}
check(workbench.includes("open('hsx_lead_cooperation'") && workbench.includes('cooperationOrder(id)'), 'payment uses framework cashier and authoritative order verification')
check(workbench.includes('requires_manual_review') && workbench.includes('已收款，需平台核对'), 'paid-but-ungranted rights get an explicit manual-review warning')
check(workbench.includes('hsx_lead_task_assigned,hsx_lead_task_changed'), 'subscription reuses configured framework notification keys')
check(workbench.includes("value === 'reject' || value === 'withdraw' || value === 'no_deal'"), 'terminal/rejected handling refreshes list without querying a lost receiver permission')
const walletPanel = fs.readFileSync(path.join(mobile, 'components/cooperation/CoopWalletPanel.vue'), 'utf8')
check(walletPanel.includes('/app/pages/member/apply_cash_out') && walletPanel.includes('/app/pages/member/cash_out') && walletPanel.includes('/app/pages/member/commission') && (walletPanel.match(/setStorageSync\('cashOutAccountType', 'commission'\)/g) || []).length === 2, 'withdrawal and record entry both select the framework commission account')
check(!apiSource.includes('cooperationWithdrawal') && !walletPanel.includes('cooperationWithdrawal') && walletPanel.includes('包含其他业务佣金'), 'wallet does not recreate withdrawals or claim shared commission belongs exclusively to collaboration')
rendered = await html(component('CoopWalletPanel.vue'), { wallet: { available: '90.20', frozen: '0', total_income: '240.60', withdrawn: '150.40', entries: { data: [{ id: 1, status: 'paid', net_amount: '40.10', create_time: 1790000000 }], total: 2 } } })
check(rendered.includes('协作收益明细') && rendered.includes('+¥40.10') && rendered.includes('加载更早的协作收益'), 'actual wallet renders server net income and earlier-page affordance')
const connectionPanel = fs.readFileSync(path.join(mobile, 'components/cooperation/CoopConnectionPanel.vue'), 'utf8')
check(connectionPanel.includes('cooperationConnectionSettings({ enabled: next, interval_minutes: interval.value })') && connectionPanel.includes('connectionActionState') && connectionPanel.includes("flow.value.primary === 'activate'") && connectionPanel.includes("setupStage.value = 'enable_sync'"), 'ERP activation explicitly enables synchronization and preserves partial-stage recovery')
const upload = fs.readFileSync(path.join(mobile, 'components/cooperation/CoopAttachmentUpload.vue'), 'utf8')
check(upload.includes('result.data?.path') && !upload.includes('file/image'), 'attachments store server-owned references, not arbitrary external URLs')
const files = ['api/cooperation.ts', 'composables/useCooperation.ts', 'composables/useCooperationPage.ts', 'pages/workbench/index.vue', 'pages/stores/index.vue', 'pages/wallet/index.vue', 'pages/profile/index.vue', ...fs.readdirSync(path.join(mobile, 'components/cooperation')).map(name => 'components/cooperation/' + name)]
for (const file of files) check(fs.statSync(path.join(mobile, file)).size > 0, 'current source exists: ' + file)
console.log(`PASS ${checks} cooperation mobile checks; actual Vue state and SFC render with synthetic APIs. No live ERP, upload, payment, notification or database actions.`)
