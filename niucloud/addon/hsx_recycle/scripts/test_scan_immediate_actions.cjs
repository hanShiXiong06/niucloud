#!/usr/bin/env node
// Real scan SFC + print hook, isolated APIs only: no server requests or printer jobs.
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const app = path.join(root, 'site-uniapp')
const src = path.join(app, 'src')
const req = require('node:module').createRequire(path.join(app, 'package.json'))
const ts = req('typescript')
const vue = req('vue')
const compiler = req('@vue/compiler-sfc')
const clone = value => JSON.parse(JSON.stringify(value))
let count = 0
const same = (actual, expected, label) => { assert.deepEqual(clone(actual), clone(expected), label); count++ }
const ok = (actual, label) => { assert.ok(actual, label); count++ }
const deferred = () => { let release; const promise = new Promise(resolve => { release = resolve }); return { promise, release } }
const settle = async () => { for (let i = 0; i < 30; i++) await Promise.resolve(); await vue.nextTick() }
const qr = (id = 3036, siteId = 100005) => `https://scan-test.invalid/adminapp/addon/hsx_recycle/pages/check/scan?device_id=${id}&site_id=${siteId}&mode=query`

function fixture(status = 1) {
    const calls = [], toasts = [], modals = [], scans = [], lifecycle = { load: [], show: [] }
    const device = { id: 3036, order_id: 2527, status, model: '苹果 iPhone 14 Pro', imei: '353938205192374' }
    const second = { ...device, id: 3037, order_id: 2528, model: '苹果 iPhone 15 Pro' }
    const records = new Map([[String(device.id), device], [String(second.id), second]])
    const state = { token: 'test-token', siteId: 100005, failDevice: false, failSearch: false, deviceGate: null, searchGate: null, actionsGate: null, planGate: null, searchList: [device] }
    const actions = [{ scene_key: 'label-a', template_type: 'device_label', button_position: 'device_actions', visible_device_status: [1, 2], confirm_required: 0 }]
    const printerApi = {
        getPrintSceneManualActions: async () => {
            calls.push(['actions'])
            if (state.actionsGate) await state.actionsGate
            return { code: 1, data: actions }
        },
        getPrintScenePlan: async (scene, payload) => {
            calls.push(['plan', scene, clone(payload)])
            if (state.planGate) await state.planGate
            const record = records.get(String(payload.device_id)) || device
            return { code: 1, data: { can_print: true, label_edit: { enabled: 0, fields: [{ key: 'model', label: '设备型号', value: record.model }, { key: 'color', label: '颜色', value: '黑色' }] } } }
        },
        printByScene: async (scene, payload) => { calls.push(['print', scene, clone(payload)]); return { code: 1, msg: '打印任务已发送' } }
    }
    const uni = {
        getStorageSync: key => key === 'siteId' ? state.siteId : '',
        scanCode: options => { scans.push(options); calls.push(['scan']) },
        showToast: value => toasts.push(value),
        showLoading() {}, hideLoading() {},
        showModal: value => { modals.push(value) },
        showActionSheet: value => value.success?.({ tapIndex: 0 })
    }
    const modules = {}
    function evaluate(relative) {
        if (modules[relative]) return modules[relative]
        const file = path.join(src, relative)
        let source = fs.readFileSync(file, 'utf8')
        if (file.endsWith('.vue')) {
            const { descriptor, errors } = compiler.parse(source, { filename: file })
            same(errors, [], 'scan SFC parses')
            const script = compiler.compileScript(descriptor, { id: 'scan-immediate-actions-test' })
            same(compiler.compileTemplate({ source: descriptor.template.content, filename: file, id: 'scan-immediate-actions-test', compilerOptions: { bindingMetadata: script.bindings } }).errors, [], 'scan template compiles')
            ok(descriptor.template.content.includes('@click="switchMode(item.value)"'), 'visible mode buttons use immediate-action handler')
            source = script.content
        }
        const module = { exports: {} }
        vm.runInNewContext(ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, {
            module, exports: module.exports, console, uni, setTimeout, clearTimeout,
            require(id) {
                if (id === 'vue') return vue
                if (id === '@dcloudio/uni-app') return { onLoad: callback => lifecycle.load.push(callback), onShow: callback => lifecycle.show.push(callback) }
                if (id === '@/utils/common') return { getToken: () => state.token, redirect: value => calls.push(['redirect', clone(value)]) }
                if (id === '@/hooks/useLogin') return { useLogin: () => ({ setLoginBack: value => calls.push(['login', clone(value)]) }) }
                if (id.endsWith('/api/printer')) return printerApi
                if (id.endsWith('/api/order')) return {
                    scanSearchDevice: async params => {
                        calls.push(['search', clone(params)])
                        if (state.searchGate) await state.searchGate
                        if (state.failSearch) throw { msg: '查询设备无权限' }
                        return { data: state.searchList.map(clone) }
                    },
                    getDevice: async id => {
                        calls.push(['device', String(id)])
                        if (state.deviceGate) await state.deviceGate
                        if (state.failDevice) throw { msg: '设备无权限' }
                        return { data: records.has(String(id)) ? clone(records.get(String(id))) : null }
                    }
                }
                if (id.endsWith('/hooks/useRecyclePrintActions')) return evaluate('addon/hsx_recycle/hooks/useRecyclePrintActions.ts')
                if (id.endsWith('/utils/devicePrintLink')) return evaluate('addon/hsx_recycle/utils/devicePrintLink.ts')
                if (id.endsWith('.vue')) return {}
                throw new Error('Unexpected import: ' + id)
            }
        }, { filename: file })
        modules[relative] = module.exports
        return module.exports
    }
    const page = evaluate('addon/hsx_recycle/pages/check/scan.vue').default.setup({}, { expose() {} })
    return {
        page, calls, toasts, modals, scans, lifecycle, device, second, records, state, actions,
        async route(options) { for (const callback of lifecycle.load) await callback(options); await settle() },
        async scanResult(result) {
            const scan = scans.at(-1)
            assert.ok(scan, 'a scanner must be open before supplying its result')
            await scan.success?.({ result })
            await scan.complete?.({ result })
            await settle()
        },
        async scanFail(errMsg = 'scanCode:fail cancel') {
            const scan = scans.at(-1)
            assert.ok(scan, 'a scanner must be open before cancelling it')
            await scan.fail?.({ errMsg })
            await scan.complete?.({ errMsg })
            await settle()
        }
    }
}

const called = (f, kind) => f.calls.filter(call => call[0] === kind)
const noPrint = (f, label) => same(called(f, 'print'), [], label + ': no printer call without confirmation')
function expectAction(f, action, label) {
    const p = f.page
    same(p.checkPopupVisible.value, action === 'check', label + ': inspection popup')
    same(p.pricePopupVisible.value, action === 'price', label + ': pricing popup')
    same(p.labelEditVisible.value, action === 'label', label + ': label editor')
    same(called(f, 'redirect').length, action === 'query' ? 1 : 0, label + ': order navigation')
    if (action === 'query') {
        same(called(f, 'redirect')[0][1].param.id, f.device.order_id, label + ': linked order is selected')
    }
    noPrint(f, label)
}

async function main() {
    // The same physical QR deliberately contains mode=query. Inside the scanner,
    // the employee's selected button, not the payload's mode, controls the action.
    for (const source of ['qr', 'imei', 'manual-qr', 'manual-imei']) {
        for (const scenario of [{ mode: 'process', status: 1, action: 'check' }, { mode: 'process', status: 3, action: 'price' }, { mode: 'query', status: 5, action: 'query' }, { mode: 'label', status: 5, action: 'label' }]) {
            const f = fixture(scenario.status), p = f.page
            await p.loadManualPrintActions()
            p.scanMode.value = scenario.mode
            const content = source.includes('qr') ? qr() : f.device.imei
            if (source.startsWith('manual')) {
                p.manualText.value = content
                await p.handleManualSubmit()
                await settle()
            } else {
                p.scanDevice()
                await f.scanResult(content)
            }
            const label = `${source}/${scenario.mode}/status-${scenario.status}`
            same(p.scanMode.value, scenario.mode, label + ': selected mode is preserved')
            same(called(f, 'device').length, 1, label + ': loads one exact device')
            same(called(f, 'search').length, source.includes('qr') ? 0 : 1, label + ': exact QR does not use ambiguous search')
            expectAction(f, scenario.action, label)
            same(p.deviceData.value.status, scenario.status, label + ': opening an action does not change business state')
        }
    }

    for (const status of [1, 2, 3, 4, 5, 6, 7, 8, 9]) {
        const f = fixture(status)
        await f.page.openPrintedDevice({ deviceId: String(f.device.id), siteId: f.state.siteId })
        await settle()
        const action = [1, 2].includes(status) ? 'check' : [3, 4, 7, 8].includes(status) ? 'price' : ''
        expectAction(f, action, 'process exact QR status ' + status)
        if (!action) ok(f.toasts.some(toast => /已回收|已退回|代卖/.test(toast.title)), 'unsupported state ' + status + ' explains why processing is blocked')
    }

    for (const mode of ['process', 'query', 'label']) {
        const f = fixture(), p = f.page
        await p.loadManualPrintActions()
        p.deviceData.value = clone(f.device)
        p.scanMode.value = mode
        await p.switchMode(mode)
        await settle()
        expectAction(f, mode === 'process' ? 'check' : mode, 'clicking selected ' + mode + ' button immediately acts on loaded device')
        same(f.scans.length, 0, 'loaded device does not require another scan for ' + mode)
    }

    for (const mode of ['process', 'label']) {
        for (const input of ['imei', 'qr']) {
            const f = fixture(), p = f.page
            f.second.imei = '353938205192375'
            await p.loadManualPrintActions()
            await p.loadDevice(String(f.device.id), false)
            same(p.deviceData.value.id, f.device.id, 'manual replacement starts from loaded device A')
            p.manualText.value = input === 'qr' ? qr(f.second.id) : f.second.imei
            f.state.searchList = [f.second]
            await p.switchMode(mode)
            await settle()
            const label = `loaded A then manual ${input} B / ${mode}`
            same(p.deviceData.value.id, f.second.id, label + ': typed target replaces the old card before action')
            same(called(f, 'device').map(call => call[1]), [String(f.device.id), String(f.second.id)], label + ': loads B rather than reusing A')
            same(called(f, 'search').length, input === 'qr' ? 0 : 1, label + ': follows the new manual input lookup')
            expectAction(f, mode === 'process' ? 'check' : 'label', label)
            same(f.scans.length, 0, label + ': typed replacement does not require camera rescan')
            if (mode === 'label') {
                same(called(f, 'plan').map(call => String(call[2].device_id)), [String(f.second.id)], label + ': no label plan is opened for stale A')
                same(String(p.pendingPrintTarget.value.device_id), String(f.second.id), label + ': confirmation is bound only to B')
            }
        }
    }

    for (const mode of ['process', 'query', 'label']) {
        const f = fixture(), p = f.page
        await p.switchMode(mode)
        same(p.scanMode.value, mode, 'empty page selects ' + mode)
        same(f.scans.length, 1, 'empty page opens scanner with one ' + mode + ' click')
        await f.scanFail()
        same(called(f, 'device').length, 0, 'cancel does not query a device in ' + mode)
        same(f.toasts.length, 0, 'camera cancel is not shown as an error in ' + mode)
        await p.switchMode(mode)
        same(f.scans.length, 2, 'selected ' + mode + ' button can start again after cancel')
        await f.scanFail()
    }

    for (const mode of [undefined, 'invalid']) {
        const f = fixture()
        await f.route({ device_id: String(f.device.id), site_id: String(f.state.siteId), ...(mode ? { mode } : {}) })
        same(f.page.scanMode.value, 'query', 'external direct route without a valid mode safely defaults to query')
        same(f.page.deviceData.value.id, f.device.id, 'external safe route displays exact device')
        expectAction(f, '', 'external route without valid mode stays on device preview')
        same(f.scans.length, 0, 'external safe route does not open the camera')
    }

    for (const mode of ['process', 'query', 'label']) {
        const f = fixture()
        await f.route({ device_id: String(f.device.id), site_id: String(f.state.siteId), mode })
        same(f.page.scanMode.value, mode, 'explicit external mode ' + mode + ' is retained')
        expectAction(f, mode === 'process' ? 'check' : mode, 'explicit external mode ' + mode + ' immediately acts')
    }

    {
        const f = fixture(), p = f.page
        f.state.searchList = [f.device, f.second]
        await p.resolveScanKeyword(f.device.imei, true)
        same(p.candidateDevices.value.length, 2, 'duplicate IMEI requires choosing the record')
        same(called(f, 'device').length, 0, 'duplicate IMEI does not guess the first record')
        await p.switchMode('query')
        same(p.candidateDevices.value.length, 2, 'changing action keeps duplicate-record candidates')
        same(f.scans.length, 0, 'candidate mode change does not require rescanning')
        same(called(f, 'redirect').length, 0, 'candidate mode change waits for record selection')
        await p.selectCandidate(f.second)
        await settle()
        same(called(f, 'device').at(-1)[1], String(f.second.id), 'candidate selection loads the chosen exact ID')
        same(called(f, 'redirect').at(-1)[1].param.id, f.second.order_id, 'candidate selection immediately opens the chosen record order')
        noPrint(f, 'candidate selection')
    }

    {
        const f = fixture(), p = f.page
        p.deviceData.value = clone(f.device)
        await p.openPrintedDevice({ deviceId: String(f.device.id), siteId: 100006 })
        same(called(f, 'device').length, 0, 'cross-site QR is rejected before device lookup')
        same(p.deviceData.value, null, 'cross-site rejection clears the previous record')
        same(f.modals.at(-1).title, '站点不匹配', 'cross-site rejection explains required login')
        expectAction(f, '', 'cross-site QR cannot start an action')
    }

    {
        const f = fixture(), p = f.page
        f.state.failDevice = true
        p.deviceData.value = clone(f.second)
        await p.openPrintedDevice({ deviceId: String(f.device.id), siteId: f.state.siteId })
        await settle()
        same(p.deviceData.value, null, 'device permission failure cannot reuse an earlier device')
        same(f.toasts.at(-1).title, '设备无权限', 'device permission rejection remains explicit')
        expectAction(f, '', 'permission failure cannot start an action')
    }

    {
        const f = fixture(), p = f.page
        f.state.failSearch = true
        p.manualText.value = f.device.imei
        await p.handleManualSubmit()
        await settle()
        same(f.toasts.at(-1).title, '查询设备无权限', 'IMEI search permission rejection remains explicit')
        same(called(f, 'device').length, 0, 'failed search does not guess a device ID')
        expectAction(f, '', 'search permission failure cannot start an action')
    }

    {
        const f = fixture(), p = f.page
        await p.switchMode('process')
        await p.switchMode('label')
        p.scanDevice()
        p.manualText.value = f.device.imei
        await p.handleManualSubmit()
        same(f.scans.length, 1, 'rapid clicks cannot open more than one scanner')
        same(p.scanMode.value, 'process', 'action mode cannot change while camera is scanning')
        same(called(f, 'search').length, 0, 'manual input cannot race an open scanner')
        await f.scanResult(qr())
        expectAction(f, 'check', 'first scan still runs its selected processing action')
    }

    {
        const f = fixture(), p = f.page, gate = deferred()
        f.state.deviceGate = gate.promise
        p.manualText.value = qr()
        const pending = p.handleManualSubmit()
        await settle()
        await p.switchMode('query')
        p.scanDevice()
        await p.handleManualSubmit()
        same(called(f, 'device').length, 1, 'rapid clicks during device lookup do not issue duplicate loads')
        same(f.scans.length, 0, 'camera cannot open over an active lookup')
        same(p.scanMode.value, 'process', 'lookup keeps the action selected when lookup began')
        gate.release()
        await pending
        await settle()
        expectAction(f, 'check', 'one pending lookup opens one matching action')
    }

    {
        const f = fixture(5), gate = deferred()
        f.state.actionsGate = gate.promise
        const pending = f.route({ device_id: String(f.device.id), site_id: String(f.state.siteId), mode: 'label' })
        await settle()
        same(called(f, 'plan').length, 0, 'label action waits for its configuration request')
        same(f.page.labelEditVisible.value, false, 'configuration still loading does not fabricate a label editor')
        ok(!f.toasts.some(toast => /未配置/.test(toast.title)), 'slow configuration is not misreported as missing configuration')
        gate.release()
        await pending
        await settle()
        same(f.page.labelEditVisible.value, true, 'delayed label configuration opens editor when ready')
        noPrint(f, 'label_edit.enabled=0 still requires edit confirmation')
        f.page.labelEditForm.value.color = '银色'
        await f.page.confirmLabelPrint()
        same(called(f, 'print').length, 1, 'printing starts only after explicit label confirmation')
        same(called(f, 'print')[0][2].print_data_override, { color: '银色' }, 'confirmed print contains only the edited override')
        same(f.page.deviceData.value.status, 5, 'confirmed label print does not alter business status')
        same(f.scans.length, 0, 'label printing does not automatically scan the next device')
    }

    console.log(`[通过] ${count} 项扫码即时动作回归；真实 SFC 和打印 hook，隔离 API，不请求服务器、不实际打印。`)
}

main().catch(error => { console.error(error); process.exitCode = 1 })
