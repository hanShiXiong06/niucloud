'use strict'
// 执行插件登录导航及原框架登录 hook，模拟 Uni 页面栈，不登录真实账号。
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '..')
const deps = name => require(path.join(root, 'uni-app/node_modules', name))
const ts = deps('typescript'), vue = deps('vue'), sfc = deps('@vue/compiler-sfc')
const {initPreContext, preJs} = deps('@dcloudio/uni-cli-shared/dist/preprocess')
const read = file => fs.readFileSync(path.join(root, 'uni-app/src', file), 'utf8')
const plain = data => JSON.parse(JSON.stringify(data))
let passed = 0, failed = 0
const check = (name, fn) => {
    try { fn(); passed++; console.log('PASS ' + name) }
    catch (error) { failed++; console.error('FAIL ' + name + ': ' + error.message) }
}
function execute(source, mocks, globals = {}) {
    const exports = {}
    const code = ts.transpileModule(source.replace(/import\.meta\.env/g, '({})'), {compilerOptions:{module:ts.ModuleKind.CommonJS, target:ts.ScriptTarget.ES2020}}).outputText
    vm.runInNewContext(code, {exports, console, require: name => {
        if (name in mocks) return mocks[name]
        throw new Error('Unmocked dependency: ' + name)
    }, ...globals})
    return exports
}
function scenario(platform = 'h5', choice = false, wechat = false) {
    initPreContext(platform)
    const origin = {route:'addon/phone_shop/pages/index', scrollTop:860, filters:{keyword:'iPhone'}}
    const state = {pages:[origin], member:vue.reactive({token:''}), storage:new Map(), timers:[], historyEvents:[], requests:[], toasts:[]}
    const routeHooks = new Set()
    const router = {afterEach: fn => {routeHooks.add(fn); return () => routeHooks.delete(fn)}}
    const config = {login:{is_username:1, is_mobile:choice ? 1 : 0, is_auth_register:choice ? 1 : 0}}
    const system = {initStatus:'finish'}
    const pushPage = url => {
        const [route, query = ''] = url.replace(/^\//,'').split('?')
        return {route, options:Object.fromEntries(new URLSearchParams(query)), scrollTop:0}
    }
    const uni = {
        $u:{queryParams: param => '?' + new URLSearchParams(param).toString()},
        setStorage: ({key,data}) => state.storage.set(key, plain(data)),
        setStorageSync: (key,data) => state.storage.set(key, data),
        getStorageSync: key => state.storage.get(key),
        removeStorageSync: key => state.storage.delete(key),
        getStorage: ({key,success,fail}) => state.storage.has(key) ? success({data:state.storage.get(key)}) : fail({}),
        showToast: data => state.toasts.push(data),
        navigateTo: ({url,success}) => {state.pages.push(pushPage(url)); success?.()},
        redirectTo: ({url,success}) => {state.pages.splice(-1,1,pushPage(url)); success?.()},
        reLaunch: ({url,success}) => {state.pages = [pushPage(url)]; success?.()},
        switchTab: ({url,success}) => {state.pages = [pushPage(url)]; success?.()},
        navigateBack: ({delta = 1,success,fail} = {}) => {
            if (delta >= state.pages.length) {fail?.(); return}
            if (platform === 'h5') {
                // 本项目 uni-h5 源码：router.go 是异步的，但 navigateBack success 立即调用。
                state.historyEvents.push(() => {state.pages.splice(-delta); [...routeHooks].forEach(fn => fn())})
                success?.()
            } else {state.pages.splice(-delta); success?.()}
        }
    }
    const globals = {uni, getApp:() => ({$router:router}), getCurrentPages:() => state.pages.slice(),
        setTimeout: (fn, delay = 0) => {const item = {fn,delay};state.timers.push(item);return item},
        clearTimeout: item => {state.timers = state.timers.filter(timer => timer !== item)},
        navigator:{userAgent:wechat ? 'MicroMessenger' : 'Chrome'}}
    const stores = {'@/stores/member':{default:() => state.member}, '@/stores/config':{default:() => config}, '@/stores/system':{default:() => system}}
    const common = execute(preJs(read('utils/common.ts')), {
        ...stores, './pages':{getTabbarPages:() => []}, '@/utils/pages':{getNeedLoginPages:() => []},
        '@/app/stores/diy':{default:() => ({mode:''})}, '@/manifest.json':{default:{}}
    },globals)
    const login = execute(preJs(read('hooks/useLogin.ts')), {vue, ...stores, '@/utils/common':common, '@/app/api/auth':{}, '@/app/api/system':{}},globals).useLogin()
    const navigation = execute(preJs(read('addon/phone_shop/hooks/usePhoneShopLoginNavigation.ts')), {
        vue, ...stores, '@/utils/common':common, '@/utils/pages':{getNeedLoginPages:() => []},
        '@/hooks/useLogin':{useLogin:() => login},
    },globals)
    const gateModule = execute(read('addon/phone_shop/hooks/useGoodsPageAccess.ts'), {
        vue, ...stores, '@dcloudio/uni-app':{onHide:() => {},onUnload:() => {}}, '@/addon/phone_shop/hooks/usePhoneShopLoginNavigation':navigation,
        '@/utils/request':{default:{get:async url => {
            state.requests.push(url)
            return url === 'member/member' ? {data:{member_id:1}} : {data:{detail_login_required:1}}
        }}}
    },globals)
    const header = read('components/top-tabbar/top-tabbar.vue')
    const backCode = header.slice(header.indexOf('const goBack ='),header.indexOf('/******************************* 返回按钮-end'))
    const back = execute(backCode + '\nexport {goBack}', {}, {...globals, isBackShow:vue.ref(true), props:{customBack:null}, redirect:common.redirect}).goBack
    state.flush = async() => {
        for (let tick = 0; tick < 20; tick++) {
            while (state.historyEvents.length) state.historyEvents.shift()()
            await vue.nextTick()
            const due = state.timers.filter(item => !item.delay)
            state.timers = state.timers.filter(item => item.delay)
            due.forEach(item => item.fn())
        }
        await vue.nextTick()
    }
    state.enter = async target => {
        common.redirect(target)
        const gate = gateModule.useGoodsPageAccess(() => target)
        await gate.check(); await state.flush()
        return gate
    }
    state.finishLogin = async () => {
        const page = state.pages.at(-1)
        const task = navigation.finishPhoneShopLogin({url:'/' + page.route,param:page.options || {}})
        await state.flush(); await task; await state.flush()
    }
    state.visibleParams = () => {
        const params = {...state.pages.at(-1).options}; delete params._phone_shop_auth; return params
    }
    return Object.assign(state, {origin, common, login, uni, back, navigation})
}
async function main() {
    for (const platform of ['h5','mp-weixin','app-plus']) {
        for (const page of ['category','list']) {
            const state = scenario(platform)
            const target = {url:'/addon/phone_shop/pages/goods/' + page,param:{category_id:'60',goods_name:'苹果 黑色',mid:'123'}}
            await state.enter(target)
            check(platform + ' ' + page + ' 登录页替换待访问页，不产生重复返回记录', () => {
                assert.deepEqual(state.pages.map(x => x.route), [state.origin.route,'app/pages/auth/login'])
                const stored = state.storage.get('loginBack')
                assert.equal(typeof stored.param._phone_shop_auth, 'string')
                assert.ok(stored.param._phone_shop_auth.length > 0)
                const businessParams = {...stored.param}; delete businessParams._phone_shop_auth
                assert.deepEqual({url: stored.url, param: businessParams},target)
            })
            state.back(); await state.flush()
            check(platform + ' ' + page + ' 登录页左上角返回保留来源页面实例、筛选及滚动位置', () => {
                assert.equal(state.pages.at(-1), state.origin)
                assert.equal(state.origin.scrollTop,860)
                assert.equal(state.origin.filters.keyword,'iPhone')
            })
            await state.enter(target)
            state.member.token = 'valid'
            state.login.handleLoginBack(); await state.flush(); await state.finishLogin()
            check(platform + ' ' + page + ' 账号直接登录后返回指定分类及原页面', () => {
                assert.deepEqual(state.pages.map(x => x.route),[state.origin.route,target.url.slice(1)])
                assert.deepEqual(state.visibleParams(),target.param)
            })
        }
        for (const registration of [false,true]) {
            const state = scenario(platform,true,platform === 'h5')
            const target = {url:'/addon/phone_shop/pages/goods/list',param:{category_id:'60',goods_name:'苹果 黑色'}}
            await state.enter(target)
            state.common.redirect({url:'/app/pages/auth/login',param:{type:'mobile'}})
            if (registration) state.common.redirect({url:'/app/pages/auth/register',param:{type:'mobile'}})
            state.member.token = 'valid'
            state.login.handleLoginBack()
            await state.flush(); await state.finishLogin()
            check(platform + ' ' + (registration ? '注册' : '切换登录方式') + ' 成功清除登录中间页并保留目标参数', () => {
                assert.deepEqual(state.pages.map(x => x.route), [state.origin.route,target.url.slice(1)])
                assert.deepEqual(state.visibleParams(),target.param)
            })
            state.back(); await state.flush()
            check(platform + ' 成功后返回不再进入登录页 registration=' + registration, () => assert.equal(state.pages.at(-1),state.origin))
        }
    }
    const click = scenario()
    click.login.setLoginBack({url:'/addon/phone_shop/pages/goods/detail',param:{goods_id:123}})
    await click.flush(); click.back(); await click.flush()
    check('原有点击登录调用默认仍保留当前页，不删除来源页', () => assert.equal(click.pages.at(-1),click.origin))

    for (const platform of ['h5','mp-weixin','app-plus']) {
        const rootEntry = scenario(platform)
        const target = {url:'/addon/phone_shop/pages/goods/list',param:{category_id:'60'}}
        rootEntry.pages = [{route:target.url.slice(1),options:target.param}]
        const entering = rootEntry.navigation.enterPhoneShopLogin(target)
        await rootEntry.flush(); await entering; await rootEntry.flush()
        check(platform + ' tabbar 根页面先保留商城首页再进入登录', () => {
            assert.deepEqual(rootEntry.pages.map(page => page.route), ['addon/phone_shop/pages/index','app/pages/auth/login'])
        })
        rootEntry.back(); await rootEntry.flush()
        check(platform + ' 取消登录返回 phone_shop 首页，而不是其他插件', () => assert.equal(rootEntry.pages.at(-1).route,'addon/phone_shop/pages/index'))
    }

    const headerOnly = scenario()
    headerOnly.pages = [{route:'app/pages/auth/index'}]
    headerOnly.member.token = 'valid'
    headerOnly.storage.set('loginBack',{url:'/addon/phone_shop/pages/goods/list',param:{category_id:'60'}})
    headerOnly.login.handleLoginBack()
    await headerOnly.flush()
    check('独立打开登录页、没有历史记录仍可正常登录返回', () => assert.equal(headerOnly.pages.at(-1).route,'addon/phone_shop/pages/goods/list'))

    const {descriptor} = sfc.parse(read('addon/phone_shop/components/PhoneGoodsAccessState.vue'))
    const template = sfc.compileTemplate({id:'login-back-state',source:descriptor.template.content,filename:'PhoneGoodsAccessState.vue'})
    assert.deepEqual(template.errors,[])
    const render = execute(template.code,{vue:{...vue,resolveComponent:name => name}}).render
    const collect = (node, result = {text:'',types:[]}) => {
        if (Array.isArray(node)) node.forEach(item => collect(item,result))
        else if (node && typeof node === 'object') {result.types.push(node.type); collect(node.children,result)}
        else if (typeof node === 'string') result.text += node
        return result
    }
    for (const status of ['checking','login']) check('校验及跳登录阶段只显示 loading：' + status, () => {
        const tree = collect(render({status,message:'权限提示不应出现'},[]))
        assert.ok(tree.types.includes('u-loading-icon'))
        assert.ok(!tree.types.includes('button'))
        assert.doesNotMatch(tree.text,/权限|登录|重试|重新加载/)
    })
    check('真正请求失败保留错误原因及重新加载按钮', () => {
        const tree = collect(render({status:'error',message:'网络连接失败'},[]))
        assert.ok(tree.types.includes('button')); assert.match(tree.text,/网络连接失败/)
    })
    console.log(`返回链路回归：${passed} 项通过，${failed} 项失败。没有网络请求、账号或数据库操作。`)
    if (failed) process.exitCode = 1
}
main().catch(error => {console.error(error);process.exitCode=1})
