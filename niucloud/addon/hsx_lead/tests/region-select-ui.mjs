/** Actual RegionSelect Vue setup/render with synthetic area responses only.
 * Select is a prop/event contract stub: real ElementPlus Teleport/click-outside
 * behavior belongs to the separate browser regression, not to this test. */
import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const require = createRequire(path.join(root, 'admin/package.json'))
const Vue = require('vue'), compiler = require('@vue/compiler-sfc'), ts = require('typescript')
const { h, ref, defineComponent, createRenderer, nextTick } = Vue
let checks = 0
const check = (ok, label) => { assert.ok(ok, label); checks++ }
const file = path.join(root, 'niucloud/addon/hsx_lead/admin/views/components/RegionSelect.vue')
const inventory = fs.readFileSync(path.join(path.dirname(file), 'InventoryPanel.vue'), 'utf8')
check(/<RegionSelect\b[^>]*:teleported="false"/.test(inventory), 'the nested inventory region filter explicitly opts out of Teleport')
for (const name of ['MerchantPanel.vue']) {
    const source = fs.readFileSync(path.join(path.dirname(file), name), 'utf8')
    check(/<RegionSelect\b[^>]*\bv-model="area"/.test(source) && !/<RegionSelect\b[^>]*:teleported="false"/.test(source), 'ordinary drawer forms retain default Teleport behavior')
}

const areas = new Map([
    [0, [{ id: 910000, name: 'Fixture Province A' }, { id: 920000, name: 'Fixture Province B' }, { id: 930000, name: 'Slow Province' }, { id: 940000, name: 'Fast Province' }]],
    [910000, [{ id: 910100, name: 'Fixture City A' }, { id: 910200, name: 'Fixture City A2' }]],
    [910100, [{ id: 910101, name: 'Fixture District A' }]],
    [910200, [{ id: 910201, name: 'Fixture District A2' }]],
    [920000, [{ id: 920100, name: 'Fixture City B' }]],
    [920100, [{ id: 920101, name: 'Fixture District B' }]],
    [930000, [{ id: 930100, name: 'Slow City' }]],
    [940000, [{ id: 940100, name: 'Fast City' }]]
])
const calls = [], pending = new Map()
const api = { getAreaListByPid(pid) {
    calls.push(Number(pid))
    return pending.get(Number(pid))?.promise || Promise.resolve({ data: areas.get(Number(pid)) || [] })
} }
function defer(pid) {
    let resolve, reject
    const promise = new Promise((ok, fail) => { resolve = ok; reject = fail })
    const item = { promise, resolve, reject }; pending.set(pid, item); return item
}
const { descriptor, errors } = compiler.parse(fs.readFileSync(file, 'utf8'), { filename: file })
check(errors.length === 0, 'actual RegionSelect SFC parses')
const script = compiler.compileScript(descriptor, { id: file, inlineTemplate: false })
const template = compiler.compileTemplate({ id: file, filename: file, source: descriptor.template.content, compilerOptions: { bindingMetadata: script.bindings } })
check(template.errors.length === 0, 'actual RegionSelect template compiles')
const source = ts.transpileModule(script.content + '\n' + template.code, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const module = { exports: {} }
vm.runInNewContext(`(function(require,module,exports){${source}\n})`, { console })(key => {
    if (key === 'vue') return Vue
    if (key === '@/app/api/sys') return api
    if (key.includes('useLeadUi')) return { errorText: (cause, fallback) => cause?.message || fallback }
    throw new Error(`Unexpected dependency: ${key}`)
}, module, module.exports)
const RegionSelect = module.exports.default; RegionSelect.render = module.exports.render

const renderer = createRenderer({
    createElement: tag => ({ tag, props: {}, children: [], parent: null }), createText: text => ({ text, children: [], parent: null }), createComment: text => ({ comment: text, children: [], parent: null }),
    insert(node, parent, anchor) { if (node.parent) { const index = node.parent.children.indexOf(node); if (index >= 0) node.parent.children.splice(index, 1) }; node.parent = parent; const index = anchor ? parent.children.indexOf(anchor) : -1; if (index < 0) parent.children.push(node); else parent.children.splice(index, 0, node) },
    remove(node) { if (node.parent) { const index = node.parent.children.indexOf(node); if (index >= 0) node.parent.children.splice(index, 1) } },
    setText: (node, text) => { node.text = text }, setElementText: (node, text) => { node.text = text; node.children = [] }, patchProp: (node, key, _old, value) => { node.props[key] = value },
    parentNode: node => node.parent, nextSibling: node => node.parent?.children[node.parent.children.indexOf(node) + 1] || null
})
const text = node => [node.text || '', ...(node.children || []).map(text)].join(' ')
async function flush() { for (let i = 0; i < 4; i++) { await Promise.resolve(); await nextTick() } }
const activeApps = new Set()
function mount(initial = {}, teleported) {
    const model = ref(initial), emissions = [], selects = []
    const Select = defineComponent({ props: ['modelValue', 'teleported', 'disabled', 'loading', 'placeholder'], emits: ['update:modelValue', 'change'], setup(props, { emit, slots }) {
        selects.push({ props, emit })
        return () => h('select-contract', { value: props.modelValue, teleported: props.teleported, disabled: props.disabled, loading: props.loading }, slots.default?.())
    } })
    const Option = defineComponent({ props: ['value', 'label'], setup(props) { return () => h('option-contract', { value: props.value }, props.label) } })
    const Host = defineComponent({ setup() { return () => h(RegionSelect, { modelValue: model.value, ...(teleported === undefined ? {} : { teleported }), 'onUpdate:modelValue': value => { emissions.push(value); model.value = value } }) } })
    const app = renderer.createApp(Host)
    app.component('el-select', Select); app.component('el-option', Option); app.component('el-button', defineComponent({ setup(_props, { slots }) { return () => h('button-contract', slots.default?.()) } }))
    app.config.warnHandler = message => { throw new Error(`Unexpected Vue warning: ${message}`) }
    const tree = { tag: 'root', children: [], props: {} }; app.mount(tree); activeApps.add(app)
    return { app, tree, model, emissions, selects, state: app._instance.subTree.component.setupState,
        async choose(level, value) { selects[level].emit('update:modelValue', value); selects[level].emit('change', value); await flush() },
        stop() { app.unmount(); activeApps.delete(app) } }
}

try {
    const ordinary = mount(); await flush()
    check(ordinary.selects.length === 3 && ordinary.selects.every(item => item.props.teleported === true), 'all three selectors default to Teleport for ordinary forms')
    check(ordinary.selects[1].props.disabled === true && ordinary.selects[2].props.disabled === true, 'empty parent disables city and district selectors')
    check(ordinary.emissions.length === 0 && calls.includes(0), 'initialization reads only synthetic area data without writing parent state')
    ordinary.stop()

    const nested = mount({ province_id: '910000', city_id: '910100', district_id: '910101' }, false); await flush()
    check(nested.selects.every(item => item.props.teleported === false), 'false is explicitly forwarded to province city and district dropdowns')
    check(nested.state.ids.join(',') === '910000,910100,910101' && nested.selects.every(item => typeof item.props.modelValue === 'number'), 'existing string ids normalize to numeric rendered selector values')
    check(text(nested.tree).includes('Fixture Province A') && text(nested.tree).includes('Fixture City A') && text(nested.tree).includes('Fixture District A'), 'initial cascade loads readable labels for all existing levels')
    check(nested.emissions.length === 0, 'initial cascade readback emits no unintended parent update')
    await nested.choose(0, 920000)
    check(nested.model.value.province_id === 920000 && nested.model.value.city_id === 0 && nested.model.value.district_id === 0, 'province-only selection is emitted immediately and clears old descendants')
    check(nested.model.value.province_name === 'Fixture Province B' && nested.model.value.city_name === '' && nested.model.value.district_name === '', 'province change clears old descendant labels as well as ids')
    check(nested.selects[1].props.disabled === false && nested.selects[2].props.disabled === true && nested.state.options[1][0].id === 920100, 'new province enables its own cities, not old district choices')
    await nested.choose(1, 920100)
    check(nested.model.value.city_id === 920100 && nested.model.value.city_name === 'Fixture City B' && nested.model.value.district_id === 0, 'city-only selection is valid before choosing a district')
    check(nested.selects[2].props.disabled === false && nested.state.options[2][0].id === 920101, 'city change fetches the corresponding district options')
    await nested.choose(2, 920101)
    check(nested.model.value.district_id === 920101 && nested.model.value.district_name === 'Fixture District B', 'district v-model and readable name reach the parent')
    await nested.choose(1, undefined)
    check(nested.model.value.province_id === 920000 && nested.model.value.city_id === 0 && nested.model.value.district_id === 0 && nested.state.options[2].length === 0, 'clearing city retains province but clears district data')
    await nested.choose(0, undefined)
    check(nested.model.value.province_id === 0 && nested.model.value.city_id === 0 && nested.model.value.district_id === 0 && nested.state.options[1].length === 0, 'clearing province emits unlimited scope with no stale descendants')

    const beforeExternal = nested.emissions.length
    nested.model.value = { province_id: 910000, city_id: 910200, district_id: 910201 }; await flush()
    check(nested.state.ids.join(',') === '910000,910200,910201' && nested.state.options[2][0].name === 'Fixture District A2', 'external model replacement restores the complete corresponding cascade')
    check(nested.emissions.length === beforeExternal, 'external parent update does not loop back as a new selection event')
    nested.model.value = {}; await flush()
    check(nested.state.ids.every(value => value === undefined) && nested.selects[1].props.disabled === true && nested.selects[2].props.disabled === true, 'external reset restores all three controls to empty')

    const slow = defer(930000), fast = defer(940000)
    await nested.choose(0, 930000)
    check(nested.selects[1].props.loading === true && nested.state.options[1].length === 0, 'pending city request has explicit loading without stale options')
    await nested.choose(0, 940000)
    fast.resolve({ data: areas.get(940000) }); await flush()
    check(nested.state.options[1][0].id === 940100 && nested.selects[1].props.loading === false, 'latest province response populates its own city options')
    slow.resolve({ data: areas.get(930000) }); await flush()
    check(nested.state.options[1].length === 1 && nested.state.options[1][0].id === 940100 && nested.model.value.province_id === 940000, 'late previous-province response cannot overwrite latest options or selection')
    pending.clear()
    const late = defer(930000); await nested.choose(0, 930000); await nested.choose(0, undefined)
    late.resolve({ data: areas.get(930000) }); await flush()
    check(nested.state.options[1].length === 0 && nested.selects[1].props.disabled === true && !nested.state.loading[1], 'clearing province invalidates an in-flight old city response')
    pending.clear()
    const failed = defer(920000); await nested.choose(0, 920000)
    failed.reject(new Error('Synthetic area unavailable')); await flush()
    check(nested.state.error === 'Synthetic area unavailable' && !nested.state.loading[1], 'area failure is visible and does not leave an indefinite loading state')
    pending.clear(); await nested.state.initialise(); await flush()
    check(nested.state.error === '' && nested.state.options[1][0].id === 920100 && nested.model.value.province_id === 920000, 'retry restores the selected cascade without losing province-only parent input')
    const disposed = defer(930000); await nested.choose(0, 930000); const oldOptions = nested.state.options[1]
    nested.stop(); disposed.resolve({ data: areas.get(930000) }); await flush()
    check(nested.state.options[1] === oldOptions && nested.state.options[1].length === 0, 'unmounted component ignores late area completion')
    pending.clear()
    console.log(`PASS ${checks} RegionSelect Vue setup/render and synthetic area contract checks; no network, database, ERP or notification; native ElementPlus click-outside is NOT claimed by this suite.`)
} finally {
    for (const app of activeApps) app.unmount()
}
