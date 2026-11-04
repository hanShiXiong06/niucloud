#!/usr/bin/env node
// Actual scan SFC + print hook with isolated APIs: never contacts a printer/server.
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), app = path.join(root, 'site-uniapp'), src = path.join(app, 'src')
const req = require('node:module').createRequire(path.join(app, 'package.json'))
const ts = req('typescript'), vue = req('vue'), compiler = req('@vue/compiler-sfc')
const clone = value => JSON.parse(JSON.stringify(value))
let count = 0
const same = (actual, expected, label) => { assert.deepEqual(clone(actual), clone(expected), label); count++ }
const ok = (value, label) => { assert.ok(value, label); count++ }
function fixture() {
    const calls = [], toasts = [], actions = [{ scene_key: 'label-a', template_type: 'device_label', button_position: 'device_actions', visible_device_status: [1, 2], confirm_required: 0 }]
    const device = { id: 3036, order_id: 2527, status: 5, confirm_status: 1, pay_status: 1, model: '苹果 iPhone 14 Pro', imei: '353938205192374' }
    const state = { failPlan: false, failDevice: false, planGate: null }
    const printerApi = {
        getPrintSceneManualActions: async () => ({ code: 1, data: actions }),
        getPrintScenePlan: async (scene, payload) => {
            calls.push(['plan', scene, clone(payload)])
            if (state.planGate) await state.planGate
            if (state.failPlan) throw { msg: '没有打印权限' }
            return { code: 1, data: { can_print: true, label_edit: { enabled: 0, fields: [{ key: 'model', label: '设备型号', value: device.model }, { key: 'color', label: '颜色', value: '黑色' }] } } }
        },
        printByScene: async (scene, payload) => { calls.push(['print', scene, clone(payload)]); return { code: 1, msg: '打印任务已发送' } },
    }
    const uni = {
        showToast: data => toasts.push(data), showLoading() {}, hideLoading() {},
        showModal: data => data.success?.({ confirm: true }),
        showActionSheet: data => data.success?.({ tapIndex: 0 }),
        getStorageSync: () => 100005,
    }
    const modules = {}
    function evaluate(relative) {
        if (modules[relative]) return modules[relative]
        const file = path.join(src, relative)
        let source = fs.readFileSync(file, 'utf8')
        if (file.endsWith('.vue')) {
            const { descriptor, errors } = compiler.parse(source, { filename: file }); same(errors, [], 'scan SFC parses')
            const script = compiler.compileScript(descriptor, { id: 'scan-label-test' })
            same(compiler.compileTemplate({ source: descriptor.template.content, filename: file, id: 'scan-label-test', compilerOptions: { bindingMetadata: script.bindings } }).errors, [], 'scan template compiles')
            ok(descriptor.template.content.includes('@click="runCurrentMode"'), 'primary action dispatches by scan mode')
            source = script.content
        }
        const module = { exports: {} }
        vm.runInNewContext(ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, {
            module, exports: module.exports, console, uni, setTimeout, clearTimeout,
            require(id) {
                if (id === 'vue') return vue
                if (id === '@dcloudio/uni-app') return { onLoad() {}, onShow() {} }
                if (id === '@/utils/common') return { getToken: () => 'test-token', redirect: data => calls.push(['redirect', clone(data)]) }
                if (id === '@/hooks/useLogin') return { useLogin: () => ({ setLoginBack() {} }) }
                if (id.endsWith('/api/printer')) return printerApi
                if (id.endsWith('/api/order')) return { scanSearchDevice: async () => ({ data: [device] }), getDevice: async id => { calls.push(['device', id]); if (state.failDevice) throw { msg: '设备无权限' }; return { data: device } } }
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
    const hook = evaluate('addon/hsx_recycle/hooks/useRecyclePrintActions.ts').useRecyclePrintActions('device')
    return { page, hook, device, actions, calls, toasts, state }
}
async function main() {
    const f = fixture(), p = f.page
    await f.hook.loadManualPrintActions()
    same(f.hook.getVisiblePrintActions({ status: 5 }), [], 'ordinary printing keeps configured status filter')
    same(f.hook.getVisiblePrintActions({ status: 5 }, { ignoreBusinessStatus: true }).length, 1, 'scan label allows recovered devices')
    f.actions.push({ scene_key: 'receipt', template_type: 'order_receipt', button_position: 'device_actions', visible_device_status: [1] })
    same(f.hook.getVisiblePrintActions({ status: 5 }, { ignoreBusinessStatus: true }).length, 1, 'scan bypass is limited to device labels')
    f.actions.pop()
    await p.loadManualPrintActions()
    for (const status of [1, 2, 3, 4, 5, 6, 7, 8, 9]) {
        p.deviceData.value = { ...f.device, status }
        p.scanMode.value = 'label'
        p.closeLabelEdit()
        await p.openLabelAction()
        same(p.labelEditVisible.value, true, 'status ' + status + ' opens label editing, including received/returned/consignment')
        same(p.primaryActionText.value, '编辑打印标签', 'label mode button does not offer inspection/pricing')
        same(p.deviceData.value.status, status, 'opening does not modify business status')
        same(f.calls.at(-1)[2].entry, 'scan_label', 'plan carries explicit scan entry')
    }
    same(f.calls.filter(c => c[0] === 'print'), [], 'no printing before explicit confirmation')
    p.labelEditForm.value.color = '银色'
    await p.confirmLabelPrint()
    const printed = f.calls.find(c => c[0] === 'print')
    same(printed[2].entry, 'scan_label', 'print execution retains scoped entry')
    same(printed[2].print_data_override, { color: '银色' }, 'only edited label text is sent as print override')
    same(p.deviceData.value.status, 9, 'printing does not update device workflow state')
    same(f.calls.filter(c => c[0] === 'plan').at(-1)[2].entry, 'scan_label', 'final preflight preserves scoped entry')
    p.closeLabelEdit(); f.state.failPlan = true
    await p.openLabelAction()
    same(p.labelEditVisible.value, false, 'failed plan does not open a fabricated empty editor')
    same(f.toasts.at(-1).title, '没有打印权限', 'permission rejection stays explicit')
    f.state.failPlan = false
    p.scanMode.value = 'process'; p.deviceData.value = { ...f.device, status: 5 }
    p.runCurrentMode()
    same(p.checkPopupVisible.value, false, 'ordinary recovered-device inspection remains blocked')
    same(p.pricePopupVisible.value, false, 'ordinary recovered-device repricing remains blocked')
    p.deviceData.value.status = 1; p.runCurrentMode()
    same(p.checkPopupVisible.value, true, 'ordinary pending inspection still opens inspection')
    p.checkPopupVisible.value = false; p.deviceData.value.status = 3; p.runCurrentMode()
    same(p.pricePopupVisible.value, true, 'ordinary checked device still opens pricing')
    p.pricePopupVisible.value = false; p.scanMode.value = 'label'
    await p.openPrintedDevice({ deviceId: '3036', siteId: 100005 })
    same(p.scanMode.value, 'label', 'explicit scan label mode survives direct device QR code')
    same(p.labelEditVisible.value, true, 'direct QR in label mode opens editor')
    p.closeLabelEdit(); p.scanMode.value = 'query'
    await p.openPrintedDevice({ deviceId: '3036', siteId: 100005 })
    same(p.labelEditVisible.value, false, 'query QR never auto-opens label editor')
    f.actions.length = 0; await p.loadManualPrintActions(); p.scanMode.value = 'label'
    const redirects = f.calls.filter(c => c[0] === 'redirect').length
    await p.openLabelAction()
    ok(f.toasts.at(-1).title.includes('未配置'), 'missing scene gets configuration message, not status message')
    same(f.calls.filter(c => c[0] === 'redirect').length, redirects, 'missing scene does not silently redirect away')
    f.state.failDevice = true
    await p.openPrintedDevice({ deviceId: '3036', siteId: 100005 })
    same(p.deviceData.value, null, 'device permission error clears old record')
    for (const change of ['device', 'order', 'mode', 'cancel']) {
        const r = fixture(), q = r.page
        await q.loadManualPrintActions()
        q.deviceData.value = { ...r.device }; q.scanMode.value = 'label'
        let release
        r.state.planGate = new Promise(resolve => { release = resolve })
        const pending = q.openLabelAction()
        if (change === 'device') q.deviceData.value = { ...r.device, id: 3037 }
        if (change === 'order') q.deviceData.value = { ...r.device, order_id: 2528 }
        if (change === 'mode') q.switchMode('query')
        if (change === 'cancel') q.closeLabelEdit()
        release(); await pending
        same(q.labelEditVisible.value, false, change + ' change invalidates in-flight label plan')
        same(q.pendingPrintAction.value, null, change + ' change does not retain stale pending print')
    }
    {
        const r = fixture(), q = r.page
        await q.loadManualPrintActions(); q.deviceData.value = { ...r.device }; q.scanMode.value = 'label'
        await q.openLabelAction()
        q.deviceData.value = { ...r.device, id: 3037 }
        await q.confirmLabelPrint()
        same(r.calls.filter(c => c[0] === 'print'), [], 'confirmation cannot apply A label plan to B device')
        same(r.toasts.at(-1).title, '设备已切换，请重新打开标签', 'device mismatch explains why confirmation was stopped')
    }
    console.log(`[通过] ${count} 项扫码打标状态隔离回归；真实SFC和打印hook，不请求服务器、不实际打印。`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
