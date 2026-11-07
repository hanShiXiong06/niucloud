#!/usr/bin/env node
// Real PC correction SFC/action hook with isolated requests; no DB, production or printing.
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), admin = path.join(root, 'admin'), base = path.join(admin, 'src/addon/hsx_recycle')
const ts = require(path.join(admin, 'node_modules/typescript'))
const vue = require(path.join(admin, 'node_modules/vue'))
const compiler = require(path.join(admin, 'node_modules/@vue/compiler-sfc'))
const { renderToString } = require(path.join(admin, 'node_modules/@vue/server-renderer'))
let count = 0
const plain = value => value === undefined ? undefined : JSON.parse(JSON.stringify(value))
const eq = (actual, expected, message) => { assert.deepEqual(plain(actual), plain(expected), message); count++ }
const ok = (value, message) => { assert.ok(value, message); count++ }
const blocked = () => { throw Error('External effects are disabled in this fixture') }
function execute(source, filename, dependencies = {}, globals = {}) {
    const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const module = { exports: {} }
    vm.runInNewContext(code, { module, exports: module.exports, console: { ...console, warn() {} }, ...globals, require(id) {
        if (Object.prototype.hasOwnProperty.call(dependencies, id)) return dependencies[id]
        if (id.endsWith('.vue')) return { default: {} }
        throw Error('Unexpected import: ' + id)
    } }, { filename })
    return module.exports
}
const checkValue = execute(fs.readFileSync(path.join(base, 'utils/checkValue.ts'), 'utf8'), 'checkValue.ts')
const summaryUtil = execute(fs.readFileSync(path.join(base, 'components/device-entry/summaryUtil.ts'), 'utf8'), 'summaryUtil.ts', { '@/addon/hsx_recycle/utils/checkValue': checkValue })
const imeiValidation = execute(fs.readFileSync(path.join(base, 'components/device-entry/imeiValidation.ts'), 'utf8'), 'imeiValidation.ts')
const field = (key, extra = {}) => ({ field_key: key, field_name: key === 'battery' ? '电池健康度' : key, component: 'input', is_required: 0, extra_config: { summary_visible: 1 }, options: [], ...extra })
const schema = (fields = [field('battery', { default_value: '100' })], extra = {}) => ({ data: { template: { id: 501 }, groups: [{ fields }], schema_status: 'ready', ...extra } })
const device = (extra = {}) => ({
    id: 3036, site_id: 100005, order_id: 2527, status: 3, confirm_status: 0, pay_status: 0, downstream_erp_asset_id: 0,
    model: '苹果 iPhone 14 Pro', category_id: 8, imei: '353938205192374', imei2: '', sn: 'SN012345', user_sn: '',
    final_price: '2100.00', initial_price: '2200.00', check_uid: 9, price_uid: 7,
    info: { original_evidence: '不能整包提交', check_meta: { raw: '保留原始信息' } },
    info_correction: { allowed: true, reason: '', provenance: { initial_entry: { uid: 6, name: '首次录入员' }, checker: { uid: 9, name: '实际质检员' } }, summary: { battery: '96' } },
    ...extra,
})
const deferred = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { promise, resolve, reject } }
async function flush() { for (let i = 0; i < 10; i++) { await Promise.resolve(); await vue.nextTick() } }
const mockControl = name => vue.defineComponent({ name, inheritAttrs: false, props: ['modelValue', 'multiple', 'type'], setup(props, { slots }) {
    return () => vue.h('span', { 'data-control': name, 'data-value': JSON.stringify(props.modelValue), 'data-multiple': props.multiple === true ? '1' : '0', 'data-type': props.type || '' }, [slots.title?.(), slots.default?.(), slots.footer?.()])
} })
const summaryFilename = path.join(base, 'components/device-entry/CheckSummaryFields.vue')
const summaryDescriptor = compiler.parse(fs.readFileSync(summaryFilename, 'utf8'), { filename: summaryFilename }).descriptor
const realSummaryComponent = execute(compiler.compileScript(summaryDescriptor, { id: 'correction-summary', inlineTemplate: true }).content, summaryFilename, { vue, './WarrantyQueryInput.vue': { default: mockControl('warranty-input') } }).default
const filename = path.join(base, 'views/recycle_order/components/DeviceInfoCorrectionDialog.vue')
function dialog(options = {}) {
    const calls = [], notices = [], emitted = [], lifecycle = []
    const source = fs.readFileSync(filename, 'utf8'), { descriptor, errors } = compiler.parse(source, { filename })
    eq(errors, [], 'correction SFC parses')
    const script = compiler.compileScript(descriptor, { id: 'device-correction-test' })
    const compiledTemplate = compiler.compileTemplate({ filename, id: 'device-correction-test', source: descriptor.template.content, compilerOptions: { bindingMetadata: script.bindings } })
    eq(compiledTemplate.errors, [], 'correction template compiles')
    const render = execute(compiledTemplate.code, filename + ':render', { vue }).render
    const props = vue.reactive({ visible: true, deviceId: options.deviceId || 3036 })
    const runtimeVue = { ...vue, onBeforeUnmount: callback => lifecycle.push(callback) }
    const feedback = Object.fromEntries(['success', 'warning', 'error', 'info'].map(kind => [kind, text => notices.push([kind, String(text)])]))
    const component = execute(script.content, filename, {
        vue: runtimeVue,
        '@/addon/hsx_components/core': { HsxDialog: mockControl('HsxDialog'), useFeedback: () => feedback },
        '@/addon/hsx_recycle/api/recycle_order': {
            getDevice: async id => { calls.push(['get', id]); return options.getDevice ? options.getDevice(id) : { data: device() } },
            updateDevice: async (id, payload) => { calls.push(['update', id, plain(payload)]); return options.updateDevice ? options.updateDevice(id, payload) : { data: { changed: true } } },
        },
        '@/addon/hsx_recycle/api/recycle_device_model_dict': { getRecycleDeviceModelDictOptions: async input => { calls.push(['models', plain(input)]); return options.models ? options.models(input) : { data: [] } } },
        '@/addon/hsx_recycle/api/check_template': { getCheckTemplateSchema: async input => { calls.push(['schema', plain(input)]); return options.schema ? options.schema(input) : schema() } },
        '@/addon/hsx_recycle/components/device-entry/CheckSummaryFields.vue': { default: realSummaryComponent },
        '@/addon/hsx_recycle/components/device-entry/summaryUtil': summaryUtil,
        '@/addon/hsx_recycle/components/device-entry/imeiValidation': imeiValidation,
    }).default
    const scope = vue.effectScope()
    const state = scope.run(() => component.setup(props, { expose() {}, emit: (name, value) => emitted.push([name, plain(value)]) }))
    return { state, props, calls, notices, emitted, template: descriptor.template.content, async html() {
        const setup = vue.proxyRefs(state)
        const app = vue.createSSRApp({ setup: () => () => render({}, [], props, setup, {}, {}) })
        for (const name of ['el-alert', 'el-button', 'el-form', 'el-form-item', 'el-select', 'el-option', 'el-radio-group', 'el-radio', 'el-checkbox-group', 'el-checkbox', 'el-switch', 'el-input-number', 'el-input', 'el-empty']) app.component(name, mockControl(name))
        app.directive('loading', { getSSRProps: () => ({}) })
        app.config.warnHandler = () => {}
        return renderToString(app)
    }, dispose() { lifecycle.forEach(fn => fn()); scope.stop() } }
}
function updates(c) { return c.calls.filter(call => call[0] === 'update') }
function assertSafePatch(payload) {
    eq(payload.action, 'correct_info', 'dedicated correct_info action is used')
    const allowed = ['action', 'correction_reason', 'model', 'category_id', 'imei', 'imei2', 'sn', 'user_sn', 'summary']
    ok(Object.keys(payload).every(key => allowed.includes(key)), 'payload only contains allowed correction fields')
    for (const key of ['status', 'confirm_status', 'pay_status', 'check_uid', 'price_uid', 'initial_price', 'final_price', 'info', 'check_result', 'check_result_seller', 'member_id', 'site_id', 'order_id']) ok(!(key in payload), 'payload does not smuggle ' + key)
}
async function main() {
    const actionSource = fs.readFileSync(path.join(base, 'hooks/useDeviceRowActions.ts'), 'utf8')
    const icons = Object.fromEntries(['DocumentChecked', 'Edit', 'PriceTag', 'Check', 'Close', 'Switch', 'Printer'].map(name => [name, {}]))
    const { useDeviceRowActions } = execute(actionSource, 'useDeviceRowActions.ts', { '@element-plus/icons-vue': icons })
    const routed = []
    const actions = useDeviceRowActions({ checkDevice: blocked, correctDeviceInfo: row => routed.push(row), priceDevice: blocked, batchRecycleDevice: blocked, batchReturnDevice: blocked, transferConsignment: blocked, getVisibleDevicePrintActions: () => [], printDeviceByScene: blocked, findOrderStatus: () => 2 })
    for (const status of [3, 4, 7, 8]) {
        const row = device({ status }), selected = actions.getDeviceMoreActions(row).find(action => action.key === 'correct_info')
        ok(selected, 'pre-confirm status exposes correction: ' + status)
        selected.handler(); eq(routed[routed.length - 1], row, 'correction action opens the selected device')
    }
    for (const status of [0, 1, 2, 5, 6, 9]) ok(!actions.getDeviceMoreActions(device({ status })).some(action => action.key === 'correct_info'), 'other workflow state does not expose correction: ' + status)
    for (const flag of [{ confirm_status: 1 }, { confirm_status: 2 }, { confirm_status: '1' }, { pay_status: 1 }, { pay_status: '1' }, { downstream_erp_asset_id: 666 }, { downstream_erp_asset_id: '666' }]) {
        for (const status of [3, 4, 7, 8]) ok(!actions.getDeviceMoreActions(device({ status, ...flag })).some(action => action.key === 'correct_info'), 'confirmed/paid/ERP-held devices do not expose correction')
    }
    const noHandler = useDeviceRowActions({ getVisibleDevicePrintActions: () => [], findOrderStatus: () => 2 })
    ok(!noHandler.getDeviceMoreActions(device()).some(action => action.key === 'correct_info'), 'missing correction handler does not create a dead action')

    {
        const c = dialog(), s = c.state
        await flush()
        eq(c.calls.filter(call => call[0] === 'get'), [['get', 3036]], 'dialog reads actual selected device once')
        eq(s.ability.value.allowed, true, 'GET correction capability enables form')
        eq(s.summaryValues.value.battery, '96', 'existing battery comes from correction summary')
        s.form.user_sn = '补录SN'; s.reason.value = '  核对实机后修正  '
        s.form.status = 5; s.form.check_uid = 88; s.form.final_price = '1'; s.form.info = { forged: true }
        s.summaryValues.value.unconfigured = '不应发送'
        await s.save()
        eq(updates(c).length, 1, 'explicit save sends one correction')
        eq(updates(c)[0][2], { action: 'correct_info', correction_reason: '核对实机后修正', user_sn: '补录SN' }, 'unchanged identity and summaries are not resent')
        assertSafePatch(updates(c)[0][2])
        ok(c.emitted.some(item => item[0] === 'saved') && c.emitted.some(item => item[0] === 'update:visible' && item[1] === false), 'successful save emits refresh and close')
        c.dispose()
    }
    {
        const raw = device({ info_correction: { allowed: false, reason: '设备已经确认并交接ERP', provenance: {}, summary: {} } })
        const c = dialog({ getDevice: async () => ({ data: raw }) }), s = c.state
        await flush(); s.form.imei = 'SNABCDEF'; await s.save()
        eq(updates(c), [], 'GET allowed=false cannot be bypassed by modifying the form')
        eq(c.calls.filter(call => call[0] === 'schema').length, 0, 'blocked capability does not load editable schema')
        ok((await c.html()).includes('设备已经确认并交接ERP'), 'ability rejection reason is actually rendered')
        c.dispose()
    }
    {
        const raw = device(); delete raw.info_correction
        const c = dialog({ getDevice: async () => ({ data: raw }) }); await flush(); await c.state.save()
        eq(updates(c), [], 'missing backend capability fails closed')
        ok(c.state.ability.value.reason.includes('更新回收插件后端'), 'missing capability gives actionable explanation')
        c.dispose()
    }
    {
        const raw = device({ info_correction: { allowed: true, reason: '', provenance: {}, summary: {} } })
        const c = dialog({ getDevice: async () => ({ data: raw }), schema: async () => schema([field('battery', { default_value: '100', options: [{ value: 8, label: '100%' }], selection_mode: 'multiple' }), field('color', { default_value: '银色' })]) }), s = c.state
        await flush()
        eq(s.summaryValues.value.battery, '', 'unknown battery does not become template default 100')
        eq(s.summaryValues.value.color, '', 'correction does not inject other template defaults')
        eq(s.fields.value[0].component, 'input', 'dynamic input component is retained')
        const html = await c.html()
        eq((html.match(/历史未记录/g) || []).length, 2, 'unknown initial-entry/checker names are explicitly rendered as unrecorded')
        ok(html.includes('data-control="el-input"') && !html.includes('data-control="el-checkbox-group"'), 'real summary renderer keeps input despite old options/multiple')
        s.summaryValues.value.battery = '95'; await s.save()
        eq(updates(c)[0][2], { action: 'correct_info', correction_reason: '', summary: { battery: '95' } }, 'typed battery is sent as a scalar changed-summary patch only')
        assertSafePatch(updates(c)[0][2])
        c.dispose()
    }
    {
        const c = dialog({ schema: async () => schema([field('battery', { is_required: 1 }), field('legacy_required', { is_required: 1 })]) }), s = c.state
        await flush(); s.form.user_sn = 'ONLY-BASE-CHANGE'; await s.save()
        eq(updates(c).length, 1, 'same-category basic correction is not blocked by unchanged legacy missing required summary')
        eq(updates(c)[0][2], { action: 'correct_info', correction_reason: '', user_sn: 'ONLY-BASE-CHANGE' }, 'legacy missing values are not filled or resubmitted')
        c.dispose()
    }
    {
        const c = dialog({ schema: async () => schema([field('battery', { is_required: 1 })]) }), s = c.state
        await flush(); s.summaryValues.value.battery = ''; await s.save()
        eq(updates(c), [], 'clearing a changed required field is rejected')
        ok(c.notices.some(([kind, text]) => kind === 'warning' && text.includes('电池健康度')), 'required correction explains which field needs input')
        c.dispose()
    }
    {
        const c = dialog({
            models: async () => ({ data: [{ id: 9, node_name: '小米 17 Pro', model_full_name: '手机/小米/小米17系列/小米 17 Pro' }] }),
            schema: async input => input.category_id === 9 ? schema([field('battery', { is_required: 1, default_value: '100' }), field('color', { is_required: 1, default_value: '黑色' })]) : schema(),
        }), s = c.state
        await flush(); await s.searchModels(' 小米 ')
        eq(c.calls.find(call => call[0] === 'models')[1], { keyword: '小米' }, 'remote model search trims the query')
        s.form.category_id = 9; s.selectModel(); await flush()
        eq(s.form.model, '小米 17 Pro', 'model change uses selected leaf name')
        eq(s.summaryValues.value, { battery: '', color: '' }, 'new model starts blank rather than inheriting old summary/defaults')
        ok(c.calls.some(call => call[0] === 'schema' && call[1].category_id === 9), 'new category loads its own schema')
        await s.save(); eq(updates(c), [], 'new category must validate every required summary field')
        s.summaryValues.value.battery = '94'; await s.save(); eq(updates(c), [], 'partially completed new-category summary cannot save')
        s.summaryValues.value.color = '黑色'; await s.save()
        eq(updates(c).length, 1, 'fully supplied new-category summary can save')
        eq(updates(c)[0][2], { action: 'correct_info', correction_reason: '', model: '小米 17 Pro', category_id: 9, summary: { battery: '94', color: '黑色' } }, 'new category sends only changed identity plus its summary')
        assertSafePatch(updates(c)[0][2])
        c.dispose()
    }
    {
        const raw = device(), originalInfo = plain(raw.info)
        const c = dialog({ getDevice: async () => ({ data: raw }), schema: async () => { throw { msg: '摘要服务暂不可用' } } }), s = c.state
        await flush()
        eq(s.allowIdentityOnly.value, true, 'unchanged same-category summary permits explicit identity-only fallback')
        eq(s.summaryValues.value, { battery: '96' }, 'schema failure preserves the GET summary')
        ok((await c.html()).includes('本次仅保存基础资料，原设备摘要不变'), 'identity-only fallback is explained in actual dialog rendering')
        s.form.imei = 'SNNEWDEVICE01'; await s.save()
        eq(updates(c).length, 1, 'temporary schema failure does not block IMEI-only correction')
        eq(updates(c)[0][2], { action: 'correct_info', correction_reason: '', imei: 'SNNEWDEVICE01' }, 'fallback does not send an empty or old summary')
        assertSafePatch(updates(c)[0][2])
        eq(s.summaryValues.value, { battery: '96' }, 'identity fallback does not erase original summary in editor')
        eq(s.rawDevice.value.info, originalInfo, 'identity fallback never rewrites the raw info snapshot')
        c.dispose()
    }
    {
        let attempts = 0
        const c = dialog({ schema: async () => { if (++attempts === 1) throw { msg: '模板暂时不可用' }; return schema() } }), s = c.state
        await flush(); ok(s.schemaError.value.includes('模板暂时不可用'), 'schema failure has explicit inline error')
        ok(c.template.includes('@click="loadSchema"') && c.template.includes('重新加载摘要'), 'schema error exposes retry action')
        s.summaryValues.value.battery = '95'
        eq(s.allowIdentityOnly.value, false, 'unsaved summary change cannot take identity-only fallback')
        await s.save(); eq(updates(c), [], 'schema failure with edited summary requires retry instead of silently dropping edits')
        await s.loadSchema(); eq(s.schemaError.value, '', 'schema retry clears error')
        eq(s.summaryValues.value.battery, '95', 'schema retry preserves edited input')
        await s.save(); eq(updates(c).length, 1, 'successful retry permits correction save')
        c.dispose()
    }
    {
        const c = dialog({ schema: async () => { throw { msg: '新型号摘要暂不可用' } } }), s = c.state
        await flush(); s.modelOptions.value = [{ id: 9, node_name: '小米 17 Pro' }]
        s.form.category_id = 9; s.selectModel(); await flush()
        eq(s.allowIdentityOnly.value, false, 'category change cannot take identity-only schema-error fallback')
        await s.save(); eq(updates(c), [], 'new category with failed schema must retry before saving')
        ok(s.schemaError.value.includes('新型号摘要暂不可用'), 'new-category schema failure remains visible')
        c.dispose()
    }
    {
        const c = dialog({ updateDevice: async () => { throw { msg: '保存被后端拒绝' } } }), s = c.state
        await flush(); s.form.user_sn = '保留输入'; s.summaryValues.value.battery = '94'; await s.save()
        eq(s.form.user_sn, '保留输入', 'failed save retains basic edits')
        eq(s.summaryValues.value.battery, '94', 'failed save retains summary edits')
        eq(s.saving.value, false, 'failed save unlocks retry')
        ok(c.notices.some(([kind, text]) => kind === 'error' && text === '保存被后端拒绝'), 'failed save displays rejection reason')
        ok(!c.emitted.some(item => item[0] === 'update:visible' && item[1] === false), 'failed save does not close dialog')
        c.dispose()
    }
    {
        const wait = deferred(), c = dialog({ updateDevice: () => wait.promise }), s = c.state
        await flush(); s.form.user_sn = '等待保存'; const first = s.save()
        eq(s.saving.value, true, 'in-flight correction locks duplicate submission')
        await s.save(); eq(updates(c).length, 1, 'double click does not send a second correction')
        s.visible.value = false
        ok(!c.emitted.some(item => item[0] === 'update:visible' && item[1] === false), 'cancel is blocked while saving')
        wait.reject({ msg: '稍后重试' }); await first
        eq(s.saving.value, false, 'rejected pending save releases lock')
        eq(s.form.user_sn, '等待保存', 'pending failure retains entered data')
        c.dispose()
    }
    for (const outcome of ['success', 'error']) {
        const wait = deferred()
        const c = dialog({ getDevice: async id => ({ data: device({ id, model: id === 3036 ? '苹果 iPhone 14 Pro' : '小米 17 Pro', category_id: id === 3036 ? 8 : 9 }) }), updateDevice: () => wait.promise }), s = c.state
        await flush(); s.form.user_sn = '旧设备待保存'; const first = s.save()
        c.props.deviceId = 3037; await flush(); s.form.user_sn = '新设备输入保留'
        if (outcome === 'success') wait.resolve({ data: { changed: true } }); else wait.reject({ msg: '旧设备保存失败，不应干扰新窗口' })
        await first
        eq(updates(c)[0][1], 3036, 'in-flight request stays bound to original device')
        eq(s.form.id, 3037, 'stale save does not change current device: ' + outcome)
        eq(s.form.user_sn, '新设备输入保留', 'stale save preserves new-device input: ' + outcome)
        eq(s.saving.value, false, 'stale save releases lock: ' + outcome)
        ok(!c.emitted.some(item => item[0] === 'update:visible' && item[1] === false), 'stale save does not close new-device dialog: ' + outcome)
        ok(!c.notices.some(([kind]) => kind === 'success' || kind === 'error'), 'stale save feedback does not leak into new-device context: ' + outcome)
        eq(c.emitted.filter(item => item[0] === 'saved').length, outcome === 'success' ? 1 : 0, 'stale success refreshes list only; failed old save does not claim success')
        c.dispose()
    }
    {
        const wait = deferred(), c = dialog({ updateDevice: () => wait.promise }), s = c.state
        await flush(); s.form.user_sn = '关闭前保存'; const pending = s.save()
        c.props.visible = false; await flush(); const snapshot = plain(s.form)
        wait.resolve({ data: { changed: true } }); await pending
        eq(s.form, snapshot, 'save response after parent close does not repopulate or rewrite form')
        ok(!c.emitted.some(item => item[0] === 'update:visible'), 'old save does not emit another close after parent has closed')
        ok(!c.notices.some(([kind]) => kind === 'success'), 'save success after close does not toast into a different context')
        eq(c.emitted.filter(item => item[0] === 'saved').length, 1, 'closed old save can still notify list refresh')
        c.dispose()
    }
    {
        let attempts = 0
        const c = dialog({ getDevice: async () => { if (++attempts === 1) throw { msg: '设备接口未响应' }; return { data: device() } } }), s = c.state
        await flush(); ok(s.loadError.value.includes('设备接口未响应'), 'device load failure gives explicit reason')
        ok(c.template.includes('@click="loadDevice"') && c.template.includes('重新加载'), 'device load failure exposes retry action')
        await s.save(); eq(updates(c), [], 'failed capability load cannot save')
        await s.loadDevice(); eq(s.loadError.value, '', 'device reload clears error')
        eq(s.form.id, 3036, 'device reload restores correct record')
        c.dispose()
    }
    {
        const first = deferred(), second = deferred()
        const c = dialog({ getDevice: id => id === 3036 ? first.promise : second.promise }), s = c.state
        c.props.deviceId = 3037; await flush()
        second.resolve({ data: device({ id: 3037, model: '小米 17 Pro', category_id: 9 }) }); await flush()
        first.resolve({ data: device() }); await flush()
        eq(s.form.id, 3037, 'late old-device response cannot replace newly selected device')
        eq(s.form.model, '小米 17 Pro', 'new-device model remains current')
        c.dispose()
    }
    {
        const wait = deferred(), c = dialog({ getDevice: () => wait.promise }), s = c.state
        c.props.visible = false; await flush(); wait.resolve({ data: device() }); await flush()
        eq(s.form.id, 0, 'device response after close does not repopulate editor')
        eq(s.loading.value, false, 'close clears loading')
        c.dispose()
    }
    {
        const oldSchema = deferred(), c = dialog({ schema: () => oldSchema.promise }), s = c.state
        await flush(); c.props.visible = false; await flush(); oldSchema.resolve(schema([field('old_field')])); await flush()
        eq(s.fields.value, [], 'schema response after close does not repopulate fields')
        eq(s.schemaLoading.value, false, 'close clears schema spinner')
        c.dispose()
    }
    {
        const oldSchema = deferred()
        const c = dialog({ getDevice: async id => ({ data: device({ id, category_id: id === 3036 ? 8 : 9 }) }), schema: input => input.device_id === 3036 ? oldSchema.promise : schema([field('current_battery')]) }), s = c.state
        await flush(); c.props.deviceId = 3037; await flush()
        oldSchema.resolve(schema([field('old_battery')])); await flush()
        eq(s.form.id, 3037, 'schema race preserves new device')
        eq(s.fields.value.map(item => item.field_key), ['current_battery'], 'old schema cannot replace new-device fields')
        eq(s.schemaLoading.value, false, 'latest schema owns loading state')
        c.dispose()
    }
    console.log(`[通过] ${count} 项 PC 设备资料纠错隔离回归；真实 SFC/摘要渲染/行操作，不连接数据库、不打印、不请求生产。`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
