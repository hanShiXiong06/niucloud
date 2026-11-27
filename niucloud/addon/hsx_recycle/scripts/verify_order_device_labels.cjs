'use strict'

// 运行真实页面脚本/字典解析逻辑，所有接口使用内存样本；不登录、不改订单、不打印。
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../../../..')
const src = path.join(root, 'site-uniapp/src')
const dep = name => require(path.join(root, 'site-uniapp/node_modules', name))
const ts = dep('typescript'), sfc = dep('@vue/compiler-sfc'), vue = dep('vue')
const { initPreContext, preJs, preHtml } = dep('@dcloudio/uni-cli-shared/dist/preprocess')
const flush = async () => { for (let i = 0; i < 30; i++) await Promise.resolve() }
const plain = value => JSON.parse(JSON.stringify(value))
const deferred = () => { let resolve, reject; const promise = new Promise((a, b) => { resolve = a; reject = b }); return { resolve, reject, promise } }
let checks = 0
async function check(name, fn) { await fn(); checks++; console.log('PASS ' + name) }
const schema = (id, capacity, color, resolve = {}) => ({ data: {
    template: { id }, resolve,
    groups: [{ fields: [
        { field_key: 'capacity', options: [{ value: '2', label: capacity }] },
        { field_key: 'color', options: [{ value: '2', label: color }] }
    ] }]
} })
const android = { id: 1, model: '安卓设备', check_template_id: 10, capacity: '2', color: '2', info: {} }
const iphone = { id: 2, model: '苹果 iPhone 16', check_template_id: 20, capacity: '2', color: '2', info: {} }
const schemaApi = async p => p.template_id === 10 ? schema(10, '12G+1T', '釉白') : schema(20, '256GB', '黑色')
const transpile = source => ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS } }).outputText

function hookHarness(api) {
    const context = { exports: {}, console, require(name) {
        if (name === 'vue') return vue
        if (name.endsWith('/api/check-template')) return { getCheckTemplateSchema: api }
        throw new Error('未模拟依赖：' + name)
    } }
    vm.runInNewContext(transpile(fs.readFileSync(path.join(src, 'addon/hsx_recycle/hooks/useDeviceOptionLabels.ts'), 'utf8')), context)
    return context.exports.useDeviceOptionLabels()
}

function pageHarness(api, getOrderDetail) {
    initPreContext('h5')
    const filename = path.join(src, 'addon/hsx_recycle/pages/order/detail.vue')
    const source = preJs(preHtml(fs.readFileSync(filename, 'utf8')))
    const script = sfc.parse(source).descriptor.scriptSetup.content + '\nexport { loadDetail, order, getDeviceSummary, loading };'
    const context = { exports: {}, console, uni: { showToast() {} }, require(name) {
        if (name === 'vue') return { ...vue, onUnmounted() {} }
        if (name === '@dcloudio/uni-app') return { onLoad() {} }
        if (name.endsWith('/api/order')) return { getOrderDetail }
        if (name.endsWith('/api/check-template')) return { getCheckTemplateSchema: api }
        if (name.endsWith('/hooks/useDeviceOptionLabels')) return { useDeviceOptionLabels: () => hookHarness(api) }
        if (name.endsWith('/hooks/useRecyclePrintActions')) return { useRecyclePrintActions: () => ({ loadManualPrintActions() {}, getVisiblePrintActions() { return [] }, executePrintAction() {} }) }
        return {}
    } }
    vm.runInNewContext(transpile(script), context)
    return context.exports
}

function popupHarness(api, getDevice) {
    initPreContext('h5')
    const filename = path.join(src, 'addon/hsx_recycle/pages/order/components/DeviceDetailPopup.vue')
    const props = { visible: true, deviceData: android }
    const script = sfc.parse(preJs(preHtml(fs.readFileSync(filename, 'utf8')))).descriptor.scriptSetup.content
        + '\nexport { loadDeviceDetail, device, basicRows, loading, costAdjustAllowed };'
    const context = { exports: {}, console, defineProps: () => props, defineEmits: () => () => {}, uni: { showToast() {} }, require(name) {
        if (name === 'vue') return { ...vue, onUnmounted() {}, watch() {} }
        if (name.endsWith('/api/order')) return {
            getDevice,
            getDeviceCostAdjustAbility: async () => ({ data: { allowed: false } }),
            getDeviceCostAdjustLogs: async () => ({ data: [] }),
            adjustDeviceCost() { throw new Error('测试不允许调整成本') }
        }
        if (name.endsWith('/hooks/useDeviceOptionLabels')) return { useDeviceOptionLabels: () => hookHarness(api) }
        if (name.endsWith('/utils/helper')) return { formatTime: String, formatMoney: String }
        return {}
    } }
    vm.runInNewContext(transpile(script), context)
    return { ...context.exports, props }
}

async function run() {
    await check('同一订单混合机型：iPhone 不使用首台安卓的容量/颜色字典', async () => {
        const p = pageHarness(schemaApi, async () => ({ data: { devices: [android, iphone] } }))
        await p.loadDetail(); await flush()
        const summary = Object.fromEntries(p.getDeviceSummary(iphone).map(item => [item.label, item.value]))
        assert.equal(summary['内存'], '256GB'); assert.equal(summary['颜色'], '黑色')
        assert.equal(p.getDeviceSummary(android)[0].value, '12G+1T')
    })

    await check('明确相同模板可复用请求，不同模板独立解析', async () => {
        const calls = [], h = hookHarness(async p => { calls.push(p); return schemaApi(p) })
        await h.loadOptionLabels([android, iphone, { ...android, id: 3 }])
        assert.equal(calls.length, 2)
        assert.equal(h.resolveOptionLabel(iphone, 'capacity', '2'), '256GB')
    })

    await check('无模板编号时逐设备解析，不能共用 template=0 缓存', async () => {
        const calls = [], a = { ...android, check_template_id: 0 }, b = { ...iphone, check_template_id: 0 }
        const h = hookHarness(async p => {
            calls.push(p)
            return p.device_id === 1 ? schema(10, '12G+1T', '釉白', { matched: true, source_type: 'model_dict' }) : schema(20, '256GB', '黑色', { matched: true, source_type: 'model_dict' })
        })
        await h.loadOptionLabels([a, b])
        assert.deepEqual(plain(calls), [{ device_id: 1 }, { device_id: 2 }])
        assert.equal(h.resolveOptionLabel(b, 'color', '2'), '黑色')
    })

    await check('设备列无模板时读取同设备 meta 的模板编号', async () => {
        const calls = [], d = { ...iphone, check_template_id: 0, info: JSON.stringify({ check_meta: { template_id: 20 } }) }
        const h = hookHarness(async p => { calls.push(p); return schemaApi(p) })
        await h.loadOptionLabels([d])
        assert.deepEqual(plain(calls), [{ template_id: 20 }]); assert.equal(h.resolveOptionLabel(d, 'capacity', '2'), '256GB')
    })

    await check('无明确型号绑定的默认模板不能解释设备编号', async () => {
        for (const resolve of [{ matched: false }, { matched: true, source_type: 'global' }]) {
            const d = { ...iphone, check_template_id: 0 }, h = hookHarness(async () => schema(10, '12G+1T', '釉白', resolve))
            await h.loadOptionLabels([d]); assert.equal(h.resolveOptionLabel(d, 'color', '2'), '2')
        }
    })

    await check('加载失败不借用其他设备字典，重试可恢复', async () => {
        let fail = true
        const h = hookHarness(async p => { if (fail && p.template_id === 20) throw new Error('模拟失败'); return schemaApi(p) })
        await h.loadOptionLabels([android, iphone])
        assert.equal(h.resolveOptionLabel(iphone, 'color', '2'), '2')
        fail = false; await h.loadOptionLabels([android, iphone])
        assert.equal(h.resolveOptionLabel(iphone, 'color', '2'), '黑色')
    })

    await check('接口返回的模板与明确请求编号不一致时不误用', async () => {
        const h = hookHarness(async () => schema(10, '12G+1T', '釉白'))
        await h.loadOptionLabels([iphone]); assert.equal(h.resolveOptionLabel(iphone, 'capacity', '2'), '2')
    })

    await check('同设备更换模板及刷新后，较慢旧响应不能覆盖新映射', async () => {
        const slow = deferred(), h = hookHarness(async p => p.template_id === 10 ? slow.promise : schemaApi(p))
        const old = h.loadOptionLabels([android]), changed = { ...android, check_template_id: 20 }
        await h.loadOptionLabels([changed])
        slow.resolve(schema(10, '12G+1T', '釉白')); await old
        assert.equal(h.resolveOptionLabel(changed, 'color', '2'), '黑色')
        assert.equal(h.resolveOptionLabel(android, 'color', '2'), '2')
    })

    await check('清空/切换设备时立即清除旧映射，迟到响应被忽略', async () => {
        const slow = deferred(), h = hookHarness(() => slow.promise), request = h.loadOptionLabels([android])
        h.resetOptionLabels(); slow.resolve(schema(10, '12G+1T', '釉白')); await request
        assert.equal(h.resolveOptionLabel(android, 'color', '2'), '2')
        await h.loadOptionLabels([]); assert.equal(h.resolveOptionLabel(iphone, 'color', '黑色'), '黑色')
    })

    await check('数组、已有文字及空值保持正确，显示解析不修改原始设备', async () => {
        const data = plain([android, iphone]), before = JSON.stringify(data), h = hookHarness(schemaApi)
        await h.loadOptionLabels(data)
        assert.equal(h.resolveOptionLabel(data[1], 'color', ['2', '已有文字']), '黑色、已有文字')
        assert.equal(h.resolveOptionLabel(data[1], 'color', null), '')
        assert.equal(h.resolveOptionLabel(data[1], 'color', '已有文字'), '已有文字')
        assert.equal(JSON.stringify(data), before)
    })

    await check('订单详情请求乱序时旧订单不能覆盖新订单', async () => {
        const slow = deferred(); let count = 0
        const p = pageHarness(schemaApi, async () => ++count === 1 ? slow.promise : { data: { devices: [iphone] } })
        const old = p.loadDetail(); await p.loadDetail(); await flush()
        slow.resolve({ data: { devices: [android] } }); await old; await flush()
        assert.equal(p.order.value.devices[0].id, 2); assert.equal(p.loading.value, false)
    })

    await check('设备详情弹窗切换机器时不沿用上一台模板', async () => {
        const p = popupHarness(schemaApi, async id => ({ data: id === 1 ? android : iphone }))
        await p.loadDeviceDetail(); await flush()
        assert.equal(p.basicRows.value.find(item => item.label === '容量').value, '12G+1T')
        p.props.deviceData = iphone
        await p.loadDeviceDetail(); await flush()
        assert.equal(p.basicRows.value.find(item => item.label === '容量').value, '256GB')
        assert.equal(p.basicRows.value.find(item => item.label === '颜色').value, '黑色')
    })

    await check('弹窗旧设备请求迟到，不能覆盖新设备或重新写入旧字典', async () => {
        const slow = deferred(), p = popupHarness(schemaApi, async id => id === 1 ? slow.promise : { data: iphone })
        const old = p.loadDeviceDetail(); p.props.deviceData = iphone
        await p.loadDeviceDetail(); await flush()
        slow.resolve({ data: android }); await old; await flush()
        assert.equal(p.device.value.id, 2)
        assert.equal(p.basicRows.value.find(item => item.label === '颜色').value, '黑色')
        assert.equal(p.loading.value, false)
    })

    await check('同一页面最多4个模板请求并行，不阻塞其他设备的独立解析', async () => {
        const calls = [], data = Array.from({ length: 9 }, (_, i) => ({ id: i + 1, check_template_id: i + 100 }))
        const h = hookHarness(p => { const task = deferred(); calls.push({ ...task, id: p.template_id }); return task.promise })
        const pending = h.loadOptionLabels(data)
        assert.equal(calls.length, 4)
        for (let start = 0; start < 9; start += 4) {
            calls.slice(start, start + 4).forEach(call => call.resolve(schema(call.id, '256GB', '黑色')))
            await flush()
        }
        await pending; assert.equal(calls.length, 9)
        assert.equal(h.resolveOptionLabel(data[8], 'color', '2'), '黑色')
    })

    await check('新字典解析 Hook 的 TypeScript 严格类型检查', () => {
        const filename = path.join(src, 'addon/hsx_recycle/hooks/useDeviceOptionLabels.ts')
        const stub = path.join(src, '__device_labels_api_test__.d.ts')
        const options = { noEmit: true, strict: true, skipLibCheck: true, types: [], target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ESNext, moduleResolution: ts.ModuleResolutionKind.NodeJs }
        const host = ts.createCompilerHost(options), originalGet = host.getSourceFile.bind(host)
        host.getSourceFile = (file, ...args) => file === stub
            ? ts.createSourceFile(file, 'export function getCheckTemplateSchema(params: Record<string, any>): Promise<any>;', ts.ScriptTarget.ES2020)
            : originalGet(file, ...args)
        host.resolveModuleNames = (names, containingFile) => names.map(name => name.endsWith('/api/check-template')
            ? { resolvedFileName: stub, extension: ts.Extension.Dts }
            : ts.resolveModuleName(name, containingFile, options, ts.sys).resolvedModule)
        const errors = ts.getPreEmitDiagnostics(ts.createProgram([filename], options, host))
        assert.deepEqual(errors.map(error => ts.flattenDiagnosticMessageText(error.messageText, '\n')), [])
    })

    for (const platform of ['h5', 'mp-weixin']) await check(platform + ' 页面和设备详情弹窗的脚本/模板/样式编译', () => {
        process.env.UNI_PLATFORM = platform; process.env.UNI_INPUT_DIR = src; initPreContext(platform)
        for (const relative of ['pages/order/detail.vue', 'pages/order/components/DeviceDetailPopup.vue']) {
            const filename = path.join(src, 'addon/hsx_recycle', relative)
            const parsed = sfc.parse(preJs(preHtml(fs.readFileSync(filename, 'utf8'))), { filename })
            assert.deepEqual(parsed.errors, [])
            const script = sfc.compileScript(parsed.descriptor, { id: 'device-label-test' })
            const result = ts.transpileModule(script.content, { reportDiagnostics: true, compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2020 } })
            assert.deepEqual(result.diagnostics, [])
            assert.deepEqual(sfc.compileTemplate({ source: parsed.descriptor.template.content, filename, id: 'device-label-test', compilerOptions: { bindingMetadata: script.bindings } }).errors, [])
            for (const style of parsed.descriptor.styles) {
                assert.deepEqual(sfc.compileStyle({ source: style.content, filename, id: 'device-label-test', preprocessLang: style.lang }).errors, [])
            }
        }
    })
    console.log(`完成：${checks} 组检查；没有真实接口请求或订单写入。`)
}

run().catch(error => { console.error(error); process.exitCode = 1 })
