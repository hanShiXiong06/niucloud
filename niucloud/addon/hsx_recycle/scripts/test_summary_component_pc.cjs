#!/usr/bin/env node
// Execute real PC SFC scripts/rendering and reading mapping with isolated API stubs.
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), admin = path.join(root, 'admin')
const base = path.join(admin, 'src/addon/hsx_recycle/components/device-entry')
const mirror = path.join(root, 'niucloud/addon/hsx_recycle/admin/components/device-entry')
const ts = require(path.join(admin, 'node_modules/typescript'))
const vue = require(path.join(admin, 'node_modules/vue'))
const compiler = require(path.join(admin, 'node_modules/@vue/compiler-sfc'))
const { renderToString } = require(path.join(admin, 'node_modules/@vue/server-renderer'))
let count = 0
const plain = value => value === undefined ? undefined : JSON.parse(JSON.stringify(value))
const eq = (actual, expected, message) => { assert.deepEqual(plain(actual), plain(expected), message); count++ }
const ok = (value, message) => { assert.ok(value, message); count++ }
const blocked = () => { throw Error('External effects are disabled in this fixture') }
function execute(source, filename, dependencies = {}) {
    const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const module = { exports: {} }
    vm.runInNewContext(code, { module, exports: module.exports, console, require(id) {
        if (Object.prototype.hasOwnProperty.call(dependencies, id)) return dependencies[id]
        if (id.endsWith('.vue')) return { default: {} }
        throw Error('Unexpected import: ' + id)
    } }, { filename })
    return module.exports
}
const readings = execute(fs.readFileSync(path.join(base, 'deviceReadings.ts'), 'utf8'), 'deviceReadings.ts')
const field = (component, extra = {}) => ({ field_key: 'battery', field_name: '电池健康度', component, selection_mode: 'multiple', options: [{ value: 471, label: '95' }], ...extra })
function compile(name, inlineTemplate = false) {
    const filename = path.join(base, name), source = fs.readFileSync(filename, 'utf8')
    eq(source, fs.readFileSync(path.join(mirror, name), 'utf8'), name + ' source/publish mirror match')
    const { descriptor, errors } = compiler.parse(source, { filename })
    eq(errors, [], name + ' SFC parses')
    return { source, script: compiler.compileScript(descriptor, { id: 'pc-component-test', inlineTemplate }), filename }
}
const controlNames = ['el-select', 'el-option', 'el-radio-group', 'el-radio', 'el-checkbox-group', 'el-checkbox', 'el-switch', 'el-input-number', 'el-input', 'el-empty']
const mockControl = name => vue.defineComponent({
    name, inheritAttrs: false, props: ['modelValue', 'multiple', 'type'],
    setup(props, { slots }) { return () => vue.h('span', { 'data-control': name, 'data-value': JSON.stringify(props.modelValue), 'data-multiple': props.multiple === true ? '1' : '0', 'data-type': props.type || '' }, slots.default?.()) }
})
async function renderField(f, value = '95') {
    const c = compile('CheckSummaryFields.vue', true)
    const component = execute(c.script.content, c.filename, { vue, './WarrantyQueryInput.vue': { default: mockControl('warranty-input') } }).default
    const app = vue.createSSRApp(component, { fields: [f], modelValue: { battery: value }, imei: '', brand: '' })
    controlNames.forEach(name => app.component(name, mockControl(name)))
    return renderToString(app)
}
function setupComponent(name, props, dependencies = {}) {
    const c = compile(name), lifecycle = []
    const runtimeVue = { ...vue, onMounted: callback => lifecycle.push(callback) }
    const component = execute(c.script.content, c.filename, {
        vue: runtimeVue, 'element-plus': { ElMessage: { warning: blocked, success: blocked, error: blocked, info: blocked }, ElMessageBox: {} },
        '@element-plus/icons-vue': {}, ...dependencies
    }).default
    const scope = vue.effectScope()
    const state = scope.run(() => component.setup(props, { expose() {}, emit() {} }))
    return { state, dispose() { scope.stop() } }
}
async function main() {
    for (const [component, expected, value] of [
        ['input', 'el-input', '95'], ['number', 'el-input-number', 95], ['textarea', 'el-input', '95'], ['switch', 'el-switch', false],
        ['radio', 'el-radio-group', 471], ['checkbox', 'el-checkbox-group', [471]], ['select', 'el-select', [471]]
    ]) {
        const html = await renderField(field(component), value)
        ok(html.includes(`data-control="${expected}"`), component + ' component wins over stale options/multiple')
        if (['input', 'number', 'textarea', 'switch'].includes(component)) {
            ok(!/data-control="el-(select|checkbox-group|radio-group)"/.test(html), component + ' cannot become a choice component')
        }
        if (component === 'select') ok(html.includes('data-multiple="1"'), 'multiple select really binds multiple')
        if (component === 'textarea') ok(html.includes('data-type="textarea"'), 'textarea keeps configured input mode')
    }
    const selectSingle = await renderField(field('select', { selection_mode: 'single' }), 471)
    ok(selectSingle.includes('data-multiple="0"'), 'single select remains scalar')
    const radioMany = await renderField(field('radio', { options: Array.from({ length: 5 }, (_, index) => ({ value: index, label: String(index) })) }), 1)
    ok(radioMany.includes('data-control="el-select"') && radioMany.includes('data-multiple="0"'), 'radio with many options may use dropdown but is always single')
    for (const [component, mode, expected] of [['input', 'multiple', '95'], ['number', 'multiple', 95], ['textarea', 'multiple', '95'], ['radio', 'multiple', 471], ['select', 'single', 471], ['select', 'multiple', [471]], ['checkbox', 'single', [471]]]) {
        const row = { summary_fields: [field(component, { selection_mode: mode })], summary_values: {} }
        readings.prefillDeviceSummary(row, { battery_health: '95' })
        eq(row.summary_values.battery, expected, component + '/' + mode + ' reading preserves scalar or maps real option correctly')
    }
    const manualRow = { summary_fields: [field('input')], summary_values: { battery: '96' } }
    readings.prefillDeviceSummary(manualRow, { battery_health: '95' })
    eq(manualRow.summary_values.battery, '96', 'local reading does not overwrite manual battery input')
    const nonmatch = { summary_fields: [field('select')], summary_values: {} }
    readings.prefillDeviceSummary(nonmatch, { battery_health: '99' })
    eq(nonmatch.summary_values.battery, undefined, 'unmatched option does not invent an ID')

    const dialogFields = [field('input', { field_key: 'input' }), field('number', { field_key: 'number' }), field('textarea', { field_key: 'textarea' }), field('switch', { field_key: 'switch' }), field('radio', { field_key: 'radio' }), field('checkbox', { field_key: 'checkbox' }), field('select', { field_key: 'multi_select' }), field('select', { field_key: 'single_select', selection_mode: 'single' })]
    const props = vue.reactive({ visible: false, fields: dialogFields, values: {}, templateName: '', deviceTitle: '', imei: '', loading: false, prefilledKeys: [] })
    const dialog = setupComponent('CheckSummaryDialog.vue', props, { './summaryUtil': { validateSummaryRequired: () => '' } })
    props.visible = true
    await vue.nextTick()
    eq(dialog.state.localValues.value, { input: '', number: '', textarea: '', switch: '', radio: '', checkbox: [], multi_select: [], single_select: '' }, 'dialog initializes according to component, not stale selection mode')
    dialog.dispose()

    let schemaFields = []
    const list = setupComponent('DeviceEntryList.vue', vue.reactive({ devices: [], orderId: '', autoAppend: true }), {
        '@/addon/hsx_recycle/api/recycle_order': {}, '@/addon/hsx_recycle/api/recycle_device_model_dict': {},
        '@/addon/hsx_recycle/api/check_template': { getCheckTemplateSchema: async () => ({ data: { template: { id: 1 }, resolve: { template_id: 1 }, groups: [{ fields: schemaFields }] } }) },
        './useLocalDevice': { useLocalDevice: () => ({ fetching: vue.ref(false), readWarnings: vue.ref([]), fetchConnected: blocked, mapToRow: blocked, describeError: blocked, startAuto: blocked, stopAuto() {} }) },
        '@/addon/hsx_components/core': {}, './deviceReadings': readings,
        './summaryUtil': { validateSummaryRequired: () => '' }, './deviceUtil': {}, './imeiValidation': {}, '@/addon/hsx_recycle/api/order_config': {}
    })
    schemaFields = dialogFields.map(f => ({ ...f, default_value: f.component === 'switch' ? false : '95', extra_config: { summary_visible: 1 } }))
    const row = { category_id: 8, summary_values: {} }
    eq(await list.state.loadCheckTemplate(row), true, 'real PC template loader succeeds')
    eq(row.summary_values, { input: '95', number: '95', textarea: '95', switch: false, radio: '95', checkbox: ['95'], multi_select: ['95'], single_select: '95' }, 'PC defaults keep input/number/radio scalar and real multi choice arrays')
    eq(row.check_template_id, 1, 'template binding remains intact')
    list.dispose()
    eq(fs.readFileSync(path.join(base, 'deviceReadings.ts'), 'utf8'), fs.readFileSync(path.join(mirror, 'deviceReadings.ts'), 'utf8'), 'reading mapping publish mirror matches')
    console.log(`[通过] ${count} 项 PC 摘要组件类型回归；真实 SFC/SSR/读取映射，不调用业务 API。`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
