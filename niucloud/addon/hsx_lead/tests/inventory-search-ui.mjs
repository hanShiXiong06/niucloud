import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

// Actual Vue setup/render and read-only synthetic APIs. No ERP, browser or DB access.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const require = createRequire(path.join(root, 'admin/package.json'))
const Vue = require('vue'), compiler = require('@vue/compiler-sfc'), ts = require('typescript')
let checks = 0
const check = (value, label) => { assert.ok(value, label); checks++ }
const sourcePath = path.join(root, 'niucloud/addon/hsx_lead/admin/views/components/InventoryPanel.vue')
const unknown = Object.freeze({ id: 1, model: 'Synthetic source iPhone', merchant_name: 'Synthetic shop', price: '900.00',
    sale_status: 'unknown', sale_status_label: '状态待核实', stock_status_label: '库存待核实', is_sellable: false })
const available = Object.freeze({ ...unknown, id: 2, sale_status: 'on_sale', sale_status_label: '在售', stock_status_label: '有货 · 1 台', is_sellable: true })
const calls = [], pending = []
let mode = 'empty'
const api = {
    getInventory(params) {
        calls.push({ ...params })
        if (mode === 'hold') return new Promise(resolve => pending.push(resolve))
        if (mode === 'error') return Promise.reject(new Error('Synthetic inventory read failed'))
        const data = params.inventory_scope === 'all' ? [unknown] : mode === 'available' ? [available] : []
        return Promise.resolve({ data: { data, total: data.length } })
    },
    getMerchants: async () => ({ data: { data: [], total: 0 } })
}
const Box = Vue.defineComponent({ inheritAttrs: false, setup(_props, { attrs, slots }) {
    return () => Vue.h('box', attrs, [slots.reference?.(), slots.filters?.(), slots.default?.()])
} })
const Button = Vue.defineComponent({ inheritAttrs: false, setup(_props, { attrs, slots }) { return () => Vue.h('button', attrs, slots.default?.()) } })
const Table = Vue.defineComponent({ props: ['data', 'emptyText'], setup(props, { slots }) {
    return () => Vue.h('table', props.data.length ? props.data.flatMap(row => ['product', 'availability', 'updated'].flatMap(key => slots[key]?.({ row }) || [])) : Vue.h('empty', props.emptyText))
} })
const Drawer = Vue.defineComponent({ props: ['modelValue'], setup(props, { slots }) { return () => props.modelValue ? Vue.h('drawer', slots.default?.()) : null } })
const modules = new Map()
function load(file) {
    file = path.resolve(file)
    if (modules.has(file)) return modules.get(file)
    let source = fs.readFileSync(file, 'utf8')
    if (file.endsWith('.vue')) {
        const parsed = compiler.parse(source, { filename: file })
        check(parsed.errors.length === 0, 'actual component SFC parses')
        const script = compiler.compileScript(parsed.descriptor, { id: 'inventory-search', inlineTemplate: false })
        const template = compiler.compileTemplate({ id: 'inventory-search', filename: file, source: parsed.descriptor.template.content,
            compilerOptions: { bindingMetadata: script.bindings } })
        check(template.errors.length === 0, 'actual component template compiles')
        source = script.content + '\n' + template.code
    }
    const js = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const module = { exports: {} }
    function dependency(name) {
        if (name === 'vue') return Vue
        if (name === '../../api') return api
        if (name === '@element-plus/icons-vue') return { ArrowDown: Box, Refresh: Box, Search: Box }
        if (name.includes('HsxTable')) return { default: Table }
        if (name.includes('HsxDrawer')) return { default: Drawer }
        if (name.includes('Hsx') || ['./LeadFilterBar.vue', './LeadSectionHeader.vue', './RegionSelect.vue', './LeadTimestamp.vue'].includes(name)) return { default: Box }
        const target = name.startsWith('@/') ? path.join(root, 'admin/src', name.slice(2)) : path.resolve(path.dirname(file), name)
        return load(fs.existsSync(target) ? target : `${target}.ts`)
    }
    vm.runInNewContext(`(function(require,module,exports){${js}\n})`, { console, setTimeout, clearTimeout })(dependency, module, module.exports)
    if (module.exports.default && module.exports.render) module.exports.default.render = module.exports.render
    modules.set(file, module.exports)
    return module.exports
}
const renderer = Vue.createRenderer({
    createElement: tag => ({ tag, children: [], props: {}, parent: null }),
    createText: text => ({ text, children: [], parent: null }), createComment: comment => ({ comment, children: [], parent: null }),
    insert(node, parent, anchor) { if (node.parent) { const old = node.parent.children.indexOf(node); if (old >= 0) node.parent.children.splice(old, 1) }; node.parent = parent; const at = anchor ? parent.children.indexOf(anchor) : -1; if (at < 0) parent.children.push(node); else parent.children.splice(at, 0, node) },
    remove(node) { if (node.parent) { const i = node.parent.children.indexOf(node); if (i >= 0) node.parent.children.splice(i, 1) } },
    setText(node, value) { node.text = value }, setElementText(node, value) { node.text = value; node.children = [] },
    patchProp(node, key, _previous, value) { node.props[key] = value }, parentNode: node => node.parent,
    nextSibling: node => node.parent?.children[node.parent.children.indexOf(node) + 1] || null
})
const app = renderer.createApp(load(sourcePath).default)
for (const tag of ['el-alert', 'el-popover', 'el-icon', 'el-input', 'el-radio', 'el-radio-group', 'el-tag', 'el-descriptions', 'el-descriptions-item']) app.component(tag, Box)
app.component('el-button', Button); app.config.warnHandler = () => {}
const tree = { tag: 'root', children: [], props: {} }; app.mount(tree)
const state = app._instance.setupState
const label = '在全部来源资料中查找（含待核实）'
const text = node => [node.text || '', ...(node.children || []).map(text)].join(' ')
const nodes = node => [node, ...(node.children || []).flatMap(nodes)]
const fallbackButtons = () => nodes(tree).filter(node => node.tag === 'button' && text(node).trim() === label)
const last = () => calls.at(-1)
async function flush() { for (let i = 0; i < 6; i++) { await Promise.resolve(); await Vue.nextTick() } }
await flush()
check(last().inventory_scope === 'sellable', 'initial request retains safe default')
check(!state.canExpandSearch && fallbackButtons().length === 0, 'empty initial keyword does not add a fallback action')
await state.applyFilters({ keyword: '   ' }); await flush()
check(!state.canExpandSearch && fallbackButtons().length === 0, 'whitespace is not a search intent')

Object.assign(state.query, { keyword: 'iPhone 18', merchant_id: 7, merchant_label: 'Synthetic shop', province_id: 130000,
    city_id: 130100, district_id: 130102, time_field: 'last_sync_at', start_time: 1700000000, end_time: 1700100000,
    page: 8, limit: 10 })
await state.search(); await flush()
check(state.canExpandSearch && fallbackButtons().length === 1, 'successful zero-result keyword search exposes exactly one explicit fallback')
check(text(tree).includes('在售有货范围内没有匹配资料'), 'empty state identifies the narrow scope, not missing ERP data')
check(text(tree).includes('待核实记录不代表可售，也不参与匹配'), 'fallback explains uncertainty without claiming stock is saleable')
check(last().inventory_scope === 'sellable', 'fallback is not applied automatically')
const before = { ...last() }; state.query.page = 9; const priorCalls = calls.length
await fallbackButtons()[0].props.onClick(); await flush()
check(calls.length === priorCalls + 1 && last().inventory_scope === 'all' && last().page === 1, 'explicit click makes one all-scope request from first page')
for (const key of ['keyword', 'merchant_id', 'merchant_label', 'province_id', 'city_id', 'district_id', 'time_field', 'start_time', 'end_time', 'limit']) {
    check(last()[key] === before[key], `fallback preserves ${key}`)
}
check(state.inventoryScopeLabel === '全部快照（含不可售）', 'top scope chip accurately exposes the expanded scope')
check(!state.canExpandSearch && fallbackButtons().length === 0, 'all-scope results do not retain a redundant fallback')
check(state.rows.length === 1 && state.rows[0].is_sellable === false, 'unverified source projection remains non-sellable')
check(text(tree).includes('状态待核实') && text(tree).includes('库存待核实'), 'actual all-scope render preserves unknown labels')
check(state.inventoryTone(unknown) === 'warning', 'unverified source result does not receive a positive saleable color')
const noHiddenCalls = calls.length; await state.expandSearch(); await flush()
check(calls.length === noHiddenCalls, 'hidden fallback cannot be called to expand or repeat a read')

await state.applyFilters({ ...state.resetValues, keyword: '' }); await flush()
check(last().inventory_scope === 'sellable' && !state.canExpandSearch, 'clearing filters restores safe default')
mode = 'available'; await state.applyFilters({ keyword: 'Known stock' }); await flush()
check(state.total === 1 && !state.canExpandSearch && fallbackButtons().length === 0, 'positive saleable result does not show expand action')
mode = 'error'; await state.applyFilters({ keyword: 'Read error' }); await flush()
check(!!state.error && !state.canExpandSearch && fallbackButtons().length === 0, 'failed read is not presented as a zero-result search')
const erroredCalls = calls.length; await state.expandSearch(); await flush()
check(calls.length === erroredCalls, 'error state blocks programmatic fallback')
mode = 'hold'; const waiting = state.applyFilters({ keyword: 'Pending search' }); await flush()
check(state.loading && !state.canExpandSearch && fallbackButtons().length === 0, 'pending request does not expose an old zero total as finished')
const loadingCalls = calls.length; await state.expandSearch(); await flush()
check(calls.length === loadingCalls, 'pending request blocks fallback dispatch')
pending.shift()({ data: { data: [], total: 0 } }); await waiting; await flush()
check(!state.loading && state.canExpandSearch && fallbackButtons().length === 1, 'action appears after the successful empty response completes')
app.unmount()
console.log(`PASS ${checks} inventory search fallback UI checks; actual Vue setup/render and synthetic read-only APIs, no ERP, DB or browser mutation`)
