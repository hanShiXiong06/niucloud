import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
import { loadMobileUtility } from './mobile-utils.mjs'

// Current merchant flows: real Vue state/SFC compilation, synthetic APIs only.
const plugin = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const root = path.resolve(plugin, '../../..'), require = createRequire(path.join(root, 'admin/package.json'))
const Vue = require('vue'), compiler = require('@vue/compiler-sfc'), ts = require('typescript'), sass = require('sass')
const directory = path.join(plugin, 'uni-app/components/cooperation')
let checks = 0
const check = (value, label) => { assert.ok(value, label); checks++ }
const apiBase = { coopDate: String, coopMoney: value => value == null ? '—' : `¥${Number(value).toFixed(2)}`, coopError: (error, fallback) => error?.message || fallback }
function setup(name, values, api = {}) {
    const file = path.join(directory, name + '.vue'), source = fs.readFileSync(file, 'utf8')
    const { descriptor, errors } = compiler.parse(source, { filename: file }); check(!errors.length, name + ' parses')
    const script = compiler.compileScript(descriptor, { id: name })
    const template = compiler.compileTemplate({ source: descriptor.template.content, filename: file, id: name, compilerOptions: { bindingMetadata: script.bindings, expressionPlugins: ['typescript'] } })
    check(!template.errors.length, name + ' template compiles')
    const js = ts.transpileModule(script.content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const module = { exports: {} }, emitted = [], props = Vue.reactive(values), cleanups = []
    const storage = new Map()
    vm.runInNewContext('(function(require,module,exports){' + js + '})', { Date, Math, Number, String, JSON, Map, Promise, console, setTimeout, clearTimeout, uni: { getStorageSync: key => storage.get(key), setStorageSync: (key, value) => storage.set(key, value), removeStorageSync: key => storage.delete(key), showToast() {}, showModal() {}, makePhoneCall() {} } })(key => key === 'vue' ? { ...Vue, onMounted: callback => callback(), onBeforeUnmount: callback => cleanups.push(callback) } : key === '../../api/cooperation' ? { ...apiBase, ...api } : key === '../../utils/cooperation-diy-style' ? loadMobileUtility('cooperation-diy-style') : key === '../../utils/entity-membership' ? loadMobileUtility('entity-membership') : key === '../../utils/mobile-ui' ? loadMobileUtility('mobile-ui') : key === '../../utils/cooperation-business' ? loadMobileUtility('cooperation-business') : key === '../../utils/cooperation-payment' ? loadMobileUtility('cooperation-payment') : key === '../../utils/cooperation-navigation' ? { openCooperationTask() {}, openCooperationCatalog() {} } : key === '../../api/regions' ? { searchCooperationRegions: async () => ({ data: { data: [], total: 0 } }) } : key === '@/app/api/system' ? api : key.endsWith('.vue') ? { default: {} } : key === '@/utils/common' ? { img: value => value } : (() => { throw Error('Unexpected import ' + key) })(), module, module.exports)
    const state = module.exports.default.setup(props, { expose() {}, emit: (...args) => emitted.push(args) })
    return { state, props, emitted, source, cleanups }
}
async function settle() { for (let step = 0; step < 12; step++) { await Promise.resolve(); await Vue.nextTick() } }

const calls = [], stores = [{ id: 1, name: '本省甲店' }, { id: 2, name: '本省乙店' }, { id: 3, name: '本省丙店' }]
let deferredResolve
const picker = setup('CoopStorePicker', { show: false, lead: undefined, profile: { province_id: 999, city_id: 998, district_id: 997 }, busy: false }, {
    cooperationStores: async (query, id) => { calls.push({ query: { ...query }, id }); if (query.keyword === 'hold') return new Promise(resolve => { deferredResolve = resolve }); return { data: { data: query.page === 2 ? [stores[2]] : stores.slice(0, 2), total: 3, scope: query.scope, reason: '合成地区结果' } } }
})
picker.props.show = true; await settle()
check(calls.length === 0 && !picker.state.region.value.province_id, 'independent discovery never silently uses the merchant address as the customer location')
picker.state.region.value = { province_id: 10, city_id: 0, district_id: 0 }; picker.state.applyRegion(); await settle()
check(calls.at(-1).query.scope === 'province' && calls.at(-1).query.province_id === 10 && picker.state.rows.value.length === 2, 'province alone enables real province discovery')
await picker.state.load(true)
check(calls.at(-1).query.page === 2 && picker.state.rows.value.length === 3, 'discovery pagination preserves the applied region and includes remaining stores')
picker.state.region.value = { province_id: 10, city_id: 11, district_id: 0 }; picker.state.applyRegion(); await settle()
check(calls.at(-1).query.scope === 'city', 'city can be queried without a district')
picker.state.region.value = { province_id: 10, city_id: 11, district_id: 12 }; picker.state.applyRegion(); await settle()
check(calls.at(-1).query.scope === 'district', 'district query uses the deepest explicit selected area')
picker.state.region.value = { province_id: 10, city_id: 0, district_id: 0 }
check(picker.state.appliedRegion.value.city_id === 11, 'editing a region draft does not change the applied results before querying')
picker.state.keyword.value = 'hold'; const pending = picker.state.load(); await settle(); picker.props.show = false; await settle(); deferredResolve({ data: { data: [{ id: 99 }], total: 1 } }); await pending
check(picker.state.rows.value.length === 0 && !picker.state.loading.value, 'closed discovery rejects late responses and releases loading state')
check(!picker.source.includes('优先本区县') && !picker.source.includes('ERP 的地区编号') && picker.source.includes('!region.province_id'), 'UI has no technical/auto-option clutter and province is the only query requirement')
const leadCalls = [], leadPicker = setup('CoopStorePicker', { show: false, lead: { id: 51, province_id: 20, city_id: 21, district_id: 22 }, profile: { province_id: 999 }, busy: false }, { cooperationStores: async (query, id) => { leadCalls.push({ query, id }); return { data: { data: [], total: 0, scope: 'city' } } } })
leadPicker.props.show = true; await settle()
check(leadCalls.length === 1 && leadCalls[0].id === 51 && leadCalls[0].query.scope === 'auto' && !('province_id' in leadCalls[0].query), 'lead-based discovery delegates customer-location county/city/province fallback to the authoritative lead API')
const regionFields = setup('CoopRegionFields', { modelValue: { province_id: 10, city_id: 11, district_id: 12 }, disabled: false, minLevel: 1 }, { getAreaListByPid: async pid => ({ data: [{ id: pid === 0 ? 10 : pid === 10 ? 11 : 12, name: '合成地区' }] }) })
regionFields.state.open(); await settle(); await regionFields.state.choose(1, { detail: { value: 0 } }); regionFields.state.applyDraft()
const clearedCity = regionFields.emitted.find(event => event[0] === 'update:modelValue')?.[1]
check(clearedCity.province_id === 10 && clearedCity.city_id === 0 && clearedCity.district_id === 0, 'city can be cleared after selection to return to province-only discovery')

const businesses = [{ key: 'purchase', name: '二手机', enabled: true, dimension: 'model', selection_mode: 'multiple', selection_limit: 0, commission_minimum: '100.00', fields: [], options: [] }]
const profile = setup('CoopProfilePanel', { profile: {}, config: { businesses }, providers: [], onboarding: true, busy: false, error: '' })
Object.assign(profile.state.form, { name: '无ERP合成门店', contact_name: '店员', mobile: '18800000001', province_id: 10, city_id: 11, district_id: 12, address: '合成地址', provider: '', manual_models: [{ id: 'cat_1', title: 'iPhone 16', brand: '苹果' }] })
profile.state.selectedBusinesses.value = ['purchase']; profile.state.capabilityChoices.purchase = [{ id: 'cat_1', title: 'iPhone 16' }]
profile.state.submit()
const submitted = profile.emitted.find(event => event[0] === 'submit')?.[1]
check(!!submitted && submitted.provider === '' && submitted.business_capabilities[0].option_ids[0] === 'cat_1' && !profile.state.localError.value, 'merchant without ERP can save business-scoped delivery capability and proceed')
check(profile.state.providerOptions.value.length === 1 && profile.state.providerOptions.value[0].key === '', 'no opened ERP channel does not block merchant registration')
check(profile.source.includes('不代表当前有现货') && profile.source.includes('openCooperationCatalog'), 'manual product capability uses the shared real selector page and is never advertised as stock')
const connectedProfile = setup('CoopProfilePanel', { profile: { id: 8, provider_requested: '', connection: { connected: true, provider: 'synthetic', provider_name: '合成 ERP' } }, config: {}, providers: [{ key: 'synthetic', name: '合成 ERP' }], editing: true, onboarding: false, busy: false, error: '' })
connectedProfile.state.open()
check(connectedProfile.state.form.provider === 'synthetic' && connectedProfile.source.includes('v-if="profile.connection?.connected"') && connectedProfile.source.includes('保存门店资料不会切换现有 ERP 账号'), 'an existing ERP is shown as a retained connection, not a misleading none option that silently changes back')
connectedProfile.state.manageConnection()
check(!connectedProfile.state.visible.value && connectedProfile.emitted.some(event => event[0] === 'connection') && !connectedProfile.emitted.some(event => event[0] === 'submit'), 'managing an existing connection closes the form without changing credentials or the auto-sync preference')

const inventoryCalls = [], inventory = setup('CoopStoreInventory', { show: false, store: { id: 6, name: '合成门店' } }, { cooperationStoreInventory: async (id, query) => { inventoryCalls.push({ id, query }); return { data: { data: [{ id: query.page, model: 'iPhone 16', price: 1000 }], total: 2, snapshot_at: 100, message: '' } } } })
let inventoryNoMore = false
// A new keyed inventory pager now owns its mounted first-page request. This
// flow fixture leaves the actual delayed lifecycle checks to inventory-initial-load-ui.
const stopInventory = Vue.watch([() => inventory.props.show, inventory.state.pagingRevision], ([show]) => {
    if (!show) { inventory.state.pagingRef.value = null; return }
    const query = inventory.state.pagingQuery.value
    inventory.state.pagingRef.value = { completeByNoMore: async (_, noMore) => { inventoryNoMore = noMore }, complete: async () => {} }
    void query(1, 20)
}, { flush: 'post' })
inventory.props.show = true; await settle(); await inventory.state.queryInventory(2, 20)
check(inventoryCalls.length === 2 && inventoryCalls[1].query.page === 2 && inventory.state.rows.value.length === 2 && inventoryNoMore, 'stock pager queries the selected store, accumulates pages once and marks the real last page')
inventory.state.keyword.value = 'iPhone'; inventory.state.search(); await settle()
check(inventoryCalls.at(-1).query.keyword === 'iPhone' && inventoryCalls.at(-1).query.page === 1, 'stock search resets pagination without selecting another merchant')
check(inventory.source.includes('成交前请与门店确认') && !inventory.source.includes('row.imei') && !inventory.source.includes('row.cost') && !inventory.source.includes('snapshot_json'), 'merchant-facing stock is clearly a snapshot and renders no internal sensitive fields')
stopInventory()

const detail = fs.readFileSync(path.join(directory, 'CoopTaskDetail.vue'), 'utf8'), card = fs.readFileSync(path.join(directory, 'CoopLeadCard.vue'), 'utf8'), workbench = fs.readFileSync(path.join(directory, 'CooperationWorkbench.vue'), 'utf8')
check(detail.includes('lead.can_cancel') && detail.includes("$emit('action', 'cancel')") && card.includes('lead.can_cancel') && card.includes("$emit('cancel', lead.id)"), 'unassigned cancellation is discoverable in both list and detail and driven by the server permission')
check(workbench.includes("value === 'cancel'") && workbench.includes('历史记录会保留') && workbench.includes('await refreshBusinessData()'), 'cancellation explains its terminal result and refreshes metadata plus the single pager owner')
check(workbench.includes("if (data.provider &&") && workbench.includes('useSubscribeMessage().request(') && workbench.includes('coop-subscribe-entry'), 'manual onboarding does not force an ERP popup and notifications reuse the framework hook at an actionable entry')
const parsed = compiler.parse(workbench).descriptor, style = parsed.styles[0]
check(style.scoped === true, 'workbench layout styles belong to its scoped Vue block')
const css = sass.compileString(style.content, { loadPaths: [directory] }).css
const scoped = compiler.compileStyle({ source: css, filename: 'CooperationWorkbench.vue', id: 'data-v-test', scoped: true })
check(!scoped.errors.length && !scoped.code.includes(':deep(') && scoped.code.includes('.coop-delivery-controls[data-v-test]') && scoped.code.includes('.coop-work-overview[data-v-test]'), 'compiled main layout and todo styles target their own component scope, not cross-component descendant styling')
check(workbench.indexOf('class="coop-delivery-controls"') < workbench.indexOf('class="coop-main-scroll"') && workbench.indexOf('<CooperationNav') < workbench.indexOf('class="coop-main-scroll"'), 'native header and delivery filters remain outside the scrolling list')
console.log(`PASS ${checks} cooperation flow UI checks; real Vue state and compiled CSS, synthetic APIs only; no live ERP, notifications, payments or browser visual acceptance.`)
