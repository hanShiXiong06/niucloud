#!/usr/bin/env node
// Execute the real mobile SFC with controllable schema responses. No DB, printer or network.
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), app = path.join(root, 'site-uniapp')
const base = path.join(app, 'src/addon/hsx_recycle')
const ts = require(path.join(app, 'node_modules/typescript'))
const vue = require(path.join(app, 'node_modules/vue'))
const compiler = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
let count = 0
const plain = value => value === undefined ? undefined : JSON.parse(JSON.stringify(value))
const eq = (actual, expected, label) => { assert.deepEqual(plain(actual), plain(expected), label); count++ }
const ok = (actual, label) => { assert.ok(actual, label); count++ }
function runModule(source, filename, dependencies = {}, extra = {}) {
    const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const module = { exports: {} }
    vm.runInNewContext(code, { module, exports: module.exports, console: { ...console, warn() {} }, ...extra, require(id) {
        if (Object.prototype.hasOwnProperty.call(dependencies, id)) return dependencies[id]
        if (id.endsWith('.vue')) return {}
        throw Error('Unexpected import: ' + id)
    } }, { filename })
    return module.exports
}
const workflow = runModule(fs.readFileSync(path.join(base, 'utils/agentOrder.ts'), 'utf8'), 'agentOrder.ts')
const device = runModule(fs.readFileSync(path.join(base, 'utils/device.ts'), 'utf8'), 'device.ts', { './helper': { formatMoney: value => String(value) } })
const field = (key, extra = {}) => ({ field_key: key, field_name: key === 'battery' ? '电池健康度' : key, component: 'input', extra_config: { summary_visible: 1 }, options: [], ...extra })
const schema = (fields, extra = {}) => ({ data: { template: { id: 501, template_name: '测试模板' }, resolve: { template_id: 501 }, groups: [{ fields }], schema_status: 'ready', ...extra } })
const deferred = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { promise, resolve, reject } }
function component(loader = async () => schema([])) {
    const calls = [], toasts = [], lifecycle = []
    const filename = path.join(base, 'pages/order/components/SignOrderPopup.vue')
    const source = fs.readFileSync(filename, 'utf8')
    const { descriptor, errors } = compiler.parse(source, { filename }); eq(errors, [], 'SFC parses')
    const script = compiler.compileScript(descriptor, { id: 'mobile-summary-test' })
    const template = compiler.compileTemplate({ filename, id: 'mobile-summary-test', source: descriptor.template.content, compilerOptions: { bindingMetadata: script.bindings } })
    eq(template.errors, [], 'template compiles')
    const blocked = () => { throw Error('Backend writes and printing disabled in this fixture') }
    const mod = runModule(script.content, filename, {
        vue: { ...vue, onBeforeUnmount: callback => lifecycle.push(callback) },
        '@/addon/hsx_recycle/api/order': { createAgentOrder: blocked, getOrderDetail: blocked, updateOrder: blocked, getDeviceModelDictChildren: async () => ({ data: [] }), searchDeviceModelDictOptions: async () => ({ data: [] }) },
        '@/addon/hsx_recycle/api/check-template': { getCheckTemplateSchema: async params => { calls.push(plain(params)); return loader(params, calls.length) } },
        '@/addon/hsx_recycle/utils/agentOrder': workflow,
        '@/addon/hsx_recycle/utils/device': device,
    }, { uni: { showToast: toast => toasts.push(plain(toast)), showModal: blocked, hideKeyboard() {} }, setTimeout: () => 1, clearTimeout() {} })
    const props = vue.reactive({ visible: true, mode: 'sign', orderId: 99, deviceList: [] })
    const scope = vue.effectScope()
    const state = scope.run(() => mod.default.setup(props, { expose() {}, emit() {} }))
    state.editVisible.value = true
    Object.assign(state.editForm.value, { model: '苹果 iPhone 14 Pro', imei: '353938205192374', category_id: 8, category_path: [1, 2, 7, 8] })
    return { state, props, calls, toasts, source, template: descriptor.template.content, dispose() { lifecycle.forEach(fn => fn()); scope.stop() } }
}
async function flush() { for (let i = 0; i < 4; i++) await vue.nextTick() }
async function main() {
    // Battery remains visible regardless of configured position; frontend must not invent a lower cap.
    for (const batteryPosition of [6, 10, 12]) {
        const fields = Array.from({ length: 12 }, (_, index) => field(index + 1 === batteryPosition ? 'battery' : 'field_' + (index + 1), index + 1 === batteryPosition ? { component: 'number', unit: '%' } : {}))
        fields.splice(2, 0, field('not_summary', { extra_config: { summary_visible: 0 } }))
        const c = component(async () => schema(fields)), s = c.state
        await s.loadSummaryFields()
        eq(c.calls, [{ category_id: 8 }], 'schema uses selected model ID')
        eq(s.editForm.value.summary_fields.length, 12, 'configured summaries are not truncated')
        eq(s.editForm.value.summary_fields.map(item => item.field_key), fields.filter(item => item.extra_config.summary_visible === 1).map(item => item.field_key), 'configured field order is preserved')
        eq(s.editForm.value.summary_fields[batteryPosition - 1].field_key, 'battery', 'battery remains at configured position ' + batteryPosition)
        eq(s.editForm.value.summary_fields[batteryPosition - 1].component, 'number', 'battery numeric metadata retained')
        eq(s.editForm.value.check_template_id, 501, 'template binding retained')
        eq(s.summaryError.value, '', 'successful load has no error')
        eq(s.summaryLoading.value, false, 'successful load ends loading')
        c.dispose()
    }
    {
        const fields = [field('color', { component: 'select', default_value: 'silver', options: [{ value: 'silver', name: '银色' }, { value: 'black', label: '黑色' }] }), field('battery', { component: 'number', unit: '%', default_value: 100, is_required: 1 }), field('parts', { component: 'checkbox', selection_mode: 'multiple', default_value: ['box'] }), field('switch', { component: 'switch' })]
        const c = component(async () => schema(fields)), s = c.state
        s.editForm.value.summary_values = { battery: '96', parts: 'box' }
        await s.loadSummaryFields()
        eq(s.editForm.value.summary_values.battery, '96', 'existing entered battery is not overwritten by defaults')
        eq(s.editForm.value.summary_values.color, 'silver', 'configured default selection applied')
        eq(s.editForm.value.summary_values.parts, ['box'], 'multiple values normalized')
        eq(s.editForm.value.summary_values.switch, false, 'switch empty default stays false')
        eq(s.editForm.value.summary_fields[0].options, [{ value: 'silver', label: '银色' }, { value: 'black', label: '黑色' }], 'option values and display text retained')
        s.editForm.value.summary_values.battery = ''
        s.saveEdit()
        eq(s.devices.value.length, 0, 'required battery is checked rather than silently omitted')
        ok(c.toasts.some(toast => toast.title.includes('电池健康度')), 'required battery shows actionable feedback')
        s.editForm.value.summary_values.battery = '95'
        s.saveEdit()
        eq(s.devices.value[0].summary_values.battery, '95', 'saving keeps entered battery')
        const override = s.collectSummaryValues(fields, { battery: 0, color: '', parts: [], switch: false })
        eq(override, { battery: 0, switch: false }, 'collecting summaries preserves zero and false while dropping empty entries')
        const input = c.template.match(/<u-input\b[^>]*v-model="editForm\.summary_values\[field\.field_key\]"[^>]*>/)
        ok(input && input[0].includes(':type='), 'summary input has explicit numeric/text keyboard selection')
        c.dispose()
    }
    // Component is authoritative: historical options/selection_mode must not turn an input into tags or an array.
    for (const [key, configuredComponent, defaultValue, enteredValue] of [
        ['battery', 'input', '100', '95'],
        ['battery', 'number', 100, '95'],
        ['condition_note', 'input', '正常', '人工补充说明'],
        ['operator_note', 'textarea', '旧备注', '本次备注'],
        ['activation_lock', 'switch', false, true],
    ]) {
        const configured = field(key, {
            component: configuredComponent, selection_mode: 'multiple', default_value: defaultValue,
            options: [{ value: 'old', label: '旧选项，不应决定组件' }],
        })
        const c = component(async () => schema([configured])), s = c.state
        await s.loadSummaryFields()
        const loaded = s.editForm.value.summary_fields[0]
        eq(loaded.component, configuredComponent, 'configured component metadata retained: ' + configuredComponent)
        eq(s.isOptionSummaryField(loaded), false, 'residual options do not select tag renderer: ' + configuredComponent)
        eq(s.isMultiSummaryField(loaded), false, 'residual multiple mode does not create array: ' + configuredComponent)
        eq(s.isSwitchSummaryField(loaded), configuredComponent === 'switch', 'switch renderer follows component only')
        eq(s.editForm.value.summary_values[key], defaultValue, 'non-choice default stays scalar: ' + configuredComponent)
        s.editForm.value.summary_values[key] = enteredValue
        s.saveEdit()
        eq(s.devices.value.length, 1, 'non-choice field saves normally: ' + configuredComponent)
        eq(s.devices.value[0].summary_values[key], enteredValue, 'entered scalar retained rather than option key/array: ' + configuredComponent)
        eq(s.collectSummaryValues(s.editForm.value.summary_fields, s.devices.value[0].summary_values), { [key]: enteredValue }, 'request summary retains typed input: ' + configuredComponent)
        ok(c.template.includes('v-if="isOptionSummaryField(field)"'), 'template tag branch uses authoritative component helper')
        ok(c.template.includes('v-else-if="isSwitchSummaryField(field)"'), 'template switch branch uses component helper')
        c.dispose()
    }
    for (const [configuredComponent, mode, expectedMultiple, defaultValue, expectedValue] of [
        ['radio', 'multiple', false, 'one', 'one'],
        ['radio', 'single', false, 'one', 'one'],
        ['checkbox', 'single', true, 'one', ['one']],
        ['checkbox', 'multiple', true, ['one'], ['one']],
        ['select', 'multiple', true, 'one', ['one']],
        ['select', 'single', false, 'one', 'one'],
    ]) {
        const c = component(async () => schema([field('configured_choice', {
            component: configuredComponent, selection_mode: mode, default_value: defaultValue,
            options: [{ value: 'one', label: '选项一' }, { value: 'two', label: '选项二' }],
        })])), s = c.state
        await s.loadSummaryFields()
        const loaded = s.editForm.value.summary_fields[0]
        eq(s.isOptionSummaryField(loaded), true, 'choice component uses options renderer: ' + configuredComponent + '/' + mode)
        eq(s.isMultiSummaryField(loaded), expectedMultiple, 'multiple selection follows component contract: ' + configuredComponent + '/' + mode)
        eq(s.editForm.value.summary_values.configured_choice, expectedValue, 'choice defaults use expected scalar/array shape')
        c.dispose()
    }
    {
        const c = component(async (_params, attempt) => {
            if (attempt === 1) throw { msg: '模板接口暂不可用' }
            return schema([field('battery', { component: 'number', default_value: 100 })])
        }), s = c.state
        s.editForm.value.summary_values = { battery: '94' }
        await s.loadSummaryFields()
        ok(s.summaryError.value.includes('模板接口暂不可用'), 'load failure exposes actual cause')
        eq(s.summaryLoading.value, false, 'failed load ends loading')
        s.saveEdit()
        eq(s.devices.value.length, 0, 'failed template load cannot silently save an incomplete summary')
        eq(s.editVisible.value, true, 'failed load retains editing dialog')
        eq(s.editForm.value.summary_values.battery, '94', 'failed load retains typed values')
        ok(c.template.includes('summaryError') && c.template.includes('loadSummaryFields') && /重试|重新加载/.test(c.template), 'inline error provides retry action')
        await s.loadSummaryFields()
        eq(s.summaryError.value, '', 'retry success clears previous error')
        eq(s.editForm.value.summary_values.battery, '94', 'retry does not erase typed battery')
        s.saveEdit()
        eq(s.devices.value.length, 1, 'successful retry permits explicit save')
        c.dispose()
    }
    {
        const wait = deferred(), c = component(() => wait.promise), s = c.state
        const loading = s.loadSummaryFields()
        eq(s.summaryLoading.value, true, 'in-flight load is visible')
        s.saveEdit()
        eq(s.devices.value.length, 0, 'saving while schema pending is blocked')
        eq(s.editVisible.value, true, 'loading does not close editor')
        wait.resolve(schema([field('battery')]))
        await loading
        eq(s.summaryLoading.value, false, 'pending load settles')
        c.dispose()
    }
    {
        const c = component(async () => schema([field('hidden', { extra_config: { summary_visible: 0 } })])), s = c.state
        await s.loadSummaryFields()
        eq(s.summaryError.value, '', 'valid template with no configured summaries is not a transport error')
        eq(s.editForm.value.summary_fields, [], 'unconfigured summaries are not invented')
        s.saveEdit()
        eq(s.devices.value.length, 1, 'valid zero-summary template still allows basic device entry')
        ok(/未配置.*摘要|未设置.*摘要/.test(c.template), 'zero-summary case has explanatory copy')
        c.dispose()
    }
    {
        const c = component(async () => schema([], { schema_status: 'empty', schema_message: '请检查型号模板字段' })), s = c.state
        await s.loadSummaryFields()
        eq(s.summaryError.value, '', 'empty template does not add a new hard gate')
        ok(s.summaryEmptyMessage.value.includes('请检查型号模板字段'), 'empty template retains server explanation')
        ok(s.summaryEmptyMessage.value.includes('可继续录入基础信息'), 'empty template explicitly explains allowed next step')
        eq(s.editForm.value.summary_fields, [], 'empty template does not invent summary fields')
        s.saveEdit()
        eq(s.devices.value.length, 1, 'empty template still permits basic device entry')
        c.dispose()
    }
    for (const status of ['missing', 'invalid']) {
        const c = component(async () => schema([], { schema_status: status, schema_message: '请检查型号模板字段' })), s = c.state
        await s.loadSummaryFields()
        ok(s.summaryError.value.includes('请检查型号模板字段'), 'backend ' + status + ' status shows server explanation')
        s.saveEdit()
        eq(s.devices.value.length, 0, 'backend ' + status + ' schema cannot masquerade as unconfigured summary')
        c.dispose()
    }
    for (const broken of [{ data: null }, { data: { template: null, groups: [] } }, { data: { template: { id: 501 }, groups: 'bad' } }]) {
        const c = component(async () => broken), s = c.state
        await s.loadSummaryFields()
        ok(s.summaryError.value.length > 0, 'malformed schema is explicit rather than silently empty')
        c.dispose()
    }
    {
        const old = deferred(), newer = deferred(), c = component((_params, attempt) => attempt === 1 ? old.promise : newer.promise), s = c.state
        const first = s.loadSummaryFields()
        s.editForm.value.category_id = 9
        const second = s.loadSummaryFields()
        newer.resolve(schema([field('new_battery', { component: 'number' })], { template: { id: 502 }, resolve: { template_id: 502 } }))
        await second
        old.resolve(schema([field('old_battery')]))
        await first
        eq(s.editForm.value.summary_fields.map(item => item.field_key), ['new_battery'], 'old model response cannot replace latest model summaries')
        eq(s.editForm.value.check_template_id, 502, 'old model response cannot replace latest template ID')
        eq(s.summaryLoading.value, false, 'latest completed load owns loading state')
        c.dispose()
    }
    {
        const old = deferred(), newer = deferred(), c = component((_params, attempt) => attempt === 1 ? old.promise : newer.promise), s = c.state
        const first = s.loadSummaryFields()
        s.editForm.value.category_id = 9
        const second = s.loadSummaryFields()
        old.reject({ msg: '旧型号请求失败' })
        await first
        eq(s.summaryError.value, '', 'old model failure does not replace new model loading state')
        eq(s.summaryLoading.value, true, 'old finalizer cannot clear newer pending spinner')
        newer.resolve(schema([field('battery')]))
        await second
        eq(s.summaryError.value, '', 'current model success remains clean')
        c.dispose()
    }
    {
        const wait = deferred(), c = component(() => wait.promise), s = c.state
        const loading = s.loadSummaryFields()
        s.closeEdit()
        wait.resolve(schema([field('battery')]))
        await loading
        eq(s.editVisible.value, false, 'closed editor remains closed')
        eq(s.editForm.value.summary_fields, [], 'closed editor ignores late schema response')
        eq(s.summaryLoading.value, false, 'closing ends loading state')
        c.dispose()
    }
    {
        const wait = deferred(), c = component(() => wait.promise), s = c.state
        const loading = s.loadSummaryFields()
        c.props.visible = false
        await flush()
        wait.reject(new Error('关闭后的失败'))
        await loading
        eq(s.summaryError.value, '', 'parent popup close ignores late request failure')
        eq(s.summaryLoading.value, false, 'parent close releases spinner')
        c.dispose()
    }
    console.log(`[通过] ${count} 项移动设备摘要隔离回归；真实SFC、可控异步接口，不连接数据库、不打印、不修改生产。`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
