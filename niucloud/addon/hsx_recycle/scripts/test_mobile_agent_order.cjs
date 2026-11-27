#!/usr/bin/env node
// 隔离测试：使用真实前端工作流与 SFC 编译，不连接数据库、不调用打印或线上接口。
const fs = require('node:fs')
const path = require('node:path')
const assert = require('node:assert/strict')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../../../..')
const app = path.join(root, 'site-uniapp')
const ts = require(path.join(app, 'node_modules/typescript'))
const { parse, compileScript, compileTemplate } = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
const source = fs.readFileSync(path.join(app, 'src/addon/hsx_recycle/utils/agentOrder.ts'), 'utf8')
const exportsObject = {}
vm.runInNewContext(ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, { exports: exportsObject })
const { agentTotalError, agentOrderError, deviceEntryError, newAgentOrderState, confirmAgentOrderTotal, submitAgentOrder } = exportsObject
let passed = 0
const check = (value, expected, message) => { assert.equal(value, expected, message); passed++ }
const device = { imei: 'ABC12345678', model: '苹果 iPhone Air', category_id: 8, initial_price: 2000, initial_price_uid: 7, check_template_id: 13170, summary: { color: '2', battery: '99' } }
const input = () => ({ memberId: 75, deliveryType: '2', expressNo: '', devices: [structuredClone(device)], nextAssigneeUid: 9 })
const totalInput = (count = 3) => ({ memberId: 75, deliveryType: '2', expressNo: '', count })
const confirmedState = (count = 3) => ({ ...newAgentOrderState(), draftId: 12, confirmedCount: count })
const fixture = (count = 3) => {
    const calls = []
    const api = {
        async create(data) { calls.push(['create', data]); return { data: { id: 12, order_no: 'RTEST12' } } },
        async detail(id) { calls.push(['detail', id]); return { data: { id, member_id: 75, count, status: 1, sign_at: 0, devices: [] } } },
        async sign(id, data) { calls.push(['sign', id, data]); return { data: true } }
    }
    return { api, calls }
}
async function rejects(action, pattern) { await assert.rejects(action, pattern); passed++ }
async function main() {
    check(newAgentOrderState().confirmedCount, 0, '初始总台数尚未确认')
    check(agentTotalError(75, '2', '', 1), '', '允许一台')
    check(agentTotalError(75, '2', '', 10000), '', '允许一万台')
    for (const count of [0, -1, 10001, 1.5, NaN, Infinity, '', '1', undefined, null]) {
        check(Boolean(agentTotalError(75, '2', '', count)), true, `拒绝无效总台数 ${count}`)
    }
    check(agentTotalError(0, '2', '', 1), '请先选择客户', '确认总台数前必须选客户')
    check(agentTotalError(75, '1', '  ', 1), '请填写快递单号', '快递草稿先填运单号')
    check(deviceEntryError(device), '', '字母数字串号')
    for (const imei of ['', '12345', '1234567890123456', 'abc def', 'abc#123', '中文123456']) check(Boolean(deviceEntryError({ ...device, imei })), true, imei)
    check(deviceEntryError({ ...device, imei: '012345678901234' }), '', '保留前导零')
    check(Boolean(agentOrderError(0, '2', '', [device])), true, '客户必选')
    check(Boolean(agentOrderError(75, '1', '', [device])), true, '快递单号必填')
    check(agentOrderError(75, '1', ' ZTO123 ', [device]), '', '快递可提交')
    check(Boolean(agentOrderError(75, '2', '', [])), true, '至少一台')
    check(Boolean(agentOrderError(75, '2', '', [{ ...device, category_id: 0 }])), true, '必须选型号')
    check(Boolean(agentOrderError(75, '2', '', [device, { ...device, imei: device.imei.toLowerCase() }])), true, '串号不区分大小写去重')
    {
        const state = newAgentOrderState(), { api, calls } = fixture()
        await rejects(() => submitAgentOrder(state, api, input()), /请先确认订单总台数/)
        check(calls.length, 0, '未确认总台数不能偷偷建单')
        check(await confirmAgentOrderTotal(state, api, totalInput()), 12, '明确确认总台数后建空草稿')
        check(calls.map(c => c[0]).join(','), 'create', '确认时只建草稿，不签收或打印')
        check(state.confirmedCount, 3, '总台数按用户确认值锁定，不按已录设备数计算')
        check(state.orderNo, 'RTEST12', '保留单号')
        check(state.busy, false, '确认后释放提交锁')
        check(await confirmAgentOrderTotal(state, api, totalInput()), 12, '重复确认相同台数复用原单')
        await rejects(() => confirmAgentOrderTotal(state, api, totalInput(4)), /已锁定/)
        check(state.confirmedCount, 3, '不能修改已确认台数')
        check(calls.length, 1, '重复确认或尝试改台数都不再建单')
        check(await submitAgentOrder(state, api, input()), 12, '已确认草稿核对后签收成功')
        check(calls.map(c => c[0]).join(','), 'create,detail,sign', '先确认总台数，再核对原单和签收')
        check(state.busy, false, '释放签收锁')
        const create = calls[0][1], sign = calls[2][2]
        check(create.order_source, 'agent', '不混入自主下单奖励')
        check(create.draft_device_entry, true, '空草稿不生成占位设备')
        check(create.sign_after_create, false, '不隐式签收')
        check(create.devices.length, 0, '建单不重复写设备')
        check(create.count, 3, '草稿持久化真实总台数，不写0或当前设备数')
        check(sign.next_assignee_uid, 9, '质检负责人')
        check(sign.devices[0].initial_price_uid, 7, '报价员')
        check(sign.devices[0].summary.battery, '99', '摘要保留')
    }
    {
        const state = newAgentOrderState(), { api, calls } = fixture()
        await confirmAgentOrderTotal(state, api, totalInput())
        let failed = true
        api.sign = async (id, data) => { calls.push(['sign', id, data]); if (failed) throw { code: 0, msg: '质检负责人无效' }; return { data: true } }
        await rejects(() => submitAgentOrder(state, api, input()), /./)
        check(state.draftId, 12, '签收失败保留草稿')
        failed = false
        await submitAgentOrder(state, api, input())
        check(calls.filter(c => c[0] === 'create').length, 1, '签收重试不重复建单')
    }
    {
        const state = newAgentOrderState(), { api, calls } = fixture()
        api.create = async data => { calls.push(['create', data]); throw new Error('request timeout') }
        await rejects(() => confirmAgentOrderTotal(state, api, totalInput()), /timeout/)
        check(state.creatingUnknown, true, '超时明确未知')
        check(state.confirmedCount, 0, '创建未知不伪造总台数已确认')
        check(state.draftId, 0, '创建未知不伪造订单ID')
        check(state.busy, false, '创建未知释放交互锁')
        await rejects(() => confirmAgentOrderTotal(state, api, totalInput()), /创建结果尚未确认/)
        await rejects(() => submitAgentOrder(state, api, input()), /创建结果尚未确认/)
        check(calls.length, 1, '未知时不重建，也不继续签收')
    }
    {
        const state = newAgentOrderState(), { api } = fixture()
        api.create = async () => ({ data: {} })
        await rejects(() => confirmAgentOrderTotal(state, api, totalInput()), /缺少订单编号/)
        check(state.creatingUnknown, true, '异常响应不可重建')
        check(state.confirmedCount, 0, '异常响应不锁定虚假确认台数')
    }
    {
        const state = newAgentOrderState(), { api } = fixture()
        api.create = async () => { throw { code: 403, msg: '无创建权限' } }
        try { await confirmAgentOrderTotal(state, api, totalInput()) } catch {}
        check(state.creatingUnknown, false, '明确权限拒绝不锁死')
        check(state.busy, false, '明确拒绝后释放锁')
        api.create = async () => ({ data: { id: 12 } })
        check(await confirmAgentOrderTotal(state, api, totalInput(4)), 12, '明确拒绝修正后可以重新确认')
        check(state.confirmedCount, 4, '只在成功创建后锁定总台数')
    }
    {
        const state = newAgentOrderState(), { api } = fixture()
        const rejected = { code: 0, msg: '客户当前不可下单', data: { id: 12 } }
        api.create = async () => rejected
        let received
        try { await confirmAgentOrderTotal(state, api, totalInput()) } catch (cause) { received = cause }
        check(received, rejected, '显式失败响应保留原code与msg供UI提示')
        check(state.draftId, 0, '失败响应即使有ID也不能锁单')
        check(state.confirmedCount, 0, '失败响应不能锁定总台数')
        check(state.creatingUnknown, false, '明确业务失败允许修正后重试')
        check(state.busy, false, '显式失败释放提交锁')
        api.create = async () => ({ code: 1, data: { id: 12 } })
        check(await confirmAgentOrderTotal(state, api, totalInput()), 12, '明确失败后可重新确认并创建')
    }
    {
        const state = confirmedState(), { api, calls } = fixture()
        const data = input()
        state.lastSignPayload = JSON.stringify({ count: 3, devices: data.devices, nextAssigneeUid: data.nextAssigneeUid })
        api.detail = async id => ({ data: { id, member_id: 75, count: 3, status: 2, sign_at: 1793300000, devices: [device] } })
        check(await submitAgentOrder(state, api, input()), 12, '签收响应丢失读取原单成功')
        check(calls.length, 0, '已签收不重复签收或打印')
        const repriced = input(); repriced.devices[0].initial_price = 2200
        await rejects(() => submitAgentOrder(state, api, repriced), /当前修改未再次保存/)
        const added = input(); added.devices.push({ ...device, imei: 'NEW1234567' })
        await rejects(() => submitAgentOrder(state, api, added), /当前修改未再次保存/)
        state.lastSignPayload = JSON.stringify({ count: 2, devices: data.devices, nextAssigneeUid: data.nextAssigneeUid })
        await rejects(() => submitAgentOrder(state, api, input()), /与当前填写不一致/)
        state.lastSignPayload = JSON.stringify({ count: 3, devices: data.devices, nextAssigneeUid: data.nextAssigneeUid })
        api.detail = async id => ({ data: { id, member_id: 75, count: 3, status: 2, sign_at: 1793300000, devices: [] } })
        await rejects(() => submitAgentOrder(state, api, input()), /与当前填写不一致/)
        api.detail = async id => ({ data: { id, member_id: 75, count: 3, status: -1, sign_at: 0 } })
        await rejects(() => submitAgentOrder(state, api, input()), /已不在待签收/)
        api.detail = async id => ({ data: { id, member_id: 75, count: 3, status: 9, sign_at: 1793300000 } })
        await rejects(() => submitAgentOrder(state, api, input()), /已不在待签收/)
        api.detail = async id => ({ data: { id, member_id: 76, count: 3, status: 1 } })
        await rejects(() => submitAgentOrder(state, api, input()), /未核对成功/)
    }
    {
        const state = confirmedState(), { api, calls } = fixture()
        api.detail = async id => ({ data: { id, member_id: 75, count: 3, status: 1, devices: [{ id: 22, imei: device.imei.toLowerCase() }] } })
        await submitAgentOrder(state, api, input())
        check(calls[0][2].devices[0].id, 22, '已有设备沿用原ID')
    }
    {
        const state = newAgentOrderState(), { api, calls } = fixture()
        let finish
        api.create = data => { calls.push(['create', data]); return new Promise(resolve => { finish = resolve }) }
        const first = confirmAgentOrderTotal(state, api, totalInput())
        await rejects(() => confirmAgentOrderTotal(state, api, totalInput()), /正在提交/)
        await rejects(() => submitAgentOrder(state, api, input()), /正在提交/)
        finish({ data: { id: 12 } })
        await first
        check(calls.filter(c => c[0] === 'create').length, 1, '连点确认仅创建一次')
        check(calls.filter(c => c[0] === 'sign').length, 0, '确认连点不触发签收打印')
    }
    {
        const state = confirmedState(), { api, calls } = fixture()
        let finish
        api.sign = (id, data) => { calls.push(['sign', id, data]); return new Promise(resolve => { finish = resolve }) }
        const first = submitAgentOrder(state, api, input())
        await rejects(() => submitAgentOrder(state, api, input()), /正在提交/)
        finish({ data: true })
        await first
        check(calls.filter(c => c[0] === 'sign').length, 1, '连点签收仅提交一次')
        check(calls.filter(c => c[0] === 'create').length, 0, '签收绝不新建草稿')
    }
    {
        const state = { ...newAgentOrderState(), draftId: 12 }, { api, calls } = fixture()
        await rejects(() => confirmAgentOrderTotal(state, api, totalInput()), /已锁定/)
        await rejects(() => submitAgentOrder(state, api, input()), /请先确认订单总台数/)
        check(calls.length, 0, '已有未核对台数的草稿不重建、不改count、不签收')
    }
    {
        const state = confirmedState(1), { api, calls } = fixture(1)
        const data = input(); data.devices.push({ ...device, imei: 'NEW1234567' })
        await rejects(() => submitAgentOrder(state, api, data), /不能超过已确认/)
        check(calls.length, 0, '设备数超总台数时不请求接口')
        for (const count of [0, 2, undefined]) {
            api.detail = async id => { calls.push(['detail', id]); return { data: { id, member_id: 75, count, status: 1, devices: [] } } }
            await rejects(() => submitAgentOrder(state, api, input()), /原订单总台数与已确认台数不一致/)
        }
        check(calls.filter(c => c[0] === 'sign').length, 0, '原单总台数变化或缺失时不签收')
        check(calls.filter(c => c[0] === 'create').length, 0, '原单总台数不符也不重建')
        check(state.confirmedCount, 1, '核对失败不悄悄同步或覆盖确认台数')
        api.detail = async id => ({ data: { id, member_id: 75, count: 2, status: 2, sign_at: 1793300000, devices: [device] } })
        await rejects(() => submitAgentOrder(state, api, input()), /原订单总台数与已确认台数不一致/)
    }
    for (const relative of ['components/RecycleMemberPicker.vue', 'pages/order/components/SignOrderPopup.vue', 'pages/order/list.vue']) {
        const filename = path.join(app, 'src/addon/hsx_recycle', relative)
        const { descriptor, errors } = parse(fs.readFileSync(filename, 'utf8'), { filename })
        check(errors.length, 0, `${relative} parse`)
        const script = compileScript(descriptor, { id: relative })
        const template = compileTemplate({ id: relative, filename, source: descriptor.template.content, compilerOptions: { bindingMetadata: script.bindings } })
        check(template.errors.length, 0, `${relative} template`)
    }
    console.log(`[通过] ${passed} 项移动代下单隔离回归；无数据库、打印或线上请求。`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
