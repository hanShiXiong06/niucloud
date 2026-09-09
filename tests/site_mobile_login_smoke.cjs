'use strict'
// 只在内存执行真实登录脚本，不访问服务端、短信或客户账号。
const assert = require('node:assert/strict')
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm')
const root = path.resolve(__dirname, '..'), src = path.join(root, 'site-uniapp/src')
const dep = name => require(path.join(root, 'site-uniapp/node_modules', name))
const ts = dep('typescript'), sfc = dep('@vue/compiler-sfc'), vue = dep('vue')
const { initPreContext, preJs, preHtml } = dep('@dcloudio/uni-cli-shared/dist/preprocess')
const flush = async () => { for (let i = 0; i < 20; i++) await Promise.resolve() }
const deferred = () => { let resolve, reject; const promise = new Promise((a, b) => { resolve = a; reject = b }); return { resolve, reject, promise } }
const plain = value => JSON.parse(JSON.stringify(value))
const home = '/app/pages/index/index', login = '/app/pages/auth/login'
const detail = '/addon/hsx_member_card/pages/member/detail'
let count = 0
async function check(name, fn) { await fn(); count++; console.log('PASS ' + name) }
function hookHarness(cached, outcomes = [true]) {
    const storage = new Map([['loginBack', cached]]), navigations = [], notices = [], timers = new Map()
    let timerId = 0
    const context = {
        exports: {}, console,
        setTimeout: (fn, delay) => { const id = ++timerId; timers.set(id, fn); if (!delay) { fn(); timers.delete(id) }; return id },
        clearTimeout: id => timers.delete(id),
        uni: {
            setStorageSync: (k, v) => storage.set(k, v), getStorageSync: k => storage.get(k),
            removeStorageSync: k => storage.delete(k), showToast: v => notices.push(v),
            reLaunch: options => navigate({ ...options, mode: 'reLaunch' })
        },
        require: name => {
            if (name === '@/utils/common') return { redirect: navigate }
            if (name === '@/utils/pages') return { getAppPages: () => [home, login], getSubPackagesPages: () => [detail], getTabbarPages: () => [home] }
            return {}
        }
    }
    function navigate(options) {
        navigations.push(options)
        const outcome = outcomes.shift()
        if (outcome === 'throw') throw new Error('navigation error')
        if (outcome === null) return
        if (outcome) options.success?.()
        else options.fail?.()
    }
    const code = ts.transpileModule(fs.readFileSync(path.join(src, 'hooks/useLogin.ts'), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    vm.runInNewContext(code, context)
    return { ...context.exports.useLogin(), storage, navigations, notices, timers }
}
function pageHarness(api = {}, opened = true) {
    initPreContext('h5')
    const source = preJs(preHtml(fs.readFileSync(path.join(src, 'app/pages/auth/login.vue'), 'utf8')))
    const names = ['handleLogin', 'loginFn', 'success', 'formRef', 'formData', 'loading', 'loginError', 'authenticated', 'type', 'verifyRef', 'loginStage']
    const script = sfc.parse(source).descriptor.scriptSetup.content + '\nexport { ' + names.join(',') + ' };'
    const events = { requests: [], back: 0, menus: 0, verified: 0 }
    const store = { token: '', setUserInfo() {}, setSiteInfo() {}, setToken(v) { this.token = v } }
    const context = {
        exports: {}, console, Promise, setTimeout: () => 0,
        uni: { setStorageSync() {}, $u: { test: { mobile: v => /^1\d{10}$/.test(v) } } },
        require: name => {
            if (name === 'vue') return { ...vue, onMounted() {} }
            if (name === '@/stores/user') return { default: () => store }
            if (name === '@/stores/system') return { default: () => ({ getSiteNavsFn() { events.menus++ } }) }
            if (name === '@/hooks/useLogin') return { useLogin: () => ({ handleLoginBack: async () => { events.back++; return typeof opened === 'function' ? opened() : opened } }) }
            if (name === '@/app/api/auth') return {
                getLoginConfig: async () => ({ data: { is_site_captcha: 0 } }),
                usernameLogin: async p => { events.requests.push(p); return { data: { token: 'local-mock-token', site_id: 1 } } },
                mobileLogin: async p => { events.requests.push(p); return { data: { token: 'local-mock-token', site_id: 1 } } }, ...api
            }
            if (name === '@/locale') return { t: key => key }
            if (name === '@/utils/common') return { img: v => v, pxToRpx: v => v }
            if (name === '@/utils/topTabbar') return { topTabar: () => ({ setTopTabbarParam: () => ({}) }) }
            if (name.endsWith('.vue')) return {}
            throw new Error('未模拟依赖：' + name)
        }
    }
    const code = ts.transpileModule(script, { compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS } }).outputText
    vm.runInNewContext(code, context)
    context.exports.formRef.value = { validate: async () => true }
    context.exports.verifyRef.value = { show() { events.verified++ } }
    return { v: context.exports, events, store }
}
async function run() {
    for (const platform of ['h5', 'mp-weixin']) await check(platform + ' 登录页真实脚本与模板编译', () => {
        process.env.UNI_PLATFORM = platform; process.env.UNI_INPUT_DIR = src; initPreContext(platform)
        const filename = path.join(src, 'app/pages/auth/login.vue')
        const parsed = sfc.parse(preJs(preHtml(fs.readFileSync(filename, 'utf8'))), { filename })
        assert.deepEqual(parsed.errors, [])
        const script = sfc.compileScript(parsed.descriptor, { id: 'login-test' })
        assert.deepEqual(sfc.compileTemplate({ source: parsed.descriptor.template.content, filename, id: 'login-test', compilerOptions: { bindingMetadata: script.bindings } }).errors, [])
        assert.match(parsed.descriptor.template.content, /:loading="loading"/)
        assert.match(parsed.descriptor.template.content, /:disabled="loading"/)
    })
    await check('正常返回原页，参数保留，成功后消费返回地址', async () => {
        const h = hookHarness({ url: detail, param: { id: 3, keyword: '会员 A' } })
        assert.equal(await h.handleLoginBack(), true)
        assert.equal(h.navigations[0].url, detail + '?id=3&keyword=%E4%BC%9A%E5%91%98%20A')
        assert.equal(h.navigations[0].mode, 'redirectTo'); assert.equal(h.storage.has('loginBack'), false)
    })
    await check('Tabbar 返回使用 switchTab，已有 URL 查询参数不重复问号', async () => {
        const h = hookHarness({ url: home + '?from=card', param: { id: 1 } })
        await h.handleLoginBack(); assert.equal(h.navigations[0].url, home); assert.equal(h.navigations[0].mode, 'switchTab')
        const q = hookHarness({ url: detail + '?id=1', param: { from: 'card' } })
        await q.handleLoginBack(); assert.equal(q.navigations[0].url, detail + '?id=1&from=card')
    })
    await check('缺失、旧页面、自身、外链返回地址都回工作台', async () => {
        for (const value of [undefined, '', {}, { url: login }, { url: 'https://example.com' }, { url: '/missing' }]) {
            const h = hookHarness(value); assert.equal(await h.handleLoginBack(), true); assert.equal(h.navigations[0].url, home)
        }
    })
    await check('返回原页失败可降级工作台，再失败可重启工作台', async () => {
        const h = hookHarness({ url: detail }, [false, false, true])
        assert.equal(await h.handleLoginBack(), true); assert.deepEqual(h.navigations.map(n => n.mode), ['redirectTo', 'switchTab', 'reLaunch'])
    })
    await check('全部跳转失败保留可重试入口，不无限锁定；无回调也能结束', async () => {
        const h = hookHarness({ url: detail }, ['throw', false, false])
        assert.equal(await h.handleLoginBack(), false); assert.equal(h.notices.length, 1); assert.equal(h.storage.has('loginBack'), true)
        const silent = hookHarness({ url: home }, [null, false]); const pending = silent.handleLoginBack()
        for (const timer of [...silent.timers.values()]) timer()
        assert.equal(await pending, false)
    })
    await check('返回地址先同步保存再离开登录前页面', () => {
        const h = hookHarness(null); h.setLoginBack({ url: detail, param: { id: 8 } })
        assert.equal(h.storage.get('loginBack').param.id, 8); assert.equal(h.navigations[0].url, login)
    })
    await check('密码登录有加载与跳转，重复点击只发一次', async () => {
        const response = deferred(); let calls = 0
        const h = pageHarness({ usernameLogin: () => { calls++; return response.promise } })
        const pending = h.v.handleLogin(); await flush(); await h.v.handleLogin()
        assert.equal(calls, 1); assert.equal(h.v.loading.value, true)
        response.resolve({ data: { token: 'local-mock-token', site_id: 1 } }); await pending
        assert.equal(h.events.back, 1); assert.equal(h.v.loading.value, false); assert.equal(h.v.authenticated.value, true)
    })
    await check('表单校验拒绝与组件尚未就绪都有明确提示', async () => {
        const h = pageHarness(); h.v.formRef.value.validate = async () => { throw [{ message: '请填写密码' }] }
        await h.v.handleLogin(); assert.equal(h.v.loginError.value, '请填写密码'); assert.equal(h.v.loading.value, false)
        h.v.formRef.value = null; await h.v.handleLogin(); assert.match(h.v.loginError.value, /尚未就绪/); assert.equal(h.events.requests.length, 0)
    })
    await check('配置失败不可绕过验证码，再点可重试获取', async () => {
        let count = 0
        const h = pageHarness({ getLoginConfig: async () => { if (++count === 1) throw {}; return { data: { is_site_captcha: 1 } } } })
        await h.v.handleLogin(); assert.equal(h.events.requests.length, 0); assert.ok(h.v.loginError.value)
        await h.v.handleLogin(); assert.equal(h.events.verified, 1); assert.equal(h.events.requests.length, 0)
        await h.v.success({ captchaVerification: 'test-only' }); assert.equal(h.events.requests.length, 1); assert.equal(h.events.back, 1)
    })
    await check('配置缺少验证码开关不能被当成关闭', async () => {
        const h = pageHarness({ getLoginConfig: async () => ({ data: { is_site_captcha: null } }) })
        await h.v.handleLogin(); assert.equal(h.events.requests.length, 0); assert.match(h.v.loginError.value, /配置不完整/)
    })
    await check('登录失败和缺少 token 不跳转、不留下永久 loading', async () => {
        for (const response of [async () => { throw {} }, async () => ({ data: {} })]) {
            const h = pageHarness({ usernameLogin: response }); await h.v.handleLogin()
            assert.equal(h.events.back, 0); assert.equal(h.v.loading.value, false); assert.ok(h.v.loginError.value)
        }
    })
    await check('已登录但跳转失败，重试只打开页面，不再提交登录', async () => {
        let opened = false; const h = pageHarness({}, () => opened)
        await h.v.handleLogin(); assert.match(h.v.loginError.value, /已登录/); assert.equal(h.v.loading.value, false)
        opened = true; await h.v.handleLogin(); assert.equal(h.events.requests.length, 1); assert.equal(h.events.back, 2)
    })
    await check('短信登录使用短信接口，保持既有认证方式', async () => {
        let mobileCalls = 0
        const h = pageHarness({ mobileLogin: async () => { mobileCalls++; return { data: { token: 'local-mock-token' } } } })
        h.v.type.value = 'mobile'; await h.v.handleLogin(); assert.equal(mobileCalls, 1); assert.equal(h.events.back, 1)
    })
    console.log('\n' + count + ' PASS — 无真实网络请求或数据写入')
}
run().catch(error => { console.error(error); process.exitCode = 1 })
