'use strict'
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '..')
const ts = require(path.join(root, 'uni-app/node_modules/typescript'))
const vue = require(path.join(root, 'uni-app/node_modules/vue'))
const sfc = require(path.join(root, 'uni-app/node_modules/@vue/compiler-sfc'))
const base = path.join(root, 'uni-app/src/addon/phone_shop')
const gateSource = fs.readFileSync(path.join(base, 'hooks/useGoodsPageAccess.ts'), 'utf8')
let count = 0
const check = (name, fn) => { fn(); count++; console.log('PASS ' + name) }
const flush = async() => { for (let i = 0; i < 12; i++) await Promise.resolve() }
const deferred = () => {let resolve, reject; const promise = new Promise((yes, no) => {resolve = yes; reject = no}); return {promise, resolve, reject}}
const plain = value => JSON.parse(JSON.stringify(value))
function execute(source, mocks, globals = {}) {
    const output = ts.transpileModule(source, {compilerOptions:{module:ts.ModuleKind.CommonJS, target:ts.ScriptTarget.ES2020}})
    const exports = {}
    vm.runInNewContext(output.outputText, {exports, require: name => {
        if (name in mocks) return mocks[name]
        if (name.endsWith('.vue')) return {}
        throw new Error('Unmocked dependency: ' + name)
    }, console, ...globals})
    return exports
}
function scenario(flag = 1, token = '') {
    const state = {flag, member:vue.reactive({token}), calls:[], logins:[], hides:[], unloads:[], configError:null, sessionError:null, memberId:123, waitConfig:null, waitSession:null}
    const hooks = {onHide: fn => state.hides.push(fn), onUnload: fn => state.unloads.push(fn)}
    const request = {get: async(url) => {
        state.calls.push(url)
        if (url === 'phone_shop/goods/category/config') {
            if (state.waitConfig) return state.waitConfig.promise
            if (state.configError) throw state.configError
            return {data: {level:3, template:'style-1', page_title:'商品分类', detail_login_required:state.flag, cart:{control:1,event:'download'}}}
        }
        if (url === 'member/member') {
            if (state.waitSession) return state.waitSession.promise
            if (state.sessionError) {if (state.sessionError.code === 401) state.member.token = ''; throw state.sessionError}
            return {data:{member_id:state.memberId}}
        }
        throw new Error('Unexpected request: ' + url)
    }}
    const module = execute(gateSource, {vue, '@dcloudio/uni-app':hooks, '@/stores/member':{default:() => state.member}, '@/addon/phone_shop/hooks/usePhoneShopLoginNavigation':{
        enterPhoneShopLogin: async target => {state.logins.push(plain(target))},
        finishPhoneShopLogin: async () => false,
    }, '@/utils/request':{default:request}})
    state.make = target => module.useGoodsPageAccess(target)
    state.access = state.make(() => ({url:'/addon/phone_shop/pages/goods/category',param:{category_id:'60'}}))
    return state
}

async function main() {
    for (const flag of [1, '1']) {
        const state = scenario(flag)
        check('配置未完成前不显示内容 ' + flag, () => {assert.equal(state.access.ready.value,false); assert.equal(state.access.hasContent.value,false)})
        assert.equal(await state.access.check(), false)
        check('开启开关：进入分类页即跳登录，不等待点击商品 ' + flag, () => {
            assert.deepEqual(state.logins, [{url:'/addon/phone_shop/pages/goods/category',param:{category_id:'60'}}])
            assert.equal(state.access.status.value,'login')
            assert.equal(state.access.hasContent.value,false)
            assert.deepEqual(state.calls, ['phone_shop/goods/category/config'])
        })
    }
    for (const flag of [0, '0']) {
        const state = scenario(flag)
        assert.equal(await state.access.check(), true)
        check('关闭开关游客正常浏览，不请求会员验证 ' + flag, () => {assert.equal(state.access.ready.value,true); assert.equal(state.logins.length,0); assert.equal(state.calls.length,1)})
    }
    const signed = scenario(1,'valid-token')
    assert.equal(await signed.access.check(),true)
    check('已有有效登录不强迫重新登录', () => {assert.equal(signed.access.ready.value,true); assert.ok(signed.calls.includes('member/member')); assert.equal(signed.logins.length,0)})
    signed.access.hasContent.value = true
    const sameContent = signed.access.hasContent
    signed.hides.forEach(fn => fn())
    check('隐藏页面时暂不销毁已通过的内容，返回可保留滚动', () => {assert.equal(sameContent.value,true); assert.equal(signed.access.ready.value,false)})
    await signed.access.check()
    check('返回重新获取配置和校验会话', () => {assert.equal(signed.calls.filter(x => x === 'phone_shop/goods/category/config').length,2); assert.equal(signed.access.ready.value,true)})

    const stale = scenario(1,'expired-token'); stale.sessionError = {code:401}
    await stale.access.check()
    check('过期 token 不能冒充登录，401 返回原页面登录', () => {assert.equal(stale.access.ready.value,false); assert.equal(stale.logins.length,1); assert.equal(stale.access.status.value,'login')})
    const emptyMember = scenario(1,'bad-token'); emptyMember.memberId = 0
    await emptyMember.access.check()
    check('没有有效会员身份不能放行', () => assert.equal(emptyMember.logins.length,1))
    const failed = scenario(1,'valid-token'); failed.sessionError = {errMsg:'network failed'}
    await failed.access.check()
    check('校验断网显示重试，不误判游客或放行', () => {assert.equal(failed.access.status.value,'error'); assert.equal(failed.logins.length,0); assert.match(failed.access.message.value,/验证登录状态/)})
    failed.sessionError = null; assert.equal(await failed.access.check(),true)
    check('网络恢复可原地重试成功', () => assert.equal(failed.access.ready.value,true))
    const invalid = scenario(undefined)
    invalid.flag = undefined; await invalid.access.check()
    check('配置不完整不默认为关闭', () => {assert.equal(invalid.access.status.value,'error'); assert.equal(invalid.access.hasContent.value,false)})
    const configFailed = scenario(); configFailed.configError = {code:0}; await configFailed.access.check()
    check('配置获取失败不展示商品，可重试', () => {assert.equal(configFailed.access.status.value,'error'); assert.equal(configFailed.access.ready.value,false)})

    const changed = scenario(0)
    await changed.access.check(); changed.flag = 1
    await changed.access.check()
    check('已打开页面从关闭变为开启后重新进入立即拦截', () => {assert.equal(changed.access.status.value,'login'); assert.equal(changed.access.hasContent.value,false)})
    changed.member.token = 'login-success'; await changed.access.check()
    check('完成登录后返回原入口可以继续', () => assert.equal(changed.access.ready.value,true))

    const parallel = scenario(); parallel.waitConfig = deferred()
    const one = parallel.access.check(), two = parallel.access.check()
    // 门禁先异步完成插件登录回跳处理，然后才请求配置。
    await flush()
    check('并发入口检查只发送一份请求', () => {assert.equal(one,two); assert.equal(parallel.calls.length,1)})
    parallel.waitConfig.resolve({data:{detail_login_required:1}}); await one
    check('并发检查只触发一次登录跳转', () => assert.equal(parallel.logins.length,1))
    const gone = scenario(); gone.waitConfig = deferred()
    const pending = gone.access.check(); gone.hides.forEach(fn => fn())
    gone.waitConfig.resolve({data:{detail_login_required:1}}); await pending
    check('离开页面后迟到请求不再跳登录', () => {assert.equal(gone.logins.length,0); assert.equal(gone.access.hasContent.value,false)})
    const returned = scenario()
    const previousConfig = deferred(), latestConfig = deferred()
    returned.waitConfig = previousConfig
    const previousCheck = returned.access.check()
    returned.hides.forEach(fn => fn())
    returned.waitConfig = latestConfig
    const latestCheck = returned.access.check()
    previousConfig.resolve({data:{detail_login_required:1}}); await previousCheck
    latestConfig.resolve({data:{detail_login_required:0}}); await latestCheck
    check('快速离开再返回只采用新请求，不被旧请求卡住', () => {assert.equal(returned.access.ready.value,true);assert.equal(returned.logins.length,0)})
    const race = scenario(1,'first'); race.waitSession = deferred()
    const pendingRace = race.access.check(); await flush(); race.member.token = 'second'
    race.waitSession.resolve({data:{member_id:1}}); await pendingRace
    check('验证中途切换身份不能沿用旧会话结果', () => {assert.equal(race.access.ready.value,false); assert.equal(race.access.status.value,'error')})

    // 执行分类页真实脚本，覆盖用户提供的首页 -> 分类 category_id=60 路径。
    const categoryState = scenario(1)
    const loads = [], shows = [], titles = []
    let categoryAccess
    const categoryCode = sfc.parse(fs.readFileSync(path.join(base,'pages/goods/category.vue'),'utf8')).descriptor.scriptSetup.content
    const category = execute(categoryCode + '\nexport {loadCategoryPage, config, entryParams};', {
        vue, '@dcloudio/uni-app':{onLoad:fn => loads.push(fn),onShow:fn => shows.push(fn)},
        '@/addon/phone_shop/hooks/useGoodsPageAccess':{useGoodsPageAccess: target => (categoryAccess = categoryState.make(target))},
        '@/utils/common':{handleOnloadParams: x => x}, '@/utils/topTabbar':{topTabar:() => ({setTopTabbarParam:x => x})}
    }, {uni:{setNavigationBarTitle: x => titles.push(x)}})
    loads[0]({category_id:'60',mid:'123'}); shows[0](); await category.loadCategoryPage()
    check('分类页真实生命周期保留 category_id=60 和分享参数', () => {
        assert.deepEqual(categoryState.logins[0], {url:'/addon/phone_shop/pages/goods/category',param:{category_id:'60',mid:'123'}})
        assert.equal(categoryAccess.hasContent.value,false)
        assert.equal(Object.keys(category.config.value).length,0)
    })
    categoryState.member.token = 'good'; await category.loadCategoryPage()
    const configObject = category.config.value
    await category.loadCategoryPage()
    check('分类页通过后应用真实配置，刷新保留同一响应式对象', () => {assert.equal(category.config.value,configObject); assert.equal(category.config.value.detail_login_required,1); assert.equal(categoryAccess.ready.value,true)})

    // 列表数据入口必须在 access.ensure 通过之后才能请求商品。
    const listSource = sfc.parse(fs.readFileSync(path.join(base,'pages/goods/list.vue'),'utf8')).descriptor.scriptSetup.content
    const listFn = listSource.slice(listSource.indexOf('const getAllAppListFn ='),listSource.indexOf('onPageScroll((e)=>'))
    let listAllowed = false, goodsRequests = 0, endErrors = 0
    const filters = Object.fromEntries(['category_ids','memory_group','condition_grade','device_color','battery_range','warranty_range','label_ids','service_ids','brand_ids'].map(key => [key,[]]))
    const list = execute("import {access,filters,loading,goods_name,coupon_id,searchType,price,sale_num,goodsList,getGoodsPages,entryParams} from 'state';\n" + listFn + '\nexport {getAllAppListFn};', {
        state:{access:{ensure:async() => listAllowed},filters,entryParams:vue.ref({}),loading:vue.ref(false),goods_name:vue.ref(''),coupon_id:vue.ref(''),searchType:vue.ref('all'),price:vue.ref(''),sale_num:vue.ref(''),goodsList:vue.ref([]),getGoodsPages:async() => {goodsRequests++;return {data:{data:[]}}}}
    })
    await list.getAllAppListFn({num:1,size:10,endErr:() => endErrors++,endSuccess:() => {}})
    check('未通过门禁的列表不请求商品接口', () => {assert.equal(goodsRequests,0);assert.equal(endErrors,1)})
    listAllowed = true; await list.getAllAppListFn({num:1,size:10,endErr:() => {},endSuccess:() => {}}); await flush()
    check('列表通过门禁后照常请求数据', () => assert.equal(goodsRequests,1))

    // 商品详情真实 hook：直接访问/分享链接也经过同一检查，而非依赖上一个页面。
    for (const allowed of [false,true]) {
        const show = [], load = [], calls = []
        const store = {mode:'',topFixedStatus:'',init:() => {}}
        const detail = execute(fs.readFileSync(path.join(base,'hooks/useDiyGoodsDetail.ts'),'utf8'), {
            vue,'@dcloudio/uni-app':{onShow:fn => show.push(fn),onLoad:fn => load.push(fn),onHide:() => {},onUnload:() => {},onPageScroll:() => {}},
            '@/utils/common':{deepClone:plain,goback:() => {},handleOnloadParams:x => x,img:x => x,getToken:() => ''},
            '@/app/stores/diy':{default:() => store},'@/app/api/diy':{getDiyInfo:async() => ({data:{}})},
            '@/addon/phone_shop/api/goods':{getGoodsDetail:async() => {calls.push('goods');return {data:{}}},browse:async() => {}},
            '@/addon/phone_shop/stores/goodsDetail':{default:() => ({setGoodsDetail:() => {},removeGoodsDetail:() => {}})},
            '@/addon/phone_shop/utils/json':{parseJsonValue:JSON.parse},
            '@/addon/phone_shop/hooks/useGoodsPageAccess':{useGoodsPageAccess:target => ({check:async() => {calls.push(plain(target()));return allowed},ready:vue.ref(allowed),hasContent:vue.ref(allowed),status:vue.ref(allowed?'ready':'login'),message:vue.ref('')})}
        }, {uni:{getStorageSync:() => '',setNavigationBarTitle:() => {}},getCurrentPages:() => [{route:'addon/phone_shop/pages/goods/detail'}]}).useDiyGoodsDetail()
        detail.onLoad(); detail.onShow(); load[0]({sku_id:'789',type:'nd',mid:'123'}); show[0](); await flush()
        check('详情真实入口门禁 allowed=' + allowed, () => {
            assert.deepEqual(calls[0],{url:'/addon/phone_shop/pages/goods/detail',param:{sku_id:'789',type:'nd',mid:'123'}})
            assert.equal(calls.includes('goods'),allowed)
            assert.equal(detail.canDisplay(),allowed)
        })
    }

    const files = ['hooks/useGoodsPageAccess.ts','components/PhoneGoodsAccessState.vue','pages/goods/category.vue','pages/goods/list.vue','pages/goods/detail.vue','hooks/useDiyGoodsDetail.ts']
    for (const file of files) {
        const source = fs.readFileSync(path.join(base,file),'utf8')
        check('发布副本一致（仅规范换行符） ' + file, () => assert.equal(source.replace(/\r\n/g, '\n'),fs.readFileSync(path.join(root,'niucloud/addon/phone_shop/uni-app',file),'utf8').replace(/\r\n/g, '\n')))
        if (file.endsWith('.vue')) check('SFC 编译 ' + file, () => {
            const {descriptor,errors} = sfc.parse(source,{filename:file}); assert.deepEqual(errors,[])
            const script = sfc.compileScript(descriptor,{id:'access-gate'})
            assert.deepEqual(sfc.compileTemplate({source:descriptor.template.content,filename:file,id:'access-gate',compilerOptions:{bindingMetadata:script.bindings}}).errors,[])
        })
    }
    check('管理员说明明确包含页面入口范围', () => assert.ok(fs.readFileSync(path.join(root,'admin/src/addon/phone_shop/views/goods/category_config.vue'),'utf8').includes('进入商品分类、商品列表和商品详情前必须登录')))
    console.log(`全部通过：${count} 项。无数据库写入、真实登录或订单操作。`)
}
main().catch(error => {console.error(error);process.exitCode=1})
