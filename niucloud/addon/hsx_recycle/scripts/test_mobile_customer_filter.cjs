#!/usr/bin/env node
// 真实 SFC setup 隔离回归：只使用内存 API / 分页 / 导航 / 定时器，不访问远程服务。
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const app = path.join(root, 'site-uniapp')
const ts = require(path.join(app, 'node_modules/typescript'))
const compiler = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
const vue = require(path.join(app, 'node_modules/vue'))
const addon = path.join(app, 'src/addon/hsx_recycle')
let passed = 0
const plain = value => value === undefined ? undefined : JSON.parse(JSON.stringify(value))
function check(actual, expected, label) { assert.deepEqual(plain(actual), plain(expected), label); passed++ }
function ok(actual, label) { assert.ok(actual, label); passed++ }
const member = (id = 7, name = '测试客户') => ({ member_id: id, nickname: name, mobile: '13800138000', member_no: `M${id}`, username: '', headimg: '' })
function deferred() { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { promise, resolve, reject } }
function environment(windowHeight = 844, navbarHeightPx = 88) {
    let clock = 0, timerId = 0
    const timers = new Map(), lifecycle = { load: [], show: [], beforeUnmount: [] }, calls = []
    const fixture = {
        members: async () => ({ data: { data: [member()], total: 1, last_page: 1 } }),
        orders: async () => ({ data: { data: [], status_counts: { all: 3, 1: 2 } } }),
    }
    const env = { timers, lifecycle, calls, fixture, navbarHeightPx,
        setTimeout(callback, delay = 0) { const id = ++timerId; timers.set(id, { callback, due: clock + delay }); return id },
        clearTimeout(id) { timers.delete(id) },
        advance(ms) { clock += ms; for (const [id, timer] of [...timers]) if (timer.due <= clock) { timers.delete(id); timer.callback() } },
        uni: {
            hideKeyboard() { calls.push(['hideKeyboard']) }, showToast(input) { calls.push(['toast', input]) }, showModal(input) { calls.push(['modal', input]) },
            getWindowInfo() { return { windowHeight, windowWidth: 390 } },
            getSystemInfoSync() { return { windowHeight, windowWidth: 390 } },
        },
    }
    return env
}
function load(relative, env, inputProps = {}) {
    const filename = path.join(addon, relative), source = fs.readFileSync(filename, 'utf8')
    const parsed = compiler.parse(source, { filename })
    check(parsed.errors, [], `${relative} parse`)
    const script = compiler.compileScript(parsed.descriptor, { id: relative })
    const template = compiler.compileTemplate({ id: relative, filename, source: parsed.descriptor.template.content, compilerOptions: { bindingMetadata: script.bindings } })
    check(template.errors, [], `${relative} template`)
    const code = ts.transpileModule(script.content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 }, reportDiagnostics: true })
    check((code.diagnostics || []).filter(item => item.category === ts.DiagnosticCategory.Error).length, 0, `${relative} TS transpile`)
    const noOp = () => {}
    const api = {
        async searchMemberList(params) { env.calls.push(['members', plain(params)]); return env.fixture.members(params) },
        async getOrderList(params) { env.calls.push(['orders', plain(params)]); return env.fixture.orders(params) },
        async getOrderStatus() { return { data: [] } }, async getOrderBusinessStageOptions() { return { data: [] } },
        async pushOrderNotify() { throw Error('Not allowed in read-only fixture') }, async updateOrder() { throw Error('Not allowed in read-only fixture') },
    }
    const dependencies = {
        vue: { ...vue, onBeforeUnmount(callback) { env.lifecycle.beforeUnmount.push(callback) } },
        '@dcloudio/uni-app': { onLoad(callback) { env.lifecycle.load.push(callback) }, onShow(callback) { env.lifecycle.show.push(callback) } },
        '@/addon/hsx_recycle/api/order': api,
        '@/utils/common': { redirect(input) { env.calls.push(['redirect', plain(input)]) } },
        '@/addon/hsx_recycle/utils/clipboard': { copyOrderNo: noOp, copyIMEI: noOp },
        '@/addon/hsx_recycle/utils/device': { getDeviceListPriceMeta: () => ({}), isConsignedDevice: () => false, shouldShowConfirmStatus: () => true },
        '@/addon/hsx_recycle/utils/helper': { makePhoneCall: noOp },
        '@/addon/hsx_recycle/utils/navbar': { getRecycleNavbarMetrics: () => ({ statusTopPx: 24, navbarHeightPx: env.navbarHeightPx }) },
        '@/addon/hsx_recycle/hooks/useRecycleListHeader': { useRecycleListHeader(...args) { env.calls.push(['header', args]); return { pageHeaderStyle: vue.ref(''), pagingStyle: vue.ref({ top: '100rpx' }) } } },
        '@/addon/hsx_recycle/hooks/useRecyclePaging': { useRecyclePaging() { return { pagingRef: vue.ref(null), list: vue.ref([]), reload: () => env.calls.push(['reload']), refresh: () => env.calls.push(['refresh']), complete: rows => env.calls.push(['complete', plain(rows)]) } } },
        '@/addon/hsx_recycle/hooks/useRecycleMember': { getRecycleMemberAvatar: () => '', getRecycleMemberInitial: () => '测', getRecycleMemberMobile: () => '', getRecycleMemberName: () => '' },
    }
    const module = { exports: {} }
    vm.runInNewContext(code.outputText, { module, exports: module.exports, console, uni: env.uni, setTimeout: env.setTimeout, clearTimeout: env.clearTimeout, require(id) {
        if (Object.prototype.hasOwnProperty.call(dependencies, id)) return dependencies[id]
        if (id.endsWith('.vue')) return {}
        throw Error('Unexpected dependency: ' + id)
    } }, { filename })
    const component = module.exports.default, values = {}
    for (const [key, definition] of Object.entries(component.props || {})) {
        if (Object.prototype.hasOwnProperty.call(inputProps, key)) continue
        if (definition && typeof definition === 'object' && Object.prototype.hasOwnProperty.call(definition, 'default')) values[key] = typeof definition.default === 'function' ? definition.default() : definition.default
    }
    const props = vue.reactive({ ...values, ...inputProps }), emitted = [], scope = vue.effectScope()
    const state = scope.run(() => component.setup(props, { expose() {}, emit(name, value) {
        emitted.push([name, plain(value)])
        if (name === 'update:show') props.show = value
    } }))
    const dispose = () => { env.lifecycle.beforeUnmount.forEach(callback => callback()); scope.stop() }
    return { source, template: parsed.descriptor.template.content, props, state, emitted, dispose }
}
async function flush() { for (let index = 0; index < 5; index++) await vue.nextTick() }
const callsOf = (env, type) => env.calls.filter(call => call[0] === type)

async function main() {
    {
        const env = environment(), { state, template, source, dispose } = load('pages/order/list.vue', env)
        check(callsOf(env, 'header')[0][1], [104, 186], '移除额外80rpx顶部横条高度')
        ok(!template.includes('agent-order-bar'), '无占用顶部的下单横条')
        ok(template.includes('agent-order-fab'), '使用独立悬浮加号')
        ok(source.includes('position: fixed;') && source.includes('right: 36rpx;') && source.includes('border-radius: 50%;'), 'FAB按ERP右下角圆形样式')
        ok(!template.includes('v-model="keyword"') && !source.includes('ScanCodeInput'), '顶部不再编辑泛搜关键词/扫码输入')
        ok(template.includes(':show-member-filter="false"'), '高级筛选不重复展示客户搜索')
        state.openAgentOrder()
        check([state.agentOrderMounted.value, state.agentOrderVisible.value], [true, true], 'FAB首次挂载并打开代下单')
        state.openCustomerPicker()
        check([state.customerPickerMounted.value, state.customerPickerVisible.value], [true, true], '顶部直接打开客户选择')
        state.filterParams.value = { user_mobile: 'old', user_nickname: '旧姓名', device_imei: 'ABC12345678', order_no: 'R123', keyword: '慢泛搜', search: '慢泛搜' }
        const before = callsOf(env, 'reload').length
        state.selectCustomer(member())
        check(callsOf(env, 'reload').length, before + 1, '选中客户只刷新一次')
        check(state.filterParams.value.member_id, 7, '选中客户使用ID')
        check([state.filterParams.value.user_mobile, state.filterParams.value.user_nickname], [undefined, undefined], '新客户替换旧姓名与手机号条件')
        check(state.filterParams.value.device_imei, 'ABC12345678', '客户筛选保留设备条件')
        check(state.customerLabel.value, '测试客户', '顶部反显客户名')
        check(state.customerMobile.value, '13800138000', '顶部反显手机号')
        await state.queryList(2, 15)
        const params = callsOf(env, 'orders').at(-1)[1]
        check([params.page, params.limit, params.member_id], [2, 15, 7], '订单请求按分页与客户ID过滤')
        check([params.keyword, params.search], [undefined, undefined], '即使历史参数混入也强制剔除泛搜')
        state.switchStatus('2')
        check([state.filterParams.value.member_id, state.currentStatus.value], [7, '2'], '切换状态保留客户')
        state.toDetail({ id: 18 })
        check(callsOf(env, 'redirect').at(-1)[1].param.device_keyword, 'ABC12345678', '详情仅携带设备IMEI')
        state.filterParams.value = { member_id: 7 }
        state.toDetail({ id: 18 })
        check(callsOf(env, 'redirect').at(-1)[1].param.device_keyword, '', '客户名不传成设备关键词')
        check(state.activeFilterCount.value, 0, '顶部客户不重复计入高级筛选角标')
        state.onFilterConfirm({ member_id: '7', status: '1', order_no: 'R999' })
        check([state.filterParams.value.member_id, state.currentStatus.value, state.customerLabel.value], ['7', '1', '测试客户'], '高级筛选确认保留客户反显')
        state.clearCustomer()
        check([state.filterParams.value.member_id, state.selectedCustomer.value, state.customerLabel.value], [undefined, null, ''], '清除客户同步移除ID和显示')
        check(state.filterParams.value.order_no, 'R999', '清客户不丢高级订单号条件')
        const count = callsOf(env, 'reload').length
        state.selectCustomer({ ...member(), member_id: -1 })
        check(callsOf(env, 'reload').length, count, '非法客户ID不触发查询')
        state.selectCustomer(member())
        state.onFilterReset()
        check([state.filterParams.value, state.currentStatus.value, state.selectedCustomer.value], [{}, '', null], '高级重置同步清顶部客户与状态')
        check(state.buildRouteFilters({ member_id: '7', keyword: 'ignore', search: 'ignore' }), { member_id: '7' }, '路由接客户ID但不接泛搜')
        for (const value of ['0', '-1', '7,8', 'x7', '1.5']) check(state.buildRouteFilters({ member_id: value }).member_id, undefined, '路由拒绝非法客户ID ' + value)
        env.lifecycle.load[0]({ member_id: '7' })
        check([state.hasCustomerFilter.value, state.customerLabel.value], [true, '已选客户'], '未知显示资料的路由客户有明确筛选反显')
        dispose()
    }
    for (const oldFails of [false, true]) {
        const env = environment(), { state, dispose } = load('pages/order/list.vue', env)
        const old = deferred(), fresh = deferred()
        env.fixture.orders = params => params.member_id === 7 ? old.promise : fresh.promise
        state.selectCustomer(member(7, '客户A'))
        const requestA = state.queryList(1, 15)
        state.selectCustomer(member(8, '客户B'))
        const requestB = state.queryList(1, 15)
        fresh.resolve({ data: { data: [{ id: 202, member_id: 8 }], status_counts: { all: 2, 2: 2 } } })
        await requestB
        check(callsOf(env, 'complete').map(call => call[1]), [[{ id: 202, member_id: 8 }]], '先返回的B客户订单可显示')
        check(state.lastStatusCounts.value, { all: 2, 2: 2 }, 'B客户状态数量写入')
        if (oldFails) old.reject({ msg: 'A旧请求读取失败' })
        else old.resolve({ data: { data: [{ id: 101, member_id: 7 }], status_counts: { all: 9, 1: 9 } } })
        await requestA
        check(callsOf(env, 'complete').map(call => call[1]), [[{ id: 202, member_id: 8 }]], oldFails ? '旧客户失败不把新结果置为失败' : '旧客户后返回不覆盖新结果')
        check(state.lastStatusCounts.value, { all: 2, 2: 2 }, oldFails ? '旧客户失败不改变新状态数量' : '旧客户旧统计不覆盖新数量')
        check(state.customerLabel.value, '客户B', '界面客户标签与最新请求一致')
        dispose()
    }
    {
        const env = environment(), { state, dispose } = load('pages/order/list.vue', env)
        const old = deferred()
        env.fixture.orders = () => old.promise
        state.selectCustomer(member(7, '客户A'))
        const request = state.queryList(1, 15)
        state.selectCustomer(member(8, '客户B'))
        old.resolve({ data: { data: [{ id: 101, member_id: 7 }], status_counts: { all: 9 } } })
        await request
        check(callsOf(env, 'complete').length, 0, '只切客户尚未发新请求时，scope签名也隔离旧结果')
        check(state.lastStatusCounts.value, {}, 'scope变化不写入旧客户数量')
        env.fixture.orders = async () => { throw { msg: '当前客户查询失败' } }
        await state.queryList(1, 15)
        check(callsOf(env, 'complete').at(-1)[1], false, '当前有效请求失败仍正常显示读取错误')
        dispose()
    }
    {
        const env = environment(), { state, emitted, props, dispose } = load('pages/order/components/OrderFilterDrawer.vue', env, { visible: true, modelValue: { member_id: 7 }, showMemberFilter: false })
        state.fillForm({ member_id: 7, device_imei: 'ABC12345678' })
        state.memberKeyword.value = '隐藏的旧搜索'
        state.handleConfirm()
        check(emitted.find(event => event[0] === 'confirm')[1], { member_id: '7', device_imei: 'ABC12345678' }, '隐藏客户控件仍保留ID且不被旧搜索拦截')
        check(callsOf(env, 'members').length, 0, '只打开高级筛选不触发会员请求')
        state.handleReset()
        check(emitted.findLast(event => event[0] === 'update:modelValue')[1], {}, '高级重置发出空筛选')
        check(state.form.member_id, '', '高级重置同步内部ID')
        props.showMemberFilter = true
        state.memberKeyword.value = '必须选客户'
        state.handleConfirm()
        check(callsOf(env, 'toast').at(-1)[1].title, '请先选择会员', '原可见客户控件仍保留选择校验')
        dispose()
    }
    {
        const env = environment(), { state, props, dispose } = load('components/RecycleMemberPicker.vue', env, { show: true })
        await flush()
        check(callsOf(env, 'members').length, 1, '默认打开客户选择仍加载第一页')
        check([props.autoFocus, props.searchOnInput], [false, false], '原代下单用法默认不抢焦点/不实时请求')
        state.onPopupOpen()
        check(state.searchFocused.value, false, '默认不抢输入焦点')
        state.keyword.value = '测试'
        await flush(); env.advance(600); await flush()
        check(callsOf(env, 'members').length, 1, '默认输入不自动请求')
        state.handleSearch(); await flush()
        check(callsOf(env, 'members').at(-1)[1].keyword, '测试', '原显式搜索正常')
        check(callsOf(env, 'orders').length, 0, '客户选择不会调用订单泛搜')
        dispose()
    }
    {
        const env = environment(), { state, props, emitted, dispose } = load('components/RecycleMemberPicker.vue', env, { show: true, autoFocus: true, searchOnInput: true })
        await flush()
        state.onPopupOpen()
        check(state.searchFocused.value, true, '顶部筛选弹层打开后自动聚焦')
        state.keyword.value = '张'; await flush(); env.advance(299); await flush()
        check(callsOf(env, 'members').length, 1, '输入防抖未到300ms不请求')
        state.keyword.value = '张三'; await flush(); env.advance(299); await flush()
        check(callsOf(env, 'members').length, 1, '连续输入重新计时')
        env.advance(1); await flush()
        check(callsOf(env, 'members').at(-1)[1], { page: 1, limit: 20, keyword: '张三' }, '只向会员接口发送最后关键词')
        const old = deferred(), current = deferred()
        env.fixture.members = params => params.keyword === '旧客户' ? old.promise : current.promise
        state.keyword.value = '旧客户'; await flush(); env.advance(300); await flush()
        state.keyword.value = '新客户'; await flush(); env.advance(300); await flush()
        current.resolve({ data: { data: [member(9, '新客户')], total: 1 } }); await flush()
        check(state.members.value.map(item => item.member_id), [9], '新搜索先返回可显示')
        old.resolve({ data: { data: [member(8, '旧客户')], total: 1 } }); await flush()
        check(state.members.value.map(item => item.member_id), [9], '旧响应不能覆盖新客户结果')
        env.fixture.members = async () => ({ data: { data: [member()], total: 1 } })
        state.keyword.value = '未发出的关键词'; await flush()
        const before = callsOf(env, 'members').length
        state.handleClose(); await flush(); env.advance(600); await flush()
        check(callsOf(env, 'members').length, before, '关闭弹层取消待发请求')
        check([state.searchFocused.value, props.show], [false, false], '关闭收起焦点与弹层')
        check(emitted.some(event => event[0] === 'select'), false, '关闭不改选中客户')
        props.show = true; await flush()
        state.selectMember(member(10, '客户十')); await flush()
        check(emitted.find(event => event[0] === 'select')[1].member_id, 10, '选中客户正常回传')
        check(props.show, false, '选中后关闭')
        check(callsOf(env, 'orders').length, 0, '实时搜索全程不调用订单接口')
        dispose()
    }
    {
        const env = environment(), { state, dispose } = load('components/RecycleMemberPicker.vue', env, { show: true, searchOnInput: true })
        await flush()
        state.keyword.value = '组件销毁'; await flush()
        const before = callsOf(env, 'members').length
        dispose(); env.advance(1000); await flush()
        check(callsOf(env, 'members').length, before, '卸载取消防抖定时器')
        check(env.timers.size, 0, '卸载不遗留定时器')
    }
    for (const height of [844, 568]) {
        const env = environment(height), { state, props, template, dispose } = load('components/RecycleMemberPicker.vue', env, { show: true, autoFocus: true, searchOnInput: true })
        await flush()
        check([state.popupStyle.value, state.pickerStyle.value], [{}, {}], `${height}px未弹键盘沿用原弹层高度`)
        check(state.pickerSafeTop, env.navbarHeightPx, '小程序顶部避让包含胶囊导航完整高度')
        ok(template.includes(':safe-area-inset-bottom="keyboardHeight === 0"'), '键盘出现时不重复叠加底部安全区')
        ok(template.includes(':adjust-position="false"') && template.includes('@keyboardheightchange="onKeyboardHeightChange"'), '输入框由弹层统一避让而不自行顶起页面')
        ok(template.includes('v-if="!keyboardHeight"') && template.includes('selectedMember && !keyboardHeight'), '键盘出现时收起辅助说明和重复已选卡片')
        ok(state !== null && /min-width:44px;\s*min-height:44px/.test(fs.readFileSync(path.join(addon, 'components/RecycleMemberPicker.vue'), 'utf8')), '关闭按钮至少44px可点击')
        for (const keyboard of [280, 320, ...(height === 568 ? [400] : [])]) {
            state.onKeyboardHeightChange({ detail: { height: keyboard } })
            check(state.keyboardHeight.value, keyboard, `${height}px读取真实键盘高度`)
            check(state.popupStyle.value.marginBottom, `${keyboard}px`, `${height}px弹层移到键盘上方`)
            const expected = Math.max(0, Math.min(height * .78, height - keyboard - state.pickerSafeTop))
            check(state.pickerStyle.value.height, `${expected}px`, `${height}px键盘${keyboard}px按可用高度收缩`)
            ok(expected + keyboard <= height, `${height}px弹层与键盘总高度不超过窗口`)
            ok(height - keyboard - expected >= state.pickerSafeTop, `${height}px弹层顶端不进入胶囊导航区`)
        }
        state.onKeyboardHeightChange({ detail: { height: 0 } })
        check([state.popupStyle.value, state.pickerStyle.value], [{}, {}], `${height}px键盘收起恢复原布局`)
        state.onKeyboardHeightChange({ detail: { height: -100 } })
        check(state.keyboardHeight.value, 0, '负键盘高度不能产生反向偏移')
        state.onKeyboardHeightChange({ detail: { height: 280 } })
        state.handleClose(); await flush()
        check([state.keyboardHeight.value, state.popupStyle.value, props.show], [0, {}, false], `${height}px关闭同步清除键盘偏移`)
        props.show = true; await flush()
        check([state.keyboardHeight.value, state.pickerStyle.value], [0, {}], `${height}px重新打开不残留偏移`)
        dispose()
    }
    {
        const env = environment(568)
        delete env.uni.getWindowInfo
        const { state, dispose } = load('components/RecycleMemberPicker.vue', env, { show: true })
        await flush()
        state.onKeyboardHeightChange({ detail: { height: 280 } })
        check(state.pickerStyle.value.height, `${568 - 280 - state.pickerSafeTop}px`, '低版本SDK使用getSystemInfoSync也能计算键盘避让')
        dispose()
    }
    {
        const env = environment(568, 16), { state, dispose } = load('components/RecycleMemberPicker.vue', env, { show: true })
        await flush()
        check(state.pickerSafeTop, 24, '异常偏小的导航指标仍保留基础顶部间距')
        state.onKeyboardHeightChange({ detail: { height: 320 } })
        check(state.pickerStyle.value.height, `${568 - 320 - state.pickerSafeTop}px`, '基础间距也参与短窗口键盘避让')
        dispose()
    }
    console.log(`[通过] ${passed} 项回收列表悬浮下单/精准客户筛选真实 SFC 隔离回归；未请求生产服务。`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
