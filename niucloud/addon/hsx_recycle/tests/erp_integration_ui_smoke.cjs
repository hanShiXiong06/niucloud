// Read-only UI contract checks: no server, database, build output or business writes.
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const { createRequire } = require('node:module')

const workspace = path.resolve(__dirname, '../../../..')
const read = file => fs.readFileSync(path.join(workspace, file), 'utf8')
const adminFiles = [
    'api/recycle_order.ts', 'api/erp_integration.ts', 'hooks/useRecycleOrderActions.ts',
    'views/order_config/submit.vue', 'views/order_config/components/ErpIntegrationSettings.vue',
    'views/recycle_order/components/PaymentMethodDialog.vue',
    'views/recycle_order/components/PriceFormDialog.vue', 'views/recycle_order/list.vue', 'utils/payment-scope.ts',
    'views/device_export/list.vue'
]
const mobileFiles = [
    'api/order.ts', 'pages/order/detail.vue', 'pages/order/components/PaymentConfirmPopup.vue',
    'pages/order/components/PriceDevicePopup.vue', 'utils/payment-scope.ts'
]

function checkSyntax(project, files) {
    const projectRequire = createRequire(path.join(workspace, project, 'package.json'))
    const compiler = projectRequire('@vue/compiler-sfc')
    const ts = projectRequire('typescript')
    for (const relative of files) {
        const file = `${project}/src/addon/hsx_recycle/${relative}`
        let script = read(file)
        if (file.endsWith('.vue')) {
            const parsed = compiler.parse(script, { filename: file })
            assert.equal(parsed.errors.length, 0, `${file}: parse errors`)
            const compiled = compiler.compileScript(parsed.descriptor, { id: file })
            script = compiled.content
            const template = compiler.compileTemplate({
                source: parsed.descriptor.template.content, filename: file, id: file,
                compilerOptions: { bindingMetadata: compiled.bindings, expressionPlugins: ['typescript'] }
            })
            assert.equal(template.errors.length, 0, `${file}: ${template.errors.join('; ')}`)
        }
        const result = ts.transpileModule(script, {
            reportDiagnostics: true,
            compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS }
        })
        assert.equal((result.diagnostics || []).filter(item => item.category === ts.DiagnosticCategory.Error).length, 0, `${file}: TypeScript syntax`)
    }
}

function loadScopeHelpers() {
    const projectRequire = createRequire(path.join(workspace, 'admin/package.json'))
    const ts = projectRequire('typescript')
    const exports = {}
    vm.runInNewContext(ts.transpileModule(read('admin/src/addon/hsx_recycle/utils/payment-scope.ts'), {
        compilerOptions: { module: ts.ModuleKind.CommonJS }
    }).outputText, { exports })
    return exports
}

async function checkPaymentEntry(scope, failure = false, mode = 'order') {
    const projectRequire = createRequire(path.join(workspace, 'admin/package.json'))
    const ts = projectRequire('typescript')
    const calls = { queried: [], routes: [], alerts: [], errors: [] }
    const api = {
        async getCapitalAccountOptions(id) {
            calls.queried.push(id)
            if (failure) throw new Error('offline')
            return { data: scope }
        },
        async getMerchantPayInfo() { return { data: [] } }
    }
    const exports = {}
    const code = ts.transpileModule(read('admin/src/addon/hsx_recycle/hooks/useRecycleOrderActions.ts'), {
        compilerOptions: { module: ts.ModuleKind.CommonJS }
    }).outputText
    vm.runInNewContext(code, {
        exports, console,
        require(name) {
            if (name === 'element-plus') return {
                ElMessage: { error: value => calls.errors.push(value), warning() {} },
                ElMessageBox: { async confirm() {}, async alert(value) { calls.alerts.push(value) } },
                ElLoading: {}
            }
            if (name === 'vue-router') return { useRouter: () => ({ async push(value) { calls.routes.push(value) } }) }
            if (name.endsWith('/api/recycle_order')) return api
            if (name.endsWith('/utils/payment-scope')) return loadScopeHelpers()
            throw new Error(`Unexpected dependency ${name}`)
        }
    })
    const ref = value => ({ value })
    const row = { id: 71, devices: [], member_id: 5, flow_mode: mode }
    const options = {
        list: ref([row]), pagination: ref({ page: 1 }), getList: async () => {},
        currentOrderId: ref(''), currentDevices: ref([]), orderDialogVisible: ref(false),
        checkDeviceLogVisible: ref(false), priceDeviceLogVisible: ref(false), paymentDialogVisible: ref(false),
        paymentInfo: ref([]), paymentMode: ref('order'), selectedPayTypeIndex: ref(0),
        orderDetailVisible: ref(false), orderDetail: ref(null)
    }
    await exports.useRecycleOrderActions(options).handleAction(row, { key: 'order_payment' })
    assert.deepEqual(calls.queried, [71], 'Payment capability must be requested for the current order')
    if (failure) {
        assert.equal(options.paymentDialogVisible.value, false)
        assert.equal(calls.routes.length, 0)
        assert.equal(calls.errors.length, 1)
    } else if (scope.payment_owner === 'self_erp') {
        assert.equal(options.paymentDialogVisible.value, false)
        assert.deepEqual(calls.routes, ['/site/hsx_erp/payable'])
    } else if ((scope.payment_owner === 'local' && scope.local_allowed === true) || (scope.payment_owner === 'mixed' && mode === 'device')) {
        assert.equal(options.paymentDialogVisible.value, true)
        assert.equal(calls.routes.length, 0)
    } else {
        assert.equal(options.paymentDialogVisible.value, false)
        assert.equal(options.orderDialogVisible.value, true)
        assert.equal(calls.alerts.length, 1)
    }
}

async function checkMixedDeviceSelection() {
    const projectRequire = createRequire(path.join(workspace, 'admin/package.json'))
    const compiler = projectRequire('@vue/compiler-sfc')
    const ts = projectRequire('typescript')
    const vue = projectRequire('vue')
    const scope = { payment_owner: 'mixed', local_allowed: false, devices: [
        { device_id: 101, owner: 'local' }, { device_id: 201, owner: 'self_erp' },
        { device_id: 301, owner: 'unknown' }, { device_id: 401, owner: 'local' }
    ] }
    const file = 'admin/src/addon/hsx_recycle/views/recycle_order/components/PaymentMethodDialog.vue'
    const parsed = compiler.parse(read(file), { filename: file })
    const compiled = compiler.compileScript(parsed.descriptor, { id: file })
    const exports = {}
    vm.runInNewContext(ts.transpileModule(compiled.content, {
        compilerOptions: { module: ts.ModuleKind.CommonJS }
    }).outputText, {
        exports, console,
        require(name) {
            if (name === 'vue') return { ...vue, watch() {}, onMounted() {}, onBeforeUnmount() {} }
            if (name === 'vue-router') return { useRouter: () => ({ push() {} }) }
            if (name === 'element-plus') return { ElMessage: { warning() {} } }
            if (name === '@/addon/hsx_components/core') return { useFeedback: () => ({ warning() {} }) }
            if (name.endsWith('/utils/payment-scope')) return loadScopeHelpers()
            if (name.endsWith('/api/recycle_order')) return { async getCapitalAccountOptions() { return { data: scope } } }
            return {}
        }
    })
    const emitted = []
    const devices = [101, 201, 301, 401].map(id => ({ id, can_pay: id !== 401, final_price: 10, status: 5 }))
    const props = { visible: false, orderId: 71, submitting: false, paymentInfo: [{ pay_type: 'bank', payment_mode: 'device', order_summary: { order_id: 71, total_amount: 40, devices } }] }
    const ui = exports.default.setup(props, { expose() {}, emit(...args) { emitted.push(args) } })
    await ui.loadCapitalAccounts()
    assert.equal(ui.localPaymentAllowed.value, true, 'Mixed device mode must retain a local payment entry')
    assert.equal(ui.hasErpDevices.value, true, 'Mixed popup must expose an ERP entry')
    assert.deepEqual(devices.map(device => ui.isDeviceSelectable(device)), [true, false, false, false], 'Only local devices already eligible for payment may be selected')
    assert.equal(ui.devicePaymentReason(devices[1]), '由 ERP 处理')
    assert.equal(ui.devicePaymentReason(devices[2]), '归属待核对')
    ui.handleDeviceSelectionChange(devices)
    ui.handleConfirmPayment()
    assert.equal(emitted.length, 1)
    assert.equal(emitted[0][1].selectedDeviceIds.join(','), '101')
    assert.equal(emitted[0][1].amount, 10, 'Confirmation must exclude ERP and unknown devices from the selected amount')
}

async function checkMobileMixedPayment(recheckOwner = 'local') {
    const projectRequire = createRequire(path.join(workspace, 'site-uniapp/package.json'))
    const compiler = projectRequire('@vue/compiler-sfc')
    const ts = projectRequire('typescript')
    const vue = projectRequire('vue')
    const queries = []
    const payments = []
    const file = 'site-uniapp/src/addon/hsx_recycle/pages/order/components/PaymentConfirmPopup.vue'
    const compiled = compiler.compileScript(compiler.parse(read(file), { filename: file }).descriptor, { id: file })
    const exports = {}
    vm.runInNewContext(ts.transpileModule(compiled.content, {
        compilerOptions: { module: ts.ModuleKind.CommonJS }
    }).outputText, {
        exports, console, uni: { showToast() {}, navigateTo() {} },
        require(name) {
            if (name === 'vue') return { ...vue, watch() {} }
            if (name.endsWith('/utils/payment-scope')) return loadScopeHelpers()
            if (name.endsWith('/utils/helper')) return { formatMoney: value => Number(value).toFixed(2) }
            if (name.endsWith('/utils/device')) return { getDeviceSettlementAmount: device => device.final_price, isConsignedDevice: () => false }
            if (name.endsWith('/api/order')) return {
                async getCapitalAccountOptions(orderId, deviceIds) {
                    queries.push({ orderId, deviceIds })
                    if (deviceIds) {
                        if (recheckOwner === 'error') throw new Error('offline')
                        return { data: { payment_owner: recheckOwner, local_allowed: recheckOwner === 'local', devices: [{ device_id: 101, owner: recheckOwner }] } }
                    }
                    return { data: { payment_owner: 'mixed', local_allowed: false, devices: [
                        { device_id: 101, owner: 'local' }, { device_id: 201, owner: 'self_erp' },
                        { device_id: 301, owner: 'unknown' }, { device_id: 401, owner: 'local' }
                    ] } }
                },
                async getMerchantPayInfo() { return { data: [] } },
                async devicePaymentConfirm(orderId, payload) { payments.push({ orderId, payload }) },
                async paymentConfirm() { throw new Error('Mixed order must not use whole-order payment') }
            }
            return {}
        }
    })
    const devices = [101, 201, 301, 401].map(id => ({ id, can_pay: id !== 401, final_price: 10, status: 5 }))
    const ui = exports.default.setup({ visible: false, orderData: { id: 71, member_id: 5, flow_mode: 'device' }, devices }, { expose() {}, emit() {} })
    await ui.initPopup()
    ui.customPayType.value = 'bank'
    assert.equal(ui.localPaymentAllowed.value, true)
    assert.equal(ui.hasErpDevices.value, true)
    assert.equal(ui.payableDevices.value.map(device => device.id).join(','), '101')
    assert.equal(ui.blockedDevices.value.map(device => device.id).join(','), '201,301,401')
    assert.equal(ui.currentPayAmount.value, '10.00')
    ui.toggleDevice(201)
    assert.equal(ui.selectedDeviceIds.value.join(','), '101', 'ERP device must not become selected')
    await ui.submitPayment()
    assert.equal(queries.length, 2)
    assert.equal(queries[1].orderId, 71)
    assert.equal(queries[1].deviceIds.join(','), '101', 'Submit recheck must target the exact selected snapshot')
    assert.equal(payments.length, recheckOwner === 'local' ? 1 : 0)
    if (recheckOwner === 'local') assert.equal(payments[0].payload.device_ids.join(','), '101')
    else assert.ok(ui.paymentScopeError.value, 'Changed or failed scope must remain visibly blocked')
}

async function checkDeviceScopedPriceOptions() {
    for (const project of ['admin', 'site-uniapp']) {
        const projectRequire = createRequire(path.join(workspace, project, 'package.json'))
        const compiler = projectRequire('@vue/compiler-sfc')
        const ts = projectRequire('typescript')
        const vue = projectRequire('vue')
        const isAdmin = project === 'admin'
        const file = `${project}/src/addon/hsx_recycle/${isAdmin ? 'views/recycle_order/components/PriceFormDialog.vue' : 'pages/order/components/PriceDevicePopup.vue'}`
        const compiled = compiler.compileScript(compiler.parse(read(file), { filename: file }).descriptor, { id: file })
        const exports = {}
        const queried = []
        vm.runInNewContext(ts.transpileModule(compiled.content, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText, {
            exports, console,
            require(name) {
                if (name === 'vue') return { ...vue, watch() {}, onMounted() {}, onBeforeUnmount() {} }
                if (name === '@/addon/hsx_components/core') return { useFeedback: () => ({ warning() {}, error() {}, success() {} }) }
                if (name.endsWith('/api/recycle_order') || name.endsWith('/api/order')) return {
                    async getSaleDestinationOptions(id) { queried.push(id); return { data: { items: [], warehouses: [], erp_connected: false } } }
                }
                if (name.endsWith('/hooks/useRecycleSubmit')) return { useRecycleSubmit: () => ({}) }
                return {}
            }
        })
        const device = {}
        const props = { visible: false, submitting: false, [isAdmin ? 'device' : 'deviceData']: device }
        const ui = exports.default.setup(props, { expose() {}, emit() {} })
        await ui.loadSaleDestinationOptions()
        assert.equal(queried.length, 0, `${project}: missing device ID must not query site-level defaults`)
        assert.ok(ui.saleDestinationError.value)
        device.id = '41'
        await ui.loadSaleDestinationOptions()
        device.id = 42
        await ui.loadSaleDestinationOptions()
        assert.deepEqual(queried, [41, 42], `${project}: price options must follow the current editing device`)

        const apiExports = {}
        const requests = []
        const apiFile = `${project}/src/addon/hsx_recycle/api/${isAdmin ? 'recycle_order' : 'order'}.ts`
        vm.runInNewContext(ts.transpileModule(read(apiFile), { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText, {
            exports: apiExports,
            require() { return { default: { get(url, params) { requests.push({ url, params }) } } } }
        })
        apiExports.getSaleDestinationOptions(41)
        apiExports.getSaleDestinationOptions()
        assert.equal((isAdmin ? requests[0].params.params : requests[0].params).device_id, 41)
        assert.equal(Object.keys(isAdmin ? requests[1].params.params : requests[1].params).length, 0, `${project}: no-argument API calls must preserve site-level compatibility`)
    }
}

async function checkSyncHealthFailure() {
    const projectRequire = createRequire(path.join(workspace, 'admin/package.json'))
    const compiler = projectRequire('@vue/compiler-sfc')
    const ts = projectRequire('typescript')
    const vue = projectRequire('vue')
    const file = 'admin/src/addon/hsx_recycle/views/device_export/list.vue'
    const compiled = compiler.compileScript(compiler.parse(read(file), { filename: file }).descriptor, { id: file })
    const exports = {}
    const row = { id: 101, target_warehouse_id: 1, target_location_id: 1 }
    const writes = []
    let healthMode = 'error'
    vm.runInNewContext(ts.transpileModule(compiled.content, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText, {
        exports, console,
        require(name) {
            if (name === 'vue') return vue
            if (name === 'vue-router') return { useRoute: () => ({ meta: { title: '设备导出' }, query: {} }) }
            if (name === '@/lang') return { t: value => value }
            if (name === 'element-plus') return { ElMessage: { warning() {}, error() {}, success() {}, info() {} }, ElMessageBox: { async confirm() {} } }
            if (name === '@/addon/hsx_components/core') return { useFeedback: () => ({ warning() {}, error() {}, success() {}, info() {} }) }
            if (name.endsWith('/api/recycle_order')) return { async getSaleDestinationOptions() { return { data: {} } } }
            if (name.endsWith('/api/device_export')) return {
                async getRecycleDeviceList() { return { data: { data: [row], total: 1 } } },
                async getDeviceSyncHealth() {
                    if (healthMode === 'error') throw new Error('offline')
                    if (healthMode === 'incomplete') return { data: {} }
                    return { data: { 101: { stuck: true, has_asset: true, unknown: healthMode === 'unknown' } } }
                },
                async syncRecycleDevicesToErp() { writes.push('sync') },
                async resyncRecycleDevice() { writes.push('resync') }
            }
            return {}
        }
    })
    const ui = exports.default.setup({}, { expose() {}, emit() {} })
    await new Promise(resolve => setImmediate(resolve))
    assert.match(ui.syncHealthError.value, /同步状态暂无法确认/)
    assert.equal(ui.syncHealthUnavailable.value, true)
    assert.equal(ui.erpStatusMeta({ ...row, erp_sync: { inventory_status: 'in_stock' } }).label, '同步状态待确认', 'Failed health must not render a cached successful ERP state as current')
    ui.selectedDevices.value = [row]
    ui.placementPendingRows.value = [row]
    ui.placementSyncRows.value = [row]
    ui.placementForm.target_warehouse_id = 1
    ui.placementForm.target_location_id = 1
    await ui.syncErpEvent()
    await ui.handleResync(row)
    await ui.submitPlacementRepair()
    assert.equal(writes.length, 0, 'Unconfirmed health must block every sync mutation entry')
    healthMode = 'ok'
    await ui.loadSyncHealth()
    assert.equal(ui.syncHealthError.value, '')
    assert.equal(ui.canSyncRows([row]), true, 'A successful retry must restore the existing sync entry')
    healthMode = 'unknown'
    await ui.loadSyncHealth()
    assert.equal(ui.canSyncRows([row]), false)
    assert.equal(ui.erpStatusMeta(row).label, '同步状态待确认')
    healthMode = 'incomplete'
    await ui.loadSyncHealth()
    assert.match(ui.syncHealthError.value, /同步状态暂无法确认/, 'Missing device health must not count as a confirmed response')
    assert.match(read(file), /刷新并重试/)
}

async function main() {
    checkSyntax('admin', adminFiles)
    checkSyntax('site-uniapp', mobileFiles)
    for (const file of adminFiles) {
        assert.equal(read(`admin/src/addon/hsx_recycle/${file}`), read(`niucloud/addon/hsx_recycle/admin/${file}`), `Mirror differs: ${file}`)
    }
    await checkPaymentEntry({ payment_owner: 'local', local_allowed: true, erp_connected: true })
    await checkPaymentEntry({ payment_owner: 'local', local_allowed: false })
    await checkPaymentEntry({ payment_owner: 'self_erp', local_allowed: false })
    await checkPaymentEntry({ payment_owner: 'mixed', local_allowed: false })
    await checkPaymentEntry({ payment_owner: 'mixed', local_allowed: false }, false, 'device')
    await checkPaymentEntry({ payment_owner: 'unknown', local_allowed: false })
    await checkPaymentEntry({})
    await checkPaymentEntry({}, true)
    await checkMixedDeviceSelection()
    await checkMobileMixedPayment()
    await checkMobileMixedPayment('self_erp')
    await checkMobileMixedPayment('error')
    await checkDeviceScopedPriceOptions()
    await checkSyncHealthFailure()
    const payment = read('admin/src/addon/hsx_recycle/views/recycle_order/components/PaymentMethodDialog.vue')
    assert.match(payment, /amount: isDevicePaymentMode\.value \? selectedDeviceAmount\.value :/, 'Partial payment confirmation must use the selected-device amount')
    assert.match(payment, /if \(paymentScopeLoading\.value \|\| !localPaymentAllowed\.value\)/, 'Payment popup must stay closed to unverified scope')
    const mobilePayment = read('site-uniapp/src/addon/hsx_recycle/pages/order/components/PaymentConfirmPopup.vue')
    assert.match(mobilePayment, /getCapitalAccountOptions\(props.orderData.id, deviceIds\)/, 'Mobile payment must recheck the selected device IDs before submitting')
    assert.match(read('admin/src/addon/hsx_recycle/views/recycle_order/list.vue'), /getCapitalAccountOptions\(orderId, deviceIds\)/, 'PC payment must recheck the selected device IDs before submitting')
    const settings = read('admin/src/addon/hsx_recycle/views/order_config/components/ErpIntegrationSettings.vue')
    assert.match(settings, /兼容旧规则 · 待确认/)
    assert.match(settings, /切换后新创建的设备按新配置办理/)
    assert.match(settings, /已有设备按已记录归属或历史规则办理，实际已入 ERP 的设备仍由 ERP 处理/)
    assert.doesNotMatch(payment, /不会.*转账|登记已付款/)
    assert.doesNotMatch(mobilePayment, /不会.*转账|登记已付款/)
    assert.match(read('admin/src/addon/hsx_recycle/api/erp_integration.ts'), /mode, confirm: true/)
    assert.equal(read('admin/src/addon/hsx_recycle/utils/payment-scope.ts'), read('site-uniapp/src/addon/hsx_recycle/utils/payment-scope.ts'))
    console.log('[PASS] recycle ERP UI: 15 Vue/TypeScript files, 8 payment ownership branches, PC mixed-device selection, 3 mobile submit-scope branches, device-scoped pricing on both clients, sync-health failure/retry/unknown guards, mirrored sources and confirmation contracts')
}

main().catch(error => { console.error(error); process.exitCode = 1 })
