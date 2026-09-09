'use strict'
// 文件解析、真实 uni-app 模板编译器、隔离授权调用测试。不连接业务服务或微信。
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '..')
const dep = name => require(path.join(root, 'uni-app/node_modules', name))
const ts = dep('typescript')
const sfc = dep('@vue/compiler-sfc')
const mp = dep('@dcloudio/uni-mp-compiler')
const { initPreContext, preJs, preHtml } = dep('@dcloudio/uni-cli-shared/dist/preprocess')
const read = file => fs.readFileSync(path.join(root, file), 'utf8')
const mobile = 'uni-app/src/addon/phone_shop/'
let checks = 0
const check = (label, fn) => { fn(); checks++; console.log('PASS ' + label) }
const files = [
    'components/GoodsArrivalSubscription.vue', 'components/PhoneGoodsWaterfall.vue',
    'components/goods-filter/GoodsCategoryFilterPopup.vue', 'components/goods-filter/GoodsMoreFilterPopup.vue',
    'pages/goods/category.vue', 'pages/goods/list.vue',
]
for (const platform of ['h5', 'mp-weixin']) {
    process.env.UNI_PLATFORM = platform
    process.env.UNI_INPUT_DIR = path.join(root, 'uni-app/src')
    initPreContext(platform)
    for (const file of files) check(platform + ' SFC/TS 编译 ' + file, () => {
        const parsed = sfc.parse(preJs(preHtml(read(mobile + file))), { filename: file })
        assert.deepEqual(parsed.errors, [])
        const script = sfc.compileScript(parsed.descriptor, { id: 'arrival-test' })
        const source = parsed.descriptor.template.content
        const template = sfc.compileTemplate({ source, filename: file, id: 'arrival-test', compilerOptions: { bindingMetadata: script.bindings } })
        assert.deepEqual(template.errors, [])
        const result = ts.transpileModule(script.content, { reportDiagnostics: true, compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ESNext } })
        assert.deepEqual((result.diagnostics || []).filter(d => d.category === ts.DiagnosticCategory.Error), [])
        if (platform === 'mp-weixin') {
            const errors = [], output = []
            mp.compile(source, {
                mode: 'module', prefixIdentifiers: true, filename: path.join(root, mobile, file),
                bindingMetadata: script.bindings,
                onError: error => errors.push(error),
                miniProgram: {
                    directive: 'wx:', class: { array: true }, event: { key: true },
                    slot: { fallbackContent: false, dynamicSlotNames: true },
                    component: { vShow: 'hidden', normalizeName: name => name },
                    emitFile: asset => output.push(asset.source),
                },
            })
            assert.deepEqual(errors, [])
            assert.ok(output.length)
            if (file.includes('Waterfall')) {
                assert.match(output.join(''), /wx:for/)
                assert.match(output.join(''), /slot/)
            }
        }
    })
}
for (const file of ['goods-arrival-notice.vue', 'goods-transfer-dialog.vue']) check('后台 SFC/TS 编译 ' + file, () => {
    const filename = 'admin/src/addon/phone_shop/views/goods/components/' + file
    const parsed = sfc.parse(read(filename), { filename })
    assert.deepEqual(parsed.errors, [])
    const script = sfc.compileScript(parsed.descriptor, { id: 'arrival-admin' })
    const template = sfc.compileTemplate({ source: parsed.descriptor.template.content, filename, id: 'arrival-admin', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    assert.deepEqual(ts.transpileModule(script.content, { reportDiagnostics: true }).diagnostics || [], [])
})

function loadHook(platform, api, uni) {
    initPreContext(platform)
    const source = preJs(read(mobile + 'hooks/useGoodsSubscriptionNotice.ts'))
    const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const exports = {}
    vm.runInNewContext(code, { exports, Promise, uni, require: name => name === 'vue' ? { ref: value => ({ value }) } : { getGoodsSubscriptionCapability: api } })
    return exports.useGoodsSubscriptionNotice()
}
async function run() {
    let calls = 0, requests = 0
    const api = async () => { calls++; return { data: { enabled: true, template_id: 'test-template' } } }
    const hook = loadHook('mp-weixin', api, { requestSubscribeMessage: options => { requests++; options.success({ 'test-template': 'accept' }) } })
    assert.equal((await hook.requestAuthorization()).status, 'not_ready')
    check('未准备模板不弹授权', () => assert.equal(requests, 0))
    await hook.prepare()
    const pending = hook.requestAuthorization()
    check('点击处理器同步拉起授权，中间没有等待网络', () => assert.equal(requests, 1))
    const result = await pending
    check('授权只认本次模板 accept', () => assert.equal(result.status, 'accepted'))
    check('授权阶段不重复请求模板', () => assert.equal(calls, 1))
    const rejected = loadHook('mp-weixin', api, { requestSubscribeMessage: options => options.success({ 'another-template': 'accept' }) })
    await rejected.prepare()
    assert.equal((await rejected.requestAuthorization()).status, 'rejected')
    checks++; console.log('PASS 其他模板同意不能冒认当前同意')
    const h5 = loadHook('h5', api, { requestSubscribeMessage: () => { throw new Error('H5 must not call wx') } })
    assert.equal((await h5.requestAuthorization()).status, 'unsupported'); checks++; console.log('PASS H5 不伪装微信授权')
    const disabled = loadHook('mp-weixin', async () => ({ data: { enabled: false } }), { requestSubscribeMessage: () => { throw new Error('disabled must not call wx') } })
    await disabled.prepare()
    assert.equal((await disabled.requestAuthorization()).status, 'not_configured'); checks++; console.log('PASS 未开通不拉起授权')

    const waterfall = read(mobile + 'components/PhoneGoodsWaterfall.vue')
    check('双列共用同一个循环插槽声明，列宽显式设置', () => {
        assert.equal((waterfall.match(/<slot /g) || []).length, 1)
        assert.match(waterfall, /\[columns.left, columns.right\]/)
        assert.match(waterfall, /width: calc\(\(100% - 12rpx\) \/ 2\)/)
    })
    check('单行封面有定宽外壳，右侧有可收缩空间', () => {
        const list = read(mobile + 'pages/goods/list.vue')
        assert.match(list, /<view class="goods-row-cover"><PhoneGoodsCover/)
        assert.match(list, /\.goods-row-cover\s*\{[^}]*flex:\s*0 0 190rpx/s)
        assert.match(list, /\.goods-row-content\s*\{[^}]*min-width:\s*0/s)
    })
    const mirrorFiles = [...files, 'hooks/useGoodsSubscriptionNotice.ts', 'api/goods.ts']
    for (const file of mirrorFiles) check('移动端发布副本一致 ' + file, () => assert.equal(
        read(mobile + file).replace(/\r\n/g, '\n'), read('niucloud/addon/phone_shop/uni-app/' + file).replace(/\r\n/g, '\n')
    ))
    for (const file of ['api/goods.ts', 'views/goods/components/goods-arrival-notice.vue', 'views/goods/components/goods-transfer-dialog.vue']) check('后台发布副本一致 ' + file, () => assert.equal(
        read('admin/src/addon/phone_shop/' + file).replace(/\r\n/g, '\n'), read('niucloud/addon/phone_shop/admin/' + file).replace(/\r\n/g, '\n')
    ))
    console.log(`完成 ${checks} 项前端隔离/编译检查；不代表真机布局与真实微信通知已经验收。`)
}
run().catch(error => { console.error(error); process.exitCode = 1 })
