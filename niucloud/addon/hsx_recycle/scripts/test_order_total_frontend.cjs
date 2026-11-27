#!/usr/bin/env node
// Actual Vue SFC logic with isolated requests; never calls a real DB, printer or API.
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), admin = path.join(root, 'admin')
const base = path.join(admin, 'src/addon/hsx_recycle')
const ts = require(path.join(admin, 'node_modules/typescript'))
const vue = require(path.join(admin, 'node_modules/vue'))
const compiler = require(path.join(admin, 'node_modules/@vue/compiler-sfc'))
const { renderToString } = require(path.join(admin, 'node_modules/@vue/server-renderer'))
let checks = 0
const plain = value => value === undefined ? undefined : JSON.parse(JSON.stringify(value))
const eq = (actual, expected, message) => { assert.deepEqual(plain(actual), plain(expected), message); checks++ }
const ok = (condition, message) => { assert.ok(condition, message); checks++ }
const deferred = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { promise, resolve, reject } }
const flush = async () => { for (let i = 0; i < 8; i++) { await Promise.resolve(); await vue.nextTick() } }
const forbidden = () => { throw Error('Real external effects are forbidden') }
const control = name => vue.defineComponent({ name, inheritAttrs: false, props: { modelValue: null, disabled: Boolean, loading: Boolean }, setup(props, { slots }) {
    return () => vue.h('span', { 'data-control': name, 'data-value': JSON.stringify(props.modelValue), 'data-disabled': String(Boolean(props.disabled)), 'data-loading': String(Boolean(props.loading)) }, [slots.header?.(), slots.quantity?.(), slots.default?.(), slots.footer?.()])
} })
function execute(source, filename, dependencies) {
    const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const module = { exports: {} }
    vm.runInNewContext(code, { module, exports: module.exports, console: { ...console, error() {}, warn() {} }, window: { innerWidth: 1280, addEventListener() {}, removeEventListener() {}, setTimeout, clearTimeout }, setTimeout, clearTimeout, require(id) {
        if (Object.prototype.hasOwnProperty.call(dependencies, id)) return dependencies[id]
        if (id.endsWith('.vue')) return { default: control(path.basename(id, '.vue')) }
        throw Error('Unexpected dependency: ' + id)
    } }, { filename })
    return module.exports
}
function sfc(relative, props, dependencies = {}) {
    const filename = path.join(base, relative), source = fs.readFileSync(filename, 'utf8')
    const { descriptor, errors } = compiler.parse(source, { filename }); eq(errors, [], relative + ' parses')
    const script = compiler.compileScript(descriptor, { id: 'order-total-fixture' })
    const template = compiler.compileTemplate({ source: descriptor.template.content, filename, id: 'order-total-fixture', compilerOptions: { bindingMetadata: script.bindings } })
    eq(template.errors, [], relative + ' template compiles')
    const lifecycle = []
    const runtimeVue = { ...vue, onMounted: fn => lifecycle.push(fn), onBeforeUnmount() {} }
    const component = execute(script.content, filename, { vue: runtimeVue, ...dependencies }).default
    const reactiveProps = vue.reactive(props), emitted = [], scope = vue.effectScope()
    const state = scope.run(() => component.setup(reactiveProps, { expose() {}, emit: (event, payload) => emitted.push([event, plain(payload)]) }))
    const render = execute(template.code, filename + ':render', { vue }).render
    return { state, props: reactiveProps, emitted, source, template: descriptor.template.content, lifecycle, async html() {
        const app = vue.createSSRApp({ setup: () => () => render({}, [], reactiveProps, vue.proxyRefs(state), {}, {}) })
        for (const name of ['el-input-number', 'el-input', 'el-button', 'el-radio-group', 'el-radio-button', 'el-icon', 'el-tag', 'el-tooltip', 'el-switch', 'el-alert', 'el-cascader', 'el-form']) app.component(name, control(name))
        app.config.warnHandler = () => {}
        return renderToString(app)
    }, dispose() { scope.stop() } }
}
function addOrder(options = {}) {
    const calls = [], messages = []
    const feedback = Object.fromEntries(['success', 'warning', 'error', 'info'].map(kind => [kind, text => messages.push([kind, String(text)])]))
    const c = sfc('views/recycle_order/components/AddOrderDialog.vue', { visible: true }, {
        '@/addon/hsx_components/core': { HsxDialog: control('HsxDialog'), useFeedback: () => feedback },
        'element-plus': { ElMessageBox: { confirm: async () => true } },
        '@element-plus/icons-vue': Object.fromEntries(['Aim', 'Loading', 'Shop', 'Tickets', 'Van'].map(key => [key, control(key)])),
        '@/addon/hsx_recycle/api/recycle_order': {
            createRecycleOrder: async data => { calls.push(['create', plain(data)]); return options.create ? options.create(data) : { code: 1, data: { id: 501, order_no: 'R501' } } },
            updateRecycleOrder: async (...args) => { calls.push(['update', ...plain(args)]); return { code: 1 } },
        },
        '@/addon/hsx_recycle/components/device-entry/deviceUtil': { normalizeDevice: row => ({ imei: row.imei }) },
        '@/addon/hsx_recycle/components/device-entry/imeiValidation': { validateDeviceEntryImeis: () => '' },
    })
    return { ...c, calls, messages }
}
const row = (extra = {}) => ({ imei: 'ABC123', model: '苹果 iPhone Air', category_id: 8, summary_fields: [], summary_values: {}, ...extra })
function entryList(options = {}) {
    const calls = [], messages = [], props = { devices: options.devices || [row()], orderId: options.orderId || 501, ensureOrder: options.ensureOrder, autoAppend: true, entryReady: options.ready !== false, maxDeviceCount: options.limit }
    const message = Object.fromEntries(['success', 'warning', 'error', 'info'].map(kind => [kind, text => messages.push([kind, String(text)])]))
    const apis = { addOrderDevice: async (id, payload) => { calls.push(['add', id, plain(payload)]); return options.add ? options.add(id, payload) : { code: 1, data: { device_id: 99 } } }, updateOrderDevice: forbidden, deleteOrderDevice: async () => ({ code: 1 }) }
    const local = { fetching: vue.ref(false), readWarnings: vue.ref([]), fetchConnected: async () => { calls.push(['fetch']); return options.local || [] }, mapToRow: value => value, describeError: error => error.message, startAuto: handler => { calls.push(['auto', handler]) }, stopAuto: () => calls.push(['stop']) }
    const c = sfc('components/device-entry/DeviceEntryList.vue', props, {
        'element-plus': { ElMessage: message, ElMessageBox: { confirm: async () => true } },
        '@element-plus/icons-vue': Object.fromEntries(['Plus', 'Connection', 'Download', 'Link', 'QuestionFilled'].map(key => [key, control(key)])),
        '@/addon/hsx_recycle/api/recycle_order': apis,
        '@/addon/hsx_recycle/api/recycle_device_model_dict': Object.fromEntries(['bindRecycleDeviceModelAlias', 'getRecycleDeviceModelDictChildren', 'getRecycleDeviceModelDictOptions', 'getRecycleDeviceModelDictTree', 'resolveRecycleDeviceModelAlias'].map(key => [key, async () => ({ data: [] })])),
        '@/addon/hsx_recycle/api/check_template': { getCheckTemplateSchema: forbidden },
        './useLocalDevice': { useLocalDevice: () => local },
        '@/addon/hsx_components/core': { HsxDataArchive: control('HsxDataArchive') },
        './deviceReadings': { localReadingArchive: value => ({ local: value }), prefillDeviceSummary: () => {}, recordModelMatch() {} },
        './summaryUtil': { validateSummaryRequired: () => '' },
        './deviceUtil': { normalizeDevice: row => ({ imei: row.imei, category_id: row.category_id }), buildUpdatePayload: row => row },
        './imeiValidation': { deviceImeiError: value => value && value.length >= 6 ? '' : '串号错误' },
        '@/addon/hsx_recycle/api/order_config': { getQuoteUserOptions: forbidden },
    })
    return { ...c, calls, messages }
}
async function main() {
    {
        const c = addOrder(), s = c.state
        eq(s.form.value.count, 1, 'total defaults to one')
        eq(s.confirmedTotal.value, 0, 'not confirmed before user action')
        await s.confirmTotal(); eq(c.calls, [], 'missing customer does not create a draft')
        ok(c.messages.some(item => item[1] === '请先选择客户'), 'missing customer gets a clear message')
        await assert.rejects(() => s.ensureDraftOrder(), /确认本单总台数/); checks++
        s.form.value.member_id = 75; s.form.value.count = 10
        await s.confirmTotal()
        eq(c.calls[0][1].count, 10, 'confirmed total sent to existing count field')
        eq(c.calls[0][1].draft_device_entry, true, 'confirmation creates the existing draft type')
        eq(c.calls[0][1].devices, [], 'total confirmation never invents placeholder devices')
        eq(s.confirmedTotal.value, 10, 'persisted total becomes the entry limit')
        eq(s.draftOrder.value.id, 501, 'successful confirmation retains actual draft ID')
        eq(await s.ensureDraftOrder(), 501, 'saving reuses confirmed draft')
        await s.confirmTotal(); eq(c.calls.filter(call => call[0] === 'create').length, 1, 'confirmed number cannot create another order')
        const html = await c.html(); ok(html.includes('已锁定'), 'locked total is visible')
        ok(html.includes('data-control="el-input-number" data-value="10" data-disabled="true"'), 'confirmed total input is disabled')
        s.handleClosed(); eq(s.form.value.count, 1, 'next dialog resets total to default one'); eq(s.confirmedTotal.value, 0, 'closing clears old total lock')
        c.dispose()
    }
    for (const count of [0, -1, 1.5, 10001, undefined, NaN]) {
        const c = addOrder(); c.state.form.value.member_id = 75; c.state.form.value.count = count
        await c.state.confirmTotal(); eq(c.calls, [], 'invalid total is not submitted: ' + count); c.dispose()
    }
    {
        const c = addOrder(); c.state.form.value.member_id = 75; c.state.form.value.count = 10000
        await c.state.confirmTotal(); eq(c.calls[0][1].count, 10000, 'existing backend upper bound is retained'); c.dispose()
    }
    {
        const pending = deferred(), c = addOrder({ create: () => pending.promise }), s = c.state
        s.form.value.member_id = 75; s.form.value.count = 10
        const a = s.confirmTotal(), b = s.confirmTotal()
        eq(c.calls.length, 1, 'double confirmation sends only one create request')
        eq(s.draftCreating.value, true, 'creating state is explicit')
        s.closeDialog(); s.dialogVisible.value = false
        eq(c.emitted, [], 'close is blocked while draft result is pending')
        const html = await c.html(); ok(html.includes('data-control="el-input-number" data-value="10" data-disabled="true"'), 'input is locked during confirmation request')
        pending.resolve({ code: 1, data: { id: 502, order_no: 'R502' } }); await Promise.all([a, b])
        eq(s.draftOrder.value.id, 502, 'both confirmation calls use the same persisted order'); c.dispose()
    }
    {
        const pending = deferred(), c = addOrder({ create: () => pending.promise }), s = c.state
        s.form.value.member_id = 75; s.form.value.count = 10
        const a = s.confirmTotal(), b = s.confirmTotal(); pending.reject(Error('测试创建失败'))
        eq(await Promise.all([a, b]), [undefined, undefined], 'all duplicate callers settle safely on failure')
        eq(s.draftOrder.value.id, '', 'failure does not fake a quantity lock')
        eq(s.form.value.count, 10, 'failure preserves entered total')
        eq(s.draftCreating.value, false, 'failure allows retry')
        eq(c.messages.filter(item => item[0] === 'error').length, 1, 'failed shared request only reports one error'); c.dispose()
    }
    {
        const pending = deferred(), c = addOrder({ create: () => pending.promise }), s = c.state
        s.form.value.member_id = 75; s.form.value.count = 10
        const previous = s.confirmTotal(); c.props.visible = false; await flush(); s.handleClosed()
        c.props.visible = true; s.form.value.member_id = 76; s.form.value.count = 3
        pending.resolve({ code: 1, data: { id: 503, order_no: 'R503' } }); await previous
        eq(s.draftOrder.value.id, '', 'old create callback does not bind a new dialog to old customer')
        eq(s.form.value.count, 3, 'old create callback does not overwrite new quantity')
        ok(c.emitted.some(item => item[0] === 'success'), 'real old draft still triggers list refresh'); c.dispose()
    }
    {
        const c = entryList({ ready: false }), s = c.state, before = plain(c.props.devices)
        await s.saveDeviceRow(c.props.devices[0], 0); await s.readLocalDevices(); s.toggleAutoLocal(true)
        eq(await s.applyLocalDevice({ imei: 'OTHER123' }), { status: 'blocked', matched: false }, 'background local callback cannot bypass quantity confirmation')
        eq(s.addDeviceRow(), false, 'unconfirmed total blocks extra rows')
        eq(c.props.devices, before, 'blocked actions preserve original row')
        ok(!c.calls.some(call => ['add', 'fetch', 'auto'].includes(call[0])), 'unconfirmed actions never call create/save/local bridge')
        ok(c.template.includes(':save-disabled="!entryReady"'), 'row save button receives disabled state'); c.dispose()
    }
    {
        const c = entryList({ limit: 1 }), s = c.state
        await s.saveDeviceRow(c.props.devices[0], 0)
        eq(c.calls.filter(call => call[0] === 'add').length, 1, 'confirmed total permits first real save')
        eq(c.props.devices.length, 1, 'saving final allowed row does not append an extra blank row')
        eq(s.canAddDeviceRow.value, false, 'add action disabled at quantity limit')
        eq(s.addDeviceRow(), false, 'programmatic add also enforces confirmed count'); c.dispose()
    }
    {
        const pending = deferred(), c = entryList({ limit: 1, devices: [row(), row({ imei: 'SECOND123' })], add: () => pending.promise }), s = c.state
        const first = s.saveDeviceRow(c.props.devices[0], 0); await flush()
        await s.saveDeviceRow(c.props.devices[1], 1)
        eq(c.calls.filter(call => call[0] === 'add').length, 1, 'in-flight new row reserves its place against concurrent saves')
        pending.resolve({ code: 1, data: { device_id: 44 } }); await first; c.dispose()
    }
    {
        const c = entryList(), s = c.state
        await s.saveDeviceRow(c.props.devices[0], 0)
        eq(c.props.devices.length, 2, 'old unlimited caller keeps automatic blank-row append')
        eq(s.addDeviceRow(), true, 'old caller without quantity prop keeps add behavior'); c.dispose()
    }
    {
        const c = entryList({ limit: 1, devices: [row({ imei: '', model: '' })] }), s = c.state
        eq((await s.applyLocalDevice({ imei: 'LOCAL111' })).status, 'applied', 'local reading can fill the allowed blank row')
        eq((await s.applyLocalDevice({ imei: 'LOCAL222' })).status, 'limit', 'next connected device cannot overflow total')
        eq(c.props.devices.length, 1, 'local reading does not insert an overflow row')
        eq(c.props.devices[0].imei, 'LOCAL111', 'overflow read does not overwrite previous device'); c.dispose()
    }
    {
        const c = sfc('views/recycle_order/components/DeviceConfirmDialog.vue', { visible: true, deviceList: [], orderId: 55, totalCount: 10 }, {
            '@/addon/hsx_components/core': { HsxDialog: control('HsxDialog'), useFeedback: () => ({ warning() {} }) },
            '@/addon/hsx_recycle/components/device-entry/deviceUtil': { normalizeDevice: row => row },
            '@/addon/hsx_recycle/components/device-entry/imeiValidation': { validateDeviceEntryImeis: () => '' },
        })
        c.state.rows.value = [row({ id: 1, saved: true })]
        eq(c.state.displayTotalCount.value, 10, 'existing sign dialog uses known order total rather than loaded row count')
        const html = await c.html()
        ok(html.includes('data-control="el-input-number" data-value="10" data-disabled="true"'), 'existing order total is read-only: ' + html)
        c.props.totalCount = 0; ok(!c.template.includes('createRecycleOrder'), 'existing order sign dialog never creates another draft'); c.dispose()
    }
    const actionSource = fs.readFileSync(path.join(base, 'hooks/useRecycleOrderActions.ts'), 'utf8')
    ok(actionSource.includes('currentOrderTotal.value = Number.isInteger(count)'), 'action captures total from clicked order, not a later page lookup')
    const listSource = fs.readFileSync(path.join(base, 'views/recycle_order/list.vue'), 'utf8')
    ok(listSource.includes(':total-count="currentOrderTotal"'), 'existing sign dialog receives total snapshot')
    const cardSource = fs.readFileSync(path.join(base, 'components/device-entry/DeviceEntryCard.vue'), 'utf8')
    ok(cardSource.includes(':disabled="saveDisabled"'), 'actual card save control implements optional disabled prop')
    console.log(`[通过] ${checks} 项 PC 总台数前置确认隔离回归；真实 SFC，无数据库、真实API或打印。`)
}
main().catch(error => { console.error(error.stack || error); process.exitCode = 1 })
