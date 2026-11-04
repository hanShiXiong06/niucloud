import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

// Actual Vue setup/render with harmless UI contracts and synthetic APIs only.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const require = createRequire(path.join(root, 'admin/package.json'))
const Vue = require('vue'), compiler = require('@vue/compiler-sfc'), ts = require('typescript')
const { h, defineComponent, createRenderer, nextTick } = Vue
let checks = 0
const check = (ok, label) => { assert.ok(ok, label); checks++ }
const Box = defineComponent({ setup(_props, { slots }) { return () => h('box', [slots.reference?.(), slots.filters?.(), slots.summary?.(), slots.default?.()]) } })
const Table = defineComponent({ props: ['data'], setup(props, { slots }) { return () => h('table', (props.data || []).flatMap(row => ['product', 'merchant', 'price', 'updated'].flatMap(name => slots[name]?.({ row }) || []))) } })
const inventoryCalls = [], merchantCalls = [], pending = []
const inventoryRow = { id: 1, merchant_id: 201, merchant_name: 'Synthetic shop', model: 'Synthetic Phone', price: '1000.00',
    province_id: 910000, city_id: 910100, district_id: 910101, region_text: 'Fixture Province / Fixture City / Fixture District',
    province_name: 'Fixture Province', city_name: 'Fixture City', district_name: 'Fixture District' }
const api = {
    async getInventory(params) { inventoryCalls.push({ ...params }); return { data: { data: [inventoryRow], total: 1 } } },
    async getMerchants(params) {
        merchantCalls.push({ ...params })
        if (params.keyword === 'pending') return new Promise(resolve => pending.push(resolve))
        if (params.keyword === 'error') throw new Error('synthetic merchant picker failure')
        const total = params.keyword ? 1 : 45
        const start = (params.page - 1) * params.limit
        return { data: { data: Array.from({ length: Math.max(0, Math.min(params.limit, total - start)) }, (_, index) => ({
            id: start + index + 201, name: params.keyword ? 'Needle shop' : `Synthetic shop ${start + index + 1}`,
            contact_name: 'Synthetic contact', mobile: '18800000001', region_text: 'Fixture Province / Fixture City / Fixture District'
        })), total, current_page: params.page, per_page: params.limit } }
    }
}
const modules = new Map()
function load(file) {
    file = path.resolve(file); if (modules.has(file)) return modules.get(file)
    const original = fs.readFileSync(file, 'utf8')
    let source = original
    if (file.endsWith('.vue')) {
        const { descriptor } = compiler.parse(source, { filename: file })
        const compiled = compiler.compileScript(descriptor, { id: file, inlineTemplate: false })
        const template = compiler.compileTemplate({ id: file, filename: file, source: descriptor.template.content,
            compilerOptions: { bindingMetadata: compiled.bindings } })
        check(template.errors.length === 0, 'actual component template compiles')
        source = compiled.content + '\n' + template.code
    }
    const js = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const module = { exports: {} }
    const localRequire = key => {
        if (key === 'vue') return Vue
        if (key === '@element-plus/icons-vue') return { ArrowDown: Box, Calendar: Box, Refresh: Box, Search: Box }
        if (key === '../../api') return api
        if (key.includes('HsxTable')) return { default: Table }
        if (key.includes('Hsx') || key === './RegionSelect.vue' || key === './LeadTimestamp.vue' || key === './LeadSectionHeader.vue') return { default: Box }
        const target = key.startsWith('@/') ? path.join(root, 'admin/src', key.slice(2)) : path.resolve(path.dirname(file), key)
        return load(fs.existsSync(target) ? target : `${target}.ts`)
    }
    vm.runInNewContext(`(function(require,module,exports){${js}\n})`, { console, setTimeout, clearTimeout })(localRequire, module, module.exports)
    if (module.exports.default && module.exports.render) module.exports.default.render = module.exports.render
    modules.set(file, module.exports)
    return module.exports
}
const renderer = createRenderer({
    createElement: tag => ({ tag, children: [], props: {}, parent: null }), createText: text => ({ text, children: [], parent: null }), createComment: text => ({ comment: text, children: [], parent: null }),
    insert(node, parent, anchor) { if (node.parent) { const old = node.parent.children.indexOf(node); if (old >= 0) node.parent.children.splice(old, 1) }; node.parent = parent; const index = anchor ? parent.children.indexOf(anchor) : -1; if (index < 0) parent.children.push(node); else parent.children.splice(index, 0, node) },
    remove(node) { if (node.parent) { const index = node.parent.children.indexOf(node); if (index >= 0) node.parent.children.splice(index, 1) } },
    setText: (node, value) => { node.text = value }, setElementText: (node, value) => { node.text = value; node.children = [] }, patchProp: (node, key, _old, value) => { node.props[key] = value }, parentNode: node => node.parent, nextSibling: node => node.parent?.children[node.parent.children.indexOf(node) + 1] || null
})
const inventoryFile = path.join(root, 'niucloud/addon/hsx_lead/admin/views/components/InventoryPanel.vue')
const component = load(inventoryFile).default
const app = renderer.createApp(component)
for (const name of ['el-button', 'el-alert', 'el-popover', 'el-icon', 'el-input', 'el-radio', 'el-radio-group', 'el-tag', 'el-tooltip', 'el-date-picker', 'el-select', 'el-option', 'el-descriptions', 'el-descriptions-item']) app.component(name, Box)
app.config.warnHandler = () => {}
const tree = { tag: 'root', children: [], props: {} }
app.mount(tree)
const state = app._instance.setupState
async function flush() { await Promise.resolve(); await Promise.resolve(); await nextTick() }
const last = values => values[values.length - 1]
const text = node => [node.text || '', ...(node.children || []).map(text)].join(' ')
await flush()
check(inventoryCalls.length === 1 && merchantCalls.length === 0, 'initial inventory load does not fetch a truncated merchant list')
check(text(tree).includes('Fixture Province / Fixture City / Fixture District'), 'actual table merchant slot displays API region names')
state.beginRegion(); await state.applyRegion(); await flush()
check(state.query.province_id === '' && state.query.city_id === '' && state.query.district_id === '', 'applying an empty region draft normalizes zeros to unlimited filters')
check(!state.filtered, 'empty region draft does not falsely mark the inventory list as filtered')
state.beginMerchants(); await flush()
check(last(merchantCalls).picker === 1 && last(merchantCalls).limit === 20 && last(merchantCalls).page === 1, 'merchant picker requests a lean server page')
check(state.merchants.length === 20 && state.merchantTotal === 45, 'first merchant page is not claimed to contain all options')
await state.loadMerchantOptions(false); await flush()
check(last(merchantCalls).page === 2 && state.merchants.length === 40, 'load more appends the next merchant page')
state.merchantKeyword = 'Needle'; await state.searchMerchantsNow(); await flush()
check(last(merchantCalls).keyword === 'Needle' && last(merchantCalls).page === 1, 'merchant search is sent to server and resets pagination')
check(state.merchants.length === 1 && state.merchantTotal === 1, 'server search replaces rather than mixes earlier pages')
state.merchantDraft = 201; state.merchantSelection = state.merchants[0]; await state.applyMerchant(); await flush()
check(state.query.merchant_id === 201 && state.query.merchant_label === 'Needle shop' && last(inventoryCalls).merchant_id === 201, 'applying merchant updates inventory query and readable label')
state.query.page = 5; state.query.time_field = 'last_sync_at'; state.query.start_time = 100
state.beginRegion(); Object.assign(state.regionDraft, { province_id: 910000, city_id: 0, district_id: 0, province_name: 'Fixture Province', city_name: '', district_name: '' }); await state.applyRegion(); await flush()
check(state.query.page === 1 && state.query.merchant_id === '' && state.query.merchant_label === '', 'region change clears stale selected merchant and resets inventory page')
check(last(inventoryCalls).province_id === 910000 && !last(inventoryCalls).city_id && !last(inventoryCalls).district_id, 'province-only selection carries no stale descendants')
check(last(inventoryCalls).time_field === 'last_sync_at' && last(inventoryCalls).start_time === 100, 'region change retains independent time filters')
state.beginMerchants(); await flush()
check(last(merchantCalls).province_id === 910000 && !last(merchantCalls).city_id, 'merchant options use applied region rather than draft or ERP codes')
state.merchantKeyword = 'pending'; const oldRequest = state.loadMerchantOptions(true); await flush()
state.beginRegion(); Object.assign(state.regionDraft, { province_id: 920000, city_id: 920100, district_id: 920101, province_name: 'Other Province', city_name: 'Other City', district_name: 'Other District' }); await state.applyRegion(); await flush()
pending.shift()({ data: { data: [{ id: 999, name: 'Stale old-region merchant' }], total: 1, current_page: 1 } }); await oldRequest; await flush()
check(state.merchants.length === 0 && !state.merchantLoading && state.query.merchant_id === '', 'late old-region response cannot repopulate stale merchant options')
check(state.regionSummary === 'Other Province / Other City / Other District', 'applied regional chip shows readable cascade names')
await state.clearRegion(); await flush()
check(state.query.province_id === '' && state.query.city_id === '' && state.query.district_id === '' && !state.regionActive, 'clear region resets the entire hierarchy')
state.beginMerchants(); await flush(); const before = state.rows.length
state.merchantKeyword = 'error'; await state.searchMerchantsNow(); await flush()
check(state.merchantError.includes('synthetic merchant picker failure') && state.rows.length === before, 'picker failure is explicit without blanking inventory')
state.merchantKeyword = 'pending'; const afterUnmount = state.loadMerchantOptions(true); await flush(); app.unmount()
pending.shift()({ data: { data: [{ id: 888, name: 'Late after unmount' }], total: 1, current_page: 1 } }); await afterUnmount
check(!state.merchants.some(item => item.id === 888), 'unmounted panel ignores late remote options')
console.log(`PASS ${checks} inventory region UI checks; actual Vue setup/render and synthetic APIs only, no network/database or visual acceptance`)
