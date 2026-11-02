#!/usr/bin/env node
// Real workflow/SFC setup with isolated in-memory APIs. No backend, printer or production calls.
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), app = path.join(root, 'site-uniapp'), base = path.join(app, 'src/addon/hsx_recycle')
const ts = require(path.join(app, 'node_modules/typescript')), vue = require(path.join(app, 'node_modules/vue'))
const compiler = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
let count = 0
const plain = value => value === undefined ? undefined : JSON.parse(JSON.stringify(value))
const check = (actual, expected, label) => { assert.deepEqual(plain(actual), plain(expected), label); count++ }
const ok = (value, label) => { assert.ok(value, label); count++ }
function runModule(source, filename, dependencies = {}, extra = {}) {
    const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const module = { exports: {} }
    vm.runInNewContext(code, { module, exports: module.exports, console, ...extra, require(id) {
        if (Object.prototype.hasOwnProperty.call(dependencies, id)) return dependencies[id]
        if (id.endsWith('.vue')) return {}
        throw Error('Unexpected import: ' + id)
    } }, { filename })
    return module.exports
}
const workflow = runModule(fs.readFileSync(path.join(base, 'utils/agentOrder.ts'), 'utf8'), 'agentOrder.ts')
const device = runModule(fs.readFileSync(path.join(base, 'utils/device.ts'), 'utf8'), 'device.ts', { './helper': { formatMoney: value => String(value) } })
const member = { member_id: 75, nickname: '测试客户', username: '', mobile: '13800138000', member_no: 'M75', headimg: '' }
const fullModel = '手机/苹果/iPhone Duo系列/苹果 iPhone Duo'
const entry = { id: 31, model: fullModel, imei: '012345678901234', category_id: 8, category_path: [1, 2, 7, 8], initial_price: '2112', initial_price_uid: 0, check_template_id: 13170, summary_fields: [], summary_values: {} }
function component(mode = 'agent') {
    const calls = [], toasts = [], modals = [], lifecycle = [], emitted = []
    const filename = path.join(base, 'pages/order/components/SignOrderPopup.vue'), source = fs.readFileSync(filename, 'utf8')
    const { descriptor, errors } = compiler.parse(source, { filename }); check(errors, [], 'SFC parse')
    const script = compiler.compileScript(descriptor, { id: 'agent-release' })
    const template = compiler.compileTemplate({ filename, id: 'agent-release', source: descriptor.template.content, compilerOptions: { bindingMetadata: script.bindings } })
    check(template.errors, [], 'SFC template')
    const api = {
        createAgentOrder: async data => { calls.push(['create', plain(data)]); return { data: { id: 18, order_no: 'R-LOCAL-18' } } },
        getOrderDetail: async id => { calls.push(['detail', id]); return { data: { id, member_id: 75, status: 1, devices: [] } } },
        updateOrder: async (id, data) => { calls.push(['sign', id, plain(data)]); return { data: true } },
        getDeviceModelDictChildren: async () => ({ data: [] }), searchDeviceModelDictOptions: async () => ({ data: [] }),
    }
    const mod = runModule(script.content, filename, {
        vue: { ...vue, onBeforeUnmount: callback => lifecycle.push(callback) },
        '@/addon/hsx_recycle/api/order': api,
        '@/addon/hsx_recycle/api/check-template': { getCheckTemplateSchema: async () => ({ data: { groups: [], template: {} } }) },
        '@/addon/hsx_recycle/utils/agentOrder': workflow,
        '@/addon/hsx_recycle/utils/device': device,
    }, { uni: { showToast: data => toasts.push(data), showModal: data => modals.push(data), hideKeyboard() {} }, setTimeout: () => 1, clearTimeout() {} })
    const props = vue.reactive({ visible: true, mode, orderId: mode === 'sign' ? 99 : undefined, deviceList: mode === 'sign' ? [entry] : [] })
    const scope = vue.effectScope()
    const state = scope.run(() => mod.default.setup(props, { expose() {}, emit: (name, value) => emitted.push([name, plain(value)]) }))
    return { state, props, calls, toasts, modals, emitted, source, template: descriptor.template.content, dispose() { lifecycle.forEach(fn => fn()); scope.stop() } }
}
async function flush() { for (let i = 0; i < 4; i++) await vue.nextTick() }
async function main() {
    for (const [value, expected] of [[fullModel, '苹果 iPhone Duo'], ['苹果 iPhone 14 Pro Max', '苹果 iPhone 14 Pro Max'], ['手机／苹果／ iPhone Air ／', 'iPhone Air'], [' / / ', '未知型号'], ['', '未知型号'], [null, '未知型号'], [{ model: fullModel }, '未知型号']]) {
        check(device.getDeviceModelLeafName(value), expected, 'display only leaf name')
    }
    check(device.getDeviceModelLeafName('', '未填写型号'), '未填写型号', 'explicit display fallback')
    check(fullModel, '手机/苹果/iPhone Duo系列/苹果 iPhone Duo', 'does not mutate raw model')
    for (const id of [0, -1, NaN, Infinity, 1.5, Number.MAX_SAFE_INTEGER + 1]) ok(workflow.agentMemberError(id), 'invalid customer ID blocked')
    check(workflow.agentMemberError(75), '', 'valid customer accepted')
    {
        const c = component(), s = c.state
        s.addDevice()
        check(s.editVisible.value, false, 'cannot start a device before choosing customer')
        check(s.memberPickerVisible.value, true, 'opens customer chooser')
        check(c.calls.length, 0, 'no create/sign/print before customer')
        check(s.devices.value.length, 0, 'no local device insert before customer')
        s.selectMember(member)
        check(s.selectedMember.value.member_id, 75, 'customer selected')
        check(s.memberPickerVisible.value, false, 'chooser closes')
        check(s.editVisible.value, true, 'continues directly to device entry')
        Object.assign(s.editForm.value, plain(entry))
        s.saveEdit()
        check(s.devices.value.length, 1, 'local save succeeds after customer')
        check(s.devices.value[0].model, fullModel, 'raw full model preserved for backend/template')
        check(s.devices.value[0].category_path, [1, 2, 7, 8], 'category lineage unchanged')
        check(c.calls.length, 0, 'local save does not prematurely create/print')
        ok(c.template.includes('getDeviceModelLeafName(device.model'), 'device card uses leaf display')
        ok(c.template.includes('getDeviceModelLeafName(editForm.model'), 'selected-model field uses leaf display')
        ok(c.template.includes('getDeviceModelLeafName(item.model_full_name'), 'search result main name uses leaf display')
        c.dispose()
    }
    {
        const c = component(), s = c.state
        s.editVisible.value = true
        Object.assign(s.editForm.value, plain(entry))
        s.saveEdit()
        check(s.devices.value.length, 0, 'direct save cannot bypass customer guard')
        check(s.editVisible.value, true, 'missing customer does not close editing draft')
        check(s.editForm.value.imei, entry.imei, 'serial draft retained')
        check(s.memberPickerVisible.value, true, 'save asks for customer')
        s.selectMember(member)
        check(s.editForm.value.model, fullModel, 'selecting customer retains draft model')
        check(s.devices.value.length, 0, 'choosing customer does not auto-save user draft')
        s.saveEdit()
        check(s.devices.value.length, 1, 'explicit save after selection')
        await s.handleSubmit()
        check(c.calls.map(row => row[0]), ['create', 'detail', 'sign'], 'ordered create/check/sign after customer')
        check(c.calls[0][1].member_id, 75, 'create has real selected customer')
        check(c.calls[0][1].devices, [], 'empty draft still avoids early device write/print')
        check(c.calls[2][2].devices[0].model, fullModel, 'sign uses original full model')
        check(c.calls[2][2].devices[0].category_id, 8, 'sign keeps template category identity')
        check(c.calls[2][2].devices[0].imei, entry.imei, 'leading zero retained')
        ok(c.toasts.some(toast => toast.title === '下单并签收成功'), 'reports save/sign success only')
        ok(!c.toasts.some(toast => /打印成功|打印\d|成功打印/.test(toast.title)), 'does not fabricate printer success/count')
        c.dispose()
    }
    {
        const c = component(), s = c.state
        s.devices.value = [plain(entry)]
        await s.handleSubmit()
        check(c.calls.length, 0, 'direct final submit without customer cannot write')
        check(s.memberPickerVisible.value, true, 'final submit locates customer picker')
        check(s.devices.value[0].model, fullModel, 'final guard preserves device data')
        s.addAfterMember.value = true
        s.memberPickerVisible.value = false
        await flush()
        check(s.addAfterMember.value, false, 'cancelling chooser clears pending add action')
        c.dispose()
    }
    {
        const c = component('sign'), s = c.state
        check(s.devices.value.length, 1, 'existing sign mode remains available')
        s.addDevice()
        check(s.editVisible.value, true, 'existing order sign does not reselect customer')
        check(s.memberPickerVisible.value, false, 'sign mode has no new customer picker gate')
        s.agentState.draftId = 18
        s.selectMember(member)
        check(s.selectedMember.value, null, 'draft customer remains locked')
        c.dispose()
    }
    const listSource = fs.readFileSync(path.join(base, 'pages/order/list.vue'), 'utf8')
    ok(listSource.includes('getDeviceModelLeafName(device.model)'), 'order list displays leaf name')
    ok(listSource.includes('agent-order-fab') && listSource.includes('openCustomerPicker'), 'preserves FAB and precise customer search')
    console.log(`[通过] ${count} 项移动代下单提审前端隔离回归；真实SFC/工作流，未调用后端、打印或生产接口。`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
