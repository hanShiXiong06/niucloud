#!/usr/bin/env node
// 真实移动组件与状态逻辑的隔离回归，不加载 .env，不连接真实接口或资金服务。
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), app = path.join(root, 'site-uniapp'), source = path.join(app, 'src/addon/phone_shop')
const ts = require(path.join(app, 'node_modules/typescript')), vue = require(path.join(app, 'node_modules/vue'))
const { parse, compileScript, compileTemplate } = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
const storage = new Map(), notices = [], emitted = []
const uni = { getStorageSync: key => storage.get(key), setStorageSync: (key, value) => storage.set(key, value), removeStorageSync: key => storage.delete(key), showToast: data => notices.push(data.title), navigateTo() {}, scanCode() {}, showModal() {} }
function evaluate(code, resolver = () => ({})) {
    const exports = {}, module = { exports }
    const js = ts.transpileModule(code, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020, esModuleInterop: true } }).outputText
    vm.runInNewContext(js, { exports, module, require: resolver, uni, console, setTimeout, clearTimeout, Map, Set, Date })
    return module.exports
}
const credit = evaluate(fs.readFileSync(path.join(source, 'utils/orderCredit.ts'), 'utf8'))
const workflow = evaluate(fs.readFileSync(path.join(source, 'utils/orderWorkflow.ts'), 'utf8'), id => id === './orderCredit' ? credit : {})
let checks = 0
function check(actual, expected, label) { assert.equal(actual, expected, label); checks++ }
const keys = order => workflow.getOrderActions({ order_id: 10, order_goods: [], ...order }).map(item => item.key)
function has(order, key) { return keys(order).includes(key) }
const line = { order_goods_id: 31, goods_name: '苹果 iPhone Air', sku_name: '256G 黑色', goods_money: 6000, discount_money: 200, price: 6000, num: 1, status: 1, is_enable_refund: 1, delivery_status: 'wait_delivery', device_identity: { imei: '353938205192374' } }
const base = { order_id: 10, order_no: 'SHOPTEST', order_money: 5800, payment_mode: 'online', status: 2, delivery_type: 'express', order_goods: [line] }
const tick = async () => { await Promise.resolve(); await Promise.resolve(); await vue.nextTick(); await new Promise(resolve => setTimeout(resolve, 0)) }
function mountPanel(propsInput, overrides = {}, componentFile = 'components/OrderActionPanel.vue') {
    const calls = []
    const api = {}
    for (const name of ['closeOrder', 'confirmOrderDeviceReceived', 'deliverOrder', 'finishOrder', 'processOrderAction', 'setOrderRemark']) api[name] = async (...args) => { calls.push([name, ...args]); return { data: true } }
    api.getDeliveryCompanies = async () => ({ data: [{ company_id: 7, company_name: '顺丰' }] })
    api.getOrderDeliveryTypes = async () => ({ data: { express: '物流配送', none_express: '无需物流' } })
    api.getOrderRefundMoney = async ids => { calls.push(['getOrderRefundMoney', ids]); return { data: { refund_money: 5600 } } }
    api.getOfflineCapitalAccounts = async () => ({ data: [{ id: 7, name: '原到账账户', is_default: 1 }] })
    Object.assign(api, overrides)
    const filename = path.join(source, componentFile), { descriptor } = parse(fs.readFileSync(filename, 'utf8'), { filename })
    const script = compileScript(descriptor, { id: 'panel' })
    const component = evaluate(script.content, id => id === 'vue' ? vue : id.endsWith('/api/order') ? api : id.endsWith('/utils/orderWorkflow') ? workflow : id.endsWith('/utils/orderCredit') ? credit : { default: {} }).default
    const props = vue.reactive({ show: true, order: structuredClone(base), action: 'delivery', ...propsInput })
    const state = component.setup(props, { expose() {}, emit: (...args) => emitted.push(args) })
    return { state, props, calls, api }
}
async function main() {
    check(workflow.orderQuery('', '', ' 手机 ').offline_keyword, '手机', '默认全渠道')
    check(Object.hasOwn(workflow.orderQuery('', '', ''), 'order_channel'), false, '默认不限制线下')
    check(workflow.orderQuery('online', '3', '').order_channel, 'online', '线上过滤')
    check(workflow.orderQuery('offline', '1', '').status, 1, '状态过滤')
    check(workflow.orderStatusLabel({ status: 3, status_name: { name: '待收货' } }), '待收货', '状态名对象不是object Object')
    check(workflow.orderStatusLabel({ status: 1, payment_mode: 'offline_cash', offline_record: { status: 'pending' } }), '收款入账待核对', '半完成收款不再待联系')
    check(workflow.goodsIdentity(line), '353938205192374', '原单串号')
    check(workflow.goodsIdentity({ extend: '{"imei":"123456"}' }), '123456', 'JSON快照')
    check(has({ status: 1, payment_mode: 'online' }, 'close'), true, '线上未付可关闭')
    check(has({ status: 1, payment_mode: 'offline_pending' }, 'close'), false, '线下不混用普通关闭')
    check(has({ status: 1, payment_mode: 'offline_pending' }, 'confirm_credit'), true, '线下挂账')
    check(has({ status: 1, payment_mode: 'offline_cash' }, 'recover_paid'), true, '收款入账恢复')
    check(has({ status: 1, payment_mode: 'offline_cash' }, 'confirm_credit'), false, '恢复不能改挂账')
    check(has({ status: 2 }, 'delivery'), true, '待发货')
    check(has({ status: 3 }, 'finish'), true, '待收货可完成')
    const onAccount = { status: 3, payment_mode: 'offline_credit', delivery_type: 'store', settle_status: 'settled' }
    check(has(onAccount, 'finish'), false, '挂账不信任过期本地settle_status')
    check(has({ ...onAccount, finance_snapshot: { available: true, matched: true, can_complete: false, status: 'pending' } }, 'finish'), false, '待财务收款不可完成')
    check(has({ ...onAccount, finance_snapshot: { available: true, matched: true, can_complete: false, status: 'partial' } }, 'finish'), false, '部分收款不可完成')
    check(has({ ...onAccount, finance_snapshot: { available: false, matched: true, can_complete: true } }, 'finish'), false, '服务失败不能用旧完成标记放行')
    check(has({ ...onAccount, finance_snapshot: { available: true, matched: false, can_complete: true } }, 'finish'), false, '未匹配原单不可完成')
    check(has({ ...onAccount, finance_snapshot: { available: true, matched: true, can_complete: true, status: 'settled' } }, 'finish'), true, 'ERP权威结清才出现完成')
    check(has({ status: 2, payment_mode: 'offline_credit' }, 'delivery'), true, '挂账未收齐仍可真实交付')
    check(workflow.orderStatusLabel({ ...onAccount, finance_snapshot: { status: 'partial' } }), '已交付 · 部分收款', '交付与财务状态分开')
    check(credit.creditMoney(null), '—', '未知金额不伪造为零')
    check(credit.creditMethods({ finance_snapshot: { payment_methods: [{ key: 'bank', name: '银行卡' }] } }), '银行卡', '实际收款方式来自ERP')
    check(credit.creditAccounts({ finance_snapshot: { capital_accounts: [{ id: 8, name: '公司对公账户' }] } }), '公司对公账户', '实际收款账户来自ERP')
    check(has({ status: 2, relate_source: 'hsx_erp' }, 'refund'), false, 'ERP不能误走原路退')
    check(has({ status: 2, payment_mode: 'offline_credit' }, 'return'), true, '线下退回')
    check(has({ status: 5, payment_mode: 'online', order_goods: [line] }, 'refund'), true, '线上退款指引')
    check(has({ status: -1, order_goods: [{ ...line, can_confirm_received: true }] }, 'received'), true, '退款后收回')
    check(workflow.formatOrderTime('2026-10-30 12:30:00'), '2026-10-30 12:30:00', '格式化时间兼容')
    {
        const { state, calls } = mountPanel({ action: 'close', order: { ...base, status: 1 } }); await tick()
        await state.submit(); check(calls.length, 0, '关闭前确认')
        state.confirmed.value = true; await state.submit(); check(calls[0][0], 'closeOrder', '沿用支付关闭校验API')
    }
    {
        const { state, calls } = mountPanel({ action: 'finish', order: { ...base, status: 3, payment_mode: 'offline_credit', finance_snapshot: { available: true, matched: true, can_complete: false } } }); await tick()
        state.confirmed.value = true; await state.submit(); check(calls.length, 0, '直接打开面板也不能完成未结清挂账')
    }
    {
        const { state, calls } = mountPanel({}); await tick()
        state.confirmed.value = true; state.companyId.value = 7; state.expressNumber.value = 'SF123456789'
        await state.submit(); const payload = calls.find(item => item[0] === 'deliverOrder')[1]
        check(payload.delivery_way, 'manual_write', '移动只手工登记真实物流')
        check(payload.express_company_id, 7, '物流公司')
        check(payload.order_goods_ids[0], 31, '设备明细')
    }
    {
        const { state, calls } = mountPanel({ order: { ...base, delivery_type: 'store', payment_mode: 'offline_cash' } }); await tick(); state.confirmed.value = true
        await state.submit(); check(calls[0][0], 'processOrderAction', '线下到店交付')
        check(calls[0][1].action, 'confirm_delivery', '线下整单交付API')
    }
    {
        const { state, calls } = mountPanel({ order: { ...base, delivery_type: 'store' } }); await tick(); state.confirmed.value = true
        await state.submit(); check(calls[0][0], 'deliverOrder', '线上到店不用线下付款接口')
        check(calls[0][1].delivery_type, 'store', '线上到店真实交付')
        check(calls.some(item => item[0] === 'finishOrder'), false, '交付不自动误完成')
    }
    {
        const plan = { data: { preview_token: 'TOKEN', items: [{ ...line, can_return: true, requires_location: false, imei: '353938205192374', refund_amount: 5800 }, { ...line, order_goods_id: 32, can_return: true, requires_location: true }] } }
        const recorded = []
        const { state } = mountPanel({ action: 'return', order: { ...base, payment_mode: 'offline_credit' } }, { processOrderAction: async data => { recorded.push(['processOrderAction', data]); return data.action === 'return_preview' ? plan : { data: { message: '已生成待退款' } } } }); await tick()
        check(state.selectedIds.value.length, 1, '缺原仓库不擅自退回')
        state.confirmed.value = true; state.remark.value = '未售出退回'; await state.submit()
        const data = recorded.find(item => item[1].action === 'return_received')[1]
        check(data.preview_token, 'TOKEN', '使用原账核对token')
        check(data.received, true, '确认实际收回')
        check(data.order_goods_ids.length, 1, '仅提交选中合法设备')
    }
    {
        const { state, calls } = mountPanel({ action: 'refund' }); await tick()
        check(state.refundQuote.value, 5600, '权威可退金额非本地相加')
        await state.submit(); check(calls.some(item => item[0] !== 'getOrderRefundMoney'), false, '退款指引不调用资金写接口')
    }
    {
        const { state, props, calls } = mountPanel({ action: 'finish', order: { ...base, order_id: 987, status: 3 } }, { finishOrder: async () => { throw new Error('timeout') } }); await tick(); state.confirmed.value = true; await state.submit()
        check(state.uncertain.value, true, '超时结果未知')
        props.show = false; await tick(); props.show = true; await tick()
        check(state.uncertain.value, true, '关闭重开不清未知标记')
        check(workflow.operationUncertain(987, 'finish'), true, '按订单动作持久标记')
        check(workflow.operationUncertain(987, 'close'), false, '动作不串')
        await state.submit(); check(calls.length, 0, '未知时只核对不重写')
    }
    {
        workflow.markOperationUncertain(10, 'confirm_paid')
        const { state, calls } = mountPanel({ action: 'confirm_paid', operationKey: 'recover_paid', order: { ...base, status: 1, payment_mode: 'offline_cash' } }, {}, 'components/OfflineOrderProcessPopup.vue')
        await tick()
        check(state.isRecovery.value, true, '新读取cash状态才能恢复入账')
        check(state.uncertain.value, false, '恢复动作不清除也不复用原收款未知标记')
        check(workflow.operationUncertain(10, 'confirm_paid'), true, '原收款未知标记仍保留')
        check(state.form.capital_account_id, 0, '恢复不自动猜原到账账户')
        await state.submit(); check(calls.length, 0, '未核对原到账不可恢复')
        state.recoveryChecked.value = true; state.form.capital_account_id = 7
        await state.submit()
        check(calls[0][1].action, 'confirm_paid', '恢复复用原后端支付校验')
        check(calls[0][1].deal_total, null, '恢复入账不回写价格')
        check(calls[0][1].capital_account_id, 7, '使用手选原到账账户')
    }
    {
        const { state } = mountPanel({ action: 'confirm_credit', order: { ...base, order_id: 993, status: 1, payment_mode: 'offline_pending' } }, { processOrderAction: async () => { throw { code: 0, msg: '后段写入失败' } } }, 'components/OfflineOrderProcessPopup.vue')
        await tick(); await state.submit()
        check(state.uncertain.value, true, '业务code0也不能武断认为未写入')
        check(workflow.operationUncertain(993, 'confirm_credit'), true, '部分提交风险持久标记')
    }
    for (const file of ['components/OfflineOrderCard.vue', 'components/OrderActionPanel.vue', 'components/OfflineOrderProcessPopup.vue', 'components/PhoneShopListHeader.vue', 'pages/order/list.vue', 'pages/order/detail.vue']) {
        const filename = path.join(source, file), { descriptor, errors } = parse(fs.readFileSync(filename, 'utf8'), { filename })
        check(errors.length, 0, file+' parse')
        const script = compileScript(descriptor, { id: file }), result = compileTemplate({ id: file, filename, source: descriptor.template.content, compilerOptions: { bindingMetadata: script.bindings } })
        check(result.errors.length, 0, file+' template')
    }
    console.log(`[通过] ${checks} 项移动统一订单隔离回归；未调用真实订单、资金、物流或打印接口。`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
