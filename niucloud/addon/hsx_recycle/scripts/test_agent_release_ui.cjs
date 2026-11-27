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
    let savedCount = 0
    const filename = path.join(base, 'pages/order/components/SignOrderPopup.vue'), source = fs.readFileSync(filename, 'utf8')
    const { descriptor, errors } = compiler.parse(source, { filename }); check(errors, [], 'SFC parse')
    const script = compiler.compileScript(descriptor, { id: 'agent-release' })
    const template = compiler.compileTemplate({ filename, id: 'agent-release', source: descriptor.template.content, compilerOptions: { bindingMetadata: script.bindings } })
    check(template.errors, [], 'SFC template')
    const api = {
        createAgentOrder: async data => { calls.push(['create', plain(data)]); savedCount = data.count; return { data: { id: 18, order_no: 'R-LOCAL-18' } } },
        getOrderDetail: async id => { calls.push(['detail', id]); return { data: { id, member_id: 75, count: savedCount, status: 1, devices: [] } } },
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
    return { state, props, api, calls, toasts, modals, emitted, source, template: descriptor.template.content, dispose() { lifecycle.forEach(fn => fn()); scope.stop() } }
}
async function flush() { for (let i = 0; i < 4; i++) await vue.nextTick() }
function saveEntry(state, overrides = {}) {
    state.addDevice()
    Object.assign(state.editForm.value, plain(entry), overrides)
    state.saveEdit()
}
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
        check(s.agentTotalInput.value, 1, 'new order defaults to one total device')
        check(s.agentState.confirmedCount, 0, 'default total is not silently confirmed')
        check(s.countConfirmed.value, false, 'new order has no confirmed total')
        await s.confirmTotal()
        check(c.calls.length, 0, 'cannot confirm/create a total before choosing customer')
        s.addDevice()
        check(s.editVisible.value, false, 'cannot start a device before choosing customer')
        check(s.memberPickerVisible.value, true, 'opens customer chooser')
        check(c.calls.length, 0, 'no create/sign/print before customer')
        check(s.devices.value.length, 0, 'no local device insert before customer')
        s.selectMember(member)
        check(s.selectedMember.value.member_id, 75, 'customer selected')
        check(s.memberPickerVisible.value, false, 'chooser closes')
        check(s.editVisible.value, false, 'customer selection still requires explicit total confirmation')
        s.addDevice()
        check(s.editVisible.value, false, 'cannot open device entry before total confirmation')
        check(c.calls.length, 0, 'customer selection does not silently create an order')
        await s.confirmTotal()
        check(c.calls.map(row => row[0]), ['create'], 'confirming total creates only the empty draft')
        check(c.calls[0][1].count, 1, 'default total is persisted before device entry')
        check(c.calls[0][1].devices, [], 'confirming total does not write a placeholder device')
        check(c.calls[0][1].draft_device_entry, true, 'confirming total uses existing draft contract')
        check(c.calls[0][1].sign_after_create, false, 'confirming total does not sign or print')
        check(s.agentState.confirmedCount, 1, 'confirmed count is retained in workflow state')
        check(s.agentState.draftId, 18, 'confirmation retains the real draft ID')
        check(s.countConfirmed.value, true, 'successful confirmation unlocks device entry')
        s.addDevice()
        check(s.editVisible.value, true, 'device entry opens after explicit confirmation')
        Object.assign(s.editForm.value, plain(entry))
        s.saveEdit()
        check(s.devices.value.length, 1, 'local save succeeds after customer')
        check(s.devices.value[0].model, fullModel, 'raw full model preserved for backend/template')
        check(s.devices.value[0].category_path, [1, 2, 7, 8], 'category lineage unchanged')
        check(c.calls.length, 1, 'local save does not create another order or prematurely print')
        ok(c.template.includes('getDeviceModelLeafName(device.model'), 'device card uses leaf display')
        ok(c.template.includes('getDeviceModelLeafName(editForm.model'), 'selected-model field uses leaf display')
        ok(c.template.includes('getDeviceModelLeafName(item.model_full_name'), 'search result main name uses leaf display')
        ok(/<u-input v-model="agentTotalInput"[\s\S]*?:disabled="Boolean\(operationBusy \|\| agentState\.draftId \|\| agentState\.creatingUnknown\)"/.test(c.template), 'total input is disabled after create or an unknown result')
        ok(c.template.includes('本单不可修改'), 'UI explains that confirmed total cannot be changed')
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
        check(s.devices.value.length, 0, 'direct save cannot bypass total confirmation')
        check(s.editForm.value.imei, entry.imei, 'total guard retains edited serial')
        s.agentTotalInput.value = 10
        await s.confirmTotal()
        check(s.agentState.confirmedCount, 10, 'ten-device order is confirmed without creating ten devices')
        check(s.devices.value.length, 0, 'confirming total preserves empty local device list')
        s.saveEdit()
        check(s.devices.value.length, 1, 'explicit save after customer and total confirmation')
        await s.handleSubmit()
        check(c.calls.map(row => row[0]), ['create', 'detail', 'sign'], 'ordered create/check/sign after customer')
        check(c.calls[0][1].member_id, 75, 'create has real selected customer')
        check(c.calls[0][1].devices, [], 'empty draft still avoids early device write/print')
        check(c.calls[0][1].count, 10, 'one received device retains the ten-device label denominator')
        check(c.calls[2][2].devices.length, 1, 'partial received quantity need not equal total count')
        check(Object.hasOwn(c.calls[2][2], 'count'), false, 'sign does not overwrite the locked total')
        check(c.calls[2][2].devices[0].model, fullModel, 'sign uses original full model')
        check(c.calls[2][2].devices[0].category_id, 8, 'sign keeps template category identity')
        check(c.calls[2][2].devices[0].imei, entry.imei, 'leading zero retained')
        ok(c.toasts.some(toast => toast.title === '下单并签收成功'), 'reports save/sign success only')
        ok(!c.toasts.some(toast => /打印成功|打印\d|成功打印/.test(toast.title)), 'does not fabricate printer success/count')
        check(s.agentTotalInput.value, 1, 'successful order resets total input to one')
        check(s.agentState.confirmedCount, 0, 'successful order clears the confirmed-count lock')
        check(s.agentState.draftId, 0, 'successful order clears the old draft ID')
        check(s.selectedMember.value, null, 'successful order resets customer')
        check(s.devices.value, [], 'successful order resets local devices')
        check(s.countConfirmed.value, false, 'next order requires fresh confirmation')
        c.dispose()
    }
    {
        const c = component(), s = c.state
        s.selectMember(member)
        for (const value of ['', 0, -1, 1.5, 10001, 'abc']) {
            s.agentTotalInput.value = value
            await s.confirmTotal()
            check(c.calls.length, 0, 'invalid total rejected before create: ' + JSON.stringify(value))
            check(s.agentState.creatingUnknown, false, 'local total validation is not an unknown create result')
        }
        s.agentTotalInput.value = 1
        s.selectDelivery('1')
        await s.confirmTotal()
        check(c.calls.length, 0, 'express arrival requires its tracking number before confirmation')
        s.expressNo.value = ' LOCAL-EXPRESS-1 '
        await s.confirmTotal()
        check(c.calls[0][1].express_no, 'LOCAL-EXPRESS-1', 'confirmation persists trimmed express number')
        check(c.calls[0][1].delivery_type, '1', 'confirmation persists selected arrival method')
        const original = plain(s.selectedMember.value)
        s.selectMember({ ...member, member_id: 76, nickname: '另一客户' })
        s.selectDelivery('2')
        s.openMemberPicker()
        await s.confirmTotal()
        check(s.selectedMember.value, original, 'confirmed draft cannot change customer')
        check(s.deliveryType.value, '1', 'confirmed draft cannot change arrival method')
        check(s.memberPickerVisible.value, false, 'confirmed draft cannot reopen customer chooser')
        check(c.calls.length, 1, 'reconfirming a locked total never creates another draft')
        check(s.agentState.confirmedCount, 1, 'reconfirmation cannot change the locked denominator')
        saveEntry(s)
        check(s.devices.value.length, 1, 'can fill the confirmed device capacity')
        s.addDevice()
        check(s.editVisible.value, false, 'at capacity, add action cannot open another editor')
        s.editingIndex.value = -1
        s.editVisible.value = true
        Object.assign(s.editForm.value, plain(entry), { imei: '000000000000002' })
        s.saveEdit()
        check(s.devices.value.length, 1, 'direct save cannot bypass confirmed total capacity')
        check(s.editVisible.value, true, 'capacity rejection retains unsaved editor data')
        s.editDevice(s.devices.value[0], 0)
        await flush()
        s.editForm.value.user_sn = 'edited-within-capacity'
        s.saveEdit()
        check(s.devices.value.length, 1, 'editing an existing device at capacity is allowed')
        check(s.devices.value[0].user_sn, 'edited-within-capacity', 'existing device edit is actually retained')
        s.handleClose()
        check(s.show.value, false, 'confirmed draft can be closed when not busy')
        ok(c.emitted.some(row => row[0] === 'draft-saved' && row[1] === 18), 'close reports existing draft rather than a new order')
        c.props.visible = false
        await flush()
        c.props.visible = true
        await flush()
        check(s.show.value, true, 'confirmed draft reopens')
        check(s.agentState.draftId, 18, 'reopening preserves the same draft ID')
        check(s.agentState.confirmedCount, 1, 'reopening preserves locked total')
        check(s.agentTotalInput.value, 1, 'reopening preserves displayed total')
        check(s.devices.value[0].user_sn, 'edited-within-capacity', 'reopening preserves local device edits')
        await s.confirmTotal()
        check(c.calls.length, 1, 'reopened draft does not create another order')
        c.dispose()
    }
    {
        const c = component(), s = c.state
        s.selectMember(member)
        let resolveCreate
        c.api.createAgentOrder = data => {
            c.calls.push(['create', plain(data)])
            return new Promise(resolve => { resolveCreate = resolve })
        }
        const pending = s.confirmTotal()
        check(s.confirmingTotal.value, true, 'total confirmation exposes pending state')
        check(s.operationBusy.value, true, 'pending confirmation locks all order operations')
        s.handleClose()
        check(s.show.value, true, 'cannot close while total confirmation is pending')
        check(c.emitted.length, 0, 'busy close does not emit a false close/draft event')
        s.selectMember({ ...member, member_id: 76 })
        s.selectDelivery('1')
        s.openMemberPicker()
        s.addDevice()
        await s.confirmTotal()
        await s.handleSubmit()
        check(s.selectedMember.value.member_id, 75, 'busy confirmation locks customer')
        check(s.deliveryType.value, '2', 'busy confirmation locks arrival method')
        check(s.memberPickerVisible.value, false, 'busy confirmation cannot open customer picker')
        check(s.editVisible.value, false, 'busy confirmation cannot start device editing')
        check(c.calls.length, 1, 'double confirm and submit while busy cannot repeat create or sign')
        resolveCreate({ data: { id: 18, order_no: 'R-LOCAL-18' } })
        await pending
        check(s.confirmingTotal.value, false, 'settled confirmation clears pending indicator')
        check(s.operationBusy.value, false, 'settled confirmation releases operation lock')
        check(s.agentState.confirmedCount, 1, 'pending confirmation resolves with the original total')
        c.dispose()
    }
    {
        const c = component(), s = c.state
        s.selectMember(member)
        s.agentTotalInput.value = 10
        c.api.createAgentOrder = async data => { c.calls.push(['create', plain(data)]); throw Error('isolated timeout') }
        await s.confirmTotal()
        check(s.agentState.creatingUnknown, true, 'lost creation response is treated as unknown')
        check(s.agentState.confirmedCount, 0, 'unknown creation does not invent confirmed count')
        check(s.agentTotalInput.value, 10, 'unknown creation retains the attempted total')
        s.selectMember({ ...member, member_id: 76 })
        s.selectDelivery('1')
        s.addDevice()
        await s.confirmTotal()
        await s.handleSubmit()
        check(c.calls.length, 1, 'unknown creation prevents automatic create and sign retries')
        check(s.selectedMember.value.member_id, 75, 'unknown creation locks original customer')
        check(s.deliveryType.value, '2', 'unknown creation locks original arrival method')
        check(s.editVisible.value, false, 'unknown creation cannot continue device entry')
        check(s.operationBusy.value, false, 'unknown result does not leave UI permanently busy')
        s.confirmCreateRetry()
        check(c.modals.length, 1, 'retry requires an explicit order-list verification modal')
        c.modals[0].success({ confirm: false })
        check(s.agentState.creatingUnknown, true, 'cancelling verification keeps duplicate-create protection')
        check(c.calls.length, 1, 'opening or cancelling verification makes no API calls')
        c.dispose()
    }
    {
        const c = component(), s = c.state
        s.selectMember(member)
        await s.confirmTotal()
        s.agentTotalInput.value = 2
        await s.confirmTotal()
        check(s.agentState.confirmedCount, 1, 'programmatic input change cannot rewrite the confirmed total')
        check(c.calls.length, 1, 'changed total cannot create a replacement draft')
        c.dispose()
    }
    {
        const c = component(), s = c.state
        s.selectMember(member)
        await s.confirmTotal()
        saveEntry(s)
        c.api.getOrderDetail = async id => { c.calls.push(['detail', id]); return { data: { id, member_id: 75, count: 2, status: 1, devices: [] } } }
        await s.handleSubmit()
        check(c.calls.map(row => row[0]), ['create', 'detail'], 'changed server count blocks sign without creating another order')
        check(s.agentState.confirmedCount, 1, 'server-count mismatch does not rewrite local lock')
        check(s.devices.value.length, 1, 'server-count mismatch preserves unsaved local devices')
        ok(s.submitError.value.includes('总台数'), 'server-count mismatch is explained to the operator')
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
        const c = component(), s = c.state
        const node = { id: 8, node_name: '苹果 iPhone 14 Pro', model_full_name: '手机/苹果/iPhone 14系列/苹果 iPhone 14 Pro', has_children: 0, category_path: [1, 2, 7, 8] }
        s.chooseModelNode(node)
        check(s.editForm.value.model, '苹果 iPhone 14 Pro', 'new selection stores leaf name before local save')
        check(s.editForm.value.category_id, 8, 'new selection retains category identity')
        check(s.editForm.value.category_path, [1, 2, 7, 8], 'new selection retains full ID path for templates')
        check(node.model_full_name, '手机/苹果/iPhone 14系列/苹果 iPhone 14 Pro', 'selection does not mutate dictionary source path')
        s.chooseModelNode({ id: 9, node_name: '', model_full_name: '手机／苹果／ 苹果 iPhone Air', has_children: 0, category_path: [1, 2, 9] })
        check(s.editForm.value.model, '苹果 iPhone Air', 'missing node name falls back to leaf path')
        c.dispose()
    }
    {
        const c = component('sign'), s = c.state
        check(s.devices.value.length, 1, 'existing sign mode remains available')
        s.addDevice()
        check(s.editVisible.value, true, 'existing order sign does not reselect customer')
        check(s.memberPickerVisible.value, false, 'sign mode has no new customer picker gate')
        check(s.agentState.confirmedCount, 0, 'ordinary sign does not create a new-order total lock')
        s.closeEdit()
        await s.confirmTotal()
        check(c.calls.length, 0, 'ordinary sign cannot create a new draft through total confirmation')
        await s.handleSubmit()
        check(c.calls.map(row => row[0]), ['sign'], 'ordinary sign retains its existing endpoint without create/detail')
        check(c.calls[0][1], 99, 'ordinary sign retains original order ID')
        check(Object.hasOwn(c.calls[0][2], 'count'), false, 'ordinary sign cannot edit an existing order total')
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
