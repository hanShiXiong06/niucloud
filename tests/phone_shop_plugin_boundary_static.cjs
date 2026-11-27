'use strict'
// 仅解析文件、检查引用与发布副本，不启动页面、不调用接口、不连接数据库。
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const cp = require('node:child_process')
const root = path.resolve(__dirname, '..')
const deps = name => require(path.join(root, 'uni-app/node_modules', name))
const ts = deps('typescript')
const sfc = deps('@vue/compiler-sfc')
const { initPreContext, preJs, preHtml } = deps('@dcloudio/uni-cli-shared/dist/preprocess')
const read = file => fs.readFileSync(path.join(root, file), 'utf8')
const normalized = text => text.replace(/\r\n/g, '\n')
let checked = 0
const check = (name, fn) => { fn(); checked++; console.log('PASS ' + name) }
const pluginFiles = [
    'hooks/useGoodsPageAccess.ts', 'hooks/usePhoneShopLoginNavigation.ts', 'hooks/usePhoneShopPaymentAmount.ts',
    'components/PhoneShopPay.vue', 'pages/order/payment.vue', 'pages/order/list.vue', 'pages/order/detail.vue',
]
for (const file of pluginFiles) {
    check('插件运行/发布副本一致 ' + file, () => assert.equal(
        normalized(read('uni-app/src/addon/phone_shop/' + file)),
        normalized(read('niucloud/addon/phone_shop/uni-app/' + file))
    ))
}
for (const platform of ['h5', 'mp-weixin', 'app-plus']) {
    initPreContext(platform)
    for (const file of pluginFiles) {
        check(platform + ' 静态编译 ' + file, () => {
            let source = read('uni-app/src/addon/phone_shop/' + file)
            if (file.endsWith('.vue')) {
                const { descriptor, errors } = sfc.parse(preJs(preHtml(source)), { filename: file })
                assert.deepEqual(errors, [])
                const script = sfc.compileScript(descriptor, { id: 'phone-shop-boundary-' + platform })
                const template = sfc.compileTemplate({
                    id: 'phone-shop-boundary-' + platform,
                    filename: file, source: descriptor.template.content,
                    compilerOptions: { bindingMetadata: script.bindings },
                })
                assert.deepEqual(template.errors, [])
                source = script.content
            } else source = preJs(source)
            const result = ts.transpileModule(source, {
                reportDiagnostics: true,
                compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ESNext },
            })
            assert.deepEqual((result.diagnostics || []).filter(item => item.category === ts.DiagnosticCategory.Error), [])
        })
    }
}
const frameworkFiles = [
    'niucloud/app/api/controller/pay/Pay.php', 'niucloud/app/service/api/pay/PayService.php',
    'niucloud/app/service/core/pay/CorePayService.php', 'uni-app/src/components/pay/pay.vue',
    'uni-app/src/components/top-tabbar/top-tabbar.vue', 'uni-app/src/app/pages/auth/index.vue',
    'uni-app/src/app/pages/auth/login.vue', 'uni-app/src/hooks/useLogin.ts',
]
for (const file of frameworkFiles) check('本次框架修改已撤回 ' + file, () => {
    const original = cp.execFileSync('git', ['show', 'HEAD:' + file], { cwd: root, encoding: 'utf8' })
    assert.equal(read(file), original)
})
check('不再依赖新增的框架首页组件', () => {
    assert.equal(fs.existsSync(path.join(root, 'uni-app/src/hooks/useBackOrHome.ts')), false)
    assert.equal(fs.existsSync(path.join(root, 'uni-app/src/components/page-home/page-home.vue')), false)
})
check('通过已有 HttpRun 注册插件支付控制器中间件', () => {
    assert.match(read('niucloud/addon/phone_shop/app/event.php'), /'HttpRun'.*RegisterPaymentGuard/)
    assert.doesNotMatch(read('niucloud/addon/phone_shop/app/event.php'), /PayBeforeListener/)
    assert.match(read('niucloud/addon/phone_shop/app/listener/pay/RegisterPaymentGuard.php'), /middleware->controller\(PhoneShopPaymentGuard::class\)/)
})
check('公共收银台由插件包装使用，没有复制支付渠道实现', () => {
    const wrapper = read('uni-app/src/addon/phone_shop/components/PhoneShopPay.vue')
    assert.match(wrapper, /import FrameworkPay from '@\/components\/pay\/pay.vue'/)
    assert.doesNotMatch(wrapper, /uni\.requestPayment|switch\s*\(.*type/)
    for (const file of ['payment', 'list', 'detail']) {
        const source = read('uni-app/src/addon/phone_shop/pages/order/' + file + '.vue')
        assert.match(source, /<PhoneShopPay\s/)
        assert.doesNotMatch(source, /verify-amount/)
    }
})
check('支付与改价共同持有插件订单锁', () => {
    assert.match(read('niucloud/addon/phone_shop/app/middleware/PhoneShopPaymentGuard.php'), /withOrderLock/)
    assert.match(read('niucloud/addon/phone_shop/app/service/admin/order/OrderService.php'), /withOrderLock/)
    const service = read('niucloud/addon/phone_shop/app/service/core/order/CoreOrderPaymentGuardService.php')
    assert.match(service, /GET_LOCK/)
    assert.match(service, /finally\s*\{/)
    assert.match(service, /RELEASE_LOCK/)
    assert.doesNotMatch(service, /startTrans|Db::transaction/)
})
console.log(`静态检查完成：${checked} 项；未执行业务、网络、数据库或支付测试。`)
