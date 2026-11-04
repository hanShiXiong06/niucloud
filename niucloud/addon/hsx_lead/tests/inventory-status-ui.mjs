import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

// Real component setup/render; only synthetic, already classified API projections.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const require = createRequire(path.join(root, 'admin/package.json'))
const Vue = require('vue'), compiler = require('@vue/compiler-sfc'), ts = require('typescript')
const { compile } = require('@vue/compiler-dom'), { renderToString } = require('@vue/server-renderer')
let checks = 0
const check = (condition, label) => { assert.ok(condition, label); checks++ }
const Box = Vue.defineComponent({ setup(_props, { slots }) { return () => Vue.h('box', [slots.reference?.(), slots.filters?.(), slots.default?.()]) } })
const Table = Vue.defineComponent({ props: ['data'], setup: (props, { slots }) => () => Vue.h('table', (props.data || []).map(row => Vue.h('tr', { 'data-row-id': row.id }, ['product', 'availability', 'updated'].map(key => Vue.h('td', { 'data-column': key }, slots[key]?.({ row }) || []))))) })
const input = [
    { id: 1, model: 'Synthetic available', sale_status: 'on_sale', sale_status_label: '在售', stock_status_label: '有货 2 台', quantity: '2', is_sellable: true },
    { id: 2, model: 'Synthetic delisted', sale_status: 'off_shelf', sale_status_label: '已下架', stock_status_label: '快照数量 1 台', quantity: '1', is_sellable: false },
    { id: 3, model: 'Synthetic zero', sale_status: 'sold_out', sale_status_label: '无可售库存', stock_status_label: '无货', quantity: '0', is_sellable: false },
    { id: 4, model: 'Synthetic unverified', sale_status: 'unknown', sale_status_label: '状态待核实', stock_status_label: '库存待核实', quantity: null, is_sellable: false },
    { id: 5, model: 'Synthetic sold', sale_state: 'sold', sale_status: 'sold_out', sale_status_label: '已售出', stock_status_label: '无货', quantity: null, is_sellable: false }
]
const inventoryCalls = []
const api = {
    async getInventory(params) {
        inventoryCalls.push({ ...params })
        const data = params.inventory_scope === 'all' ? input : params.inventory_scope === 'off_shelf' ? [input[1]] : params.inventory_scope === 'sold_out' ? [input[2], input[4]] : params.inventory_scope === 'unverified' ? [input[3]] : [input[0]]
        return { data: { data, total: data.length } }
    },
    async getMerchants() { return { data: { data: [], total: 0 } } }
}
const modules = new Map()
function load(file) {
    file = path.resolve(file)
    if (modules.has(file)) return modules.get(file)
    let source = fs.readFileSync(file, 'utf8')
    if (file.endsWith('.vue')) {
        const { descriptor, errors } = compiler.parse(source, { filename: file })
        check(!errors.length, 'component SFC parses')
        const script = compiler.compileScript(descriptor, { id: 'inventory-status', inlineTemplate: false })
        const template = compiler.compileTemplate({ id: 'inventory-status', filename: file, source: descriptor.template.content, compilerOptions: { bindingMetadata: script.bindings } })
        check(!template.errors.length, 'actual component template compiles')
        source = script.content + '\n' + template.code
    }
    const js = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const module = { exports: {} }
    function dependency(name) {
        if (name === 'vue') return Vue
        if (name === '@element-plus/icons-vue') return { ArrowDown: Box, Calendar: Box, Refresh: Box, Search: Box }
        if (name === '../../api') return api
        if (name.includes('HsxTable')) return { default: Table }
        if (name.includes('Hsx') || ['./RegionSelect.vue', './LeadTimestamp.vue', './LeadSectionHeader.vue'].includes(name)) return { default: Box }
        const target = name.startsWith('@/') ? path.join(root, 'admin/src', name.slice(2)) : path.resolve(path.dirname(file), name)
        return load(fs.existsSync(target) ? target : `${target}.ts`)
    }
    vm.runInNewContext(`(function(require,module,exports){${js}\n})`, { console, setTimeout, clearTimeout })(dependency, module, module.exports)
    if (module.exports.default && module.exports.render) module.exports.default.render = module.exports.render
    modules.set(file, module.exports)
    return module.exports
}
const renderer = Vue.createRenderer({
    createElement: tag => ({ tag, children: [], props: {}, parent: null }), createText: text => ({ text, children: [], parent: null }), createComment: text => ({ comment: text, children: [], parent: null }),
    insert(node, parent, anchor) { if (node.parent) { const old = node.parent.children.indexOf(node); if (old >= 0) node.parent.children.splice(old, 1) }; node.parent = parent; const at = anchor ? parent.children.indexOf(anchor) : -1; if (at < 0) parent.children.push(node); else parent.children.splice(at, 0, node) },
    remove(node) { if (node.parent) { const at = node.parent.children.indexOf(node); if (at >= 0) node.parent.children.splice(at, 1) } }, setText: (node, value) => { node.text = value }, setElementText: (node, value) => { node.text = value; node.children = [] }, patchProp: (node, key, _old, value) => { node.props[key] = value }, parentNode: node => node.parent, nextSibling: node => node.parent?.children[node.parent.children.indexOf(node) + 1] || null
})
const componentPath = path.join(root, 'niucloud/addon/hsx_lead/admin/views/components/InventoryPanel.vue')
check(fs.existsSync(componentPath), 'current plugin inventory component exists')
const app = renderer.createApp(load(componentPath).default)
for (const tag of ['el-button', 'el-alert', 'el-popover', 'el-icon', 'el-input', 'el-radio', 'el-radio-group', 'el-tag', 'el-tooltip', 'el-date-picker', 'el-select', 'el-option', 'el-descriptions', 'el-descriptions-item']) app.component(tag, Box)
app.config.warnHandler = () => {}
const tree = { tag: 'root', children: [], props: {} }
app.mount(tree)
const state = app._instance.setupState
async function flush() { for (let i = 0; i < 5; i++) { await Promise.resolve(); await Vue.nextTick() } }
const text = node => [node.text || '', ...(node.children || []).map(text)].join(' ')
const findNode = (node, predicate) => predicate(node) ? node : (node.children || []).map(child => findNode(child, predicate)).find(Boolean)
const availabilityText = id => text(findNode(tree, node => node.props?.['data-row-id'] === id)?.children.find(node => node.props?.['data-column'] === 'availability') || {})
const last = () => inventoryCalls[inventoryCalls.length - 1]
await flush()
check(last().inventory_scope === 'sellable' && state.query.inventory_scope === 'sellable', 'initial server request explicitly asks for on-sale stock')
check(state.rows.length === 1 && state.rows[0].id === 1, 'default page uses filtered server result, not a client-side pager filter')
check(text(tree).includes('在售') && text(tree).includes('有货 2 台'), 'sale and stock labels from API are actually rendered')
check(!text(tree).includes('待门店确认'), 'every status is no longer replaced with the same generic badge')
check(!state.filtered && state.inventoryScopeLabel === '在售有货', 'default has readable scope without duplicate applied-filter tags')
state.query.page = 5; state.beginInventoryScope(); state.inventoryScopeDraft = 'all'; await state.applyInventoryScope(); await flush()
check(last().inventory_scope === 'all' && last().page === 1 && state.rows.length === 5, 'all snapshot scope is explicit and resets pagination')
check(text(tree).includes('已下架') && text(tree).includes('无可售库存') && text(tree).includes('库存待核实'), 'all scope preserves distinct delisted zero and unknown statuses')
check(availabilityText(5).includes('已售出'), 'a source-confirmed sale renders as sold')
check(!availabilityText(2).includes('已售出') && !availabilityText(3).includes('已售出'), 'delisting and zero stock are not falsely rendered as completed sales')
check(state.inventoryTone(input[0]) === 'success' && state.inventoryTone(input[3]) === 'warning', 'only confirmed sellable rows get a positive status color')
state.beginInventoryScope(); state.inventoryScopeDraft = 'sold_out'; state.inventoryScopeOpened = false
check(last().inventory_scope === 'all' && state.query.inventory_scope === 'all', 'closing draft scope without applying does not change the query')
for (const scope of ['off_shelf', 'sold_out', 'unverified']) {
    state.beginInventoryScope(); state.inventoryScopeDraft = scope; await state.applyInventoryScope(); await flush()
    check(last().inventory_scope === scope && state.rows.length === (scope === 'sold_out' ? 2 : 1), `scope ${scope} is sent to the server`)
}
await state.applyFilters({ ...state.resetValues }); await flush()
check(state.query.inventory_scope === 'sellable' && last().inventory_scope === 'sellable', 'clear filters restores safe default, not all stock')
state.beginInventoryScope(); state.inventoryScopeDraft = 'injected-value'; await state.applyInventoryScope(); await flush()
check(last().inventory_scope === 'sellable', 'unknown UI scope cannot accidentally request all inventory')
state.beginInventoryScope(); state.inventoryScopeDraft = 'all'; await state.applyInventoryScope(); await state.applyFilters({ keyword: 'synthetic' }); await flush()
check(last().inventory_scope === 'all' && last().keyword === 'synthetic', 'independent keyword search keeps explicit inventory scope')
check(availabilityText(5).includes('已售出') && !availabilityText(3).includes('已售出'), 'row labels preserve sold-versus-zero evidence after filtering')
app.unmount()

console.log(`PASS ${checks} inventory status UI checks; real Vue setup/render, synthetic projections only, no network or business mutation`)
