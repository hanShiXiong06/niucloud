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
const { agentOrderError, deviceEntryError, newAgentOrderState, submitAgentOrder } = exportsObject
let passed = 0
const check = (value, expected, message) => { assert.equal(value, expected, message); passed++ }
const device = { imei: 'ABC12345678', model: '苹果 iPhone Air', category_id: 8, initial_price: 2000, initial_price_uid: 7, check_template_id: 13170, summary: { color: '2', battery: '99' } }
const input = () => ({ memberId: 75, deliveryType: '2', expressNo: '', devices: [structuredClone(device)], nextAssigneeUid: 9 })
const fixture = () => {
    const calls = []
    const api = {
        async create(data) { calls.push(['create', data]); return { data: { id: 12, order_no: 'RTEST12' } } },
        async detail(id) { calls.push(['detail', id]); return { data: { id, member_id: 75, status: 1, sign_at: 0, devices: [] } } },
        async sign(id, data) { calls.push(['sign', id, data]); return { data: true } }
    }
    return { api, calls }
}
async function rejects(action, pattern) { await assert.rejects(action, pattern); passed++ }
async function main() {
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
        check(await submitAgentOrder(state, api, input()), 12, '创建签收成功')
        check(calls.map(c => c[0]).join(','), 'create,detail,sign', '先创建再核对再签收')
        check(state.orderNo, 'RTEST12', '保留单号')
        check(state.busy, false, '释放提交锁')
        const create = calls[0][1], sign = calls[2][2]
        check(create.order_source, 'agent', '不混入自主下单奖励')
        check(create.draft_device_entry, true, '空草稿不生成占位设备')
        check(create.sign_after_create, false, '不隐式签收')
        check(create.devices.length, 0, '建单不重复写设备')
        check(sign.next_assignee_uid, 9, '质检负责人')
        check(sign.devices[0].initial_price_uid, 7, '报价员')
        check(sign.devices[0].summary.battery, '99', '摘要保留')
    }
    {
        const state = newAgentOrderState(), { api, calls } = fixture()
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
        api.create = async () => { throw new Error('request timeout') }
        await rejects(() => submitAgentOrder(state, api, input()), /timeout/)
        check(state.creatingUnknown, true, '超时明确未知')
        await rejects(() => submitAgentOrder(state, api, input()), /创建结果尚未确认/)
        check(calls.length, 0, '未知时不继续签收')
    }
    {
        const state = newAgentOrderState(), { api } = fixture()
        api.create = async () => ({ data: {} })
        await rejects(() => submitAgentOrder(state, api, input()), /缺少订单编号/)
        check(state.creatingUnknown, true, '异常响应不可重建')
    }
    {
        const state = newAgentOrderState(), { api } = fixture()
        api.create = async () => { throw { code: 403, msg: '无创建权限' } }
        try { await submitAgentOrder(state, api, input()) } catch {}
        check(state.creatingUnknown, false, '明确权限拒绝不锁死')
    }
    {
        const state = { ...newAgentOrderState(), draftId: 12 }, { api, calls } = fixture()
        const data = input()
        state.lastSignPayload = JSON.stringify({ devices: data.devices, nextAssigneeUid: data.nextAssigneeUid })
        api.detail = async id => ({ data: { id, member_id: 75, status: 2, sign_at: 1793300000, devices: [device] } })
        check(await submitAgentOrder(state, api, input()), 12, '签收响应丢失读取原单成功')
        check(calls.length, 0, '已签收不重复签收或打印')
        const repriced = input(); repriced.devices[0].initial_price = 2200
        await rejects(() => submitAgentOrder(state, api, repriced), /当前修改未再次保存/)
        const added = input(); added.devices.push({ ...device, imei: 'NEW1234567' })
        await rejects(() => submitAgentOrder(state, api, added), /当前修改未再次保存/)
        api.detail = async id => ({ data: { id, member_id: 75, status: 2, sign_at: 1793300000, devices: [] } })
        await rejects(() => submitAgentOrder(state, api, input()), /与当前填写不一致/)
        api.detail = async id => ({ data: { id, member_id: 75, status: -1, sign_at: 0 } })
        await rejects(() => submitAgentOrder(state, api, input()), /已不在待签收/)
        api.detail = async id => ({ data: { id, member_id: 75, status: 9, sign_at: 1793300000 } })
        await rejects(() => submitAgentOrder(state, api, input()), /已不在待签收/)
        api.detail = async id => ({ data: { id, member_id: 76, status: 1 } })
        await rejects(() => submitAgentOrder(state, api, input()), /未核对成功/)
    }
    {
        const state = { ...newAgentOrderState(), draftId: 12 }, { api, calls } = fixture()
        api.detail = async id => ({ data: { id, member_id: 75, status: 1, devices: [{ id: 22, imei: device.imei.toLowerCase() }] } })
        await submitAgentOrder(state, api, input())
        check(calls[0][2].devices[0].id, 22, '已有设备沿用原ID')
    }
    {
        const state = newAgentOrderState(), { api, calls } = fixture()
        let finish
        api.create = () => new Promise(resolve => { finish = resolve })
        const first = submitAgentOrder(state, api, input())
        await rejects(() => submitAgentOrder(state, api, input()), /正在提交/)
        finish({ data: { id: 12 } })
        await first
        check(calls.filter(c => c[0] === 'sign').length, 1, '连点仅签收一次')
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
