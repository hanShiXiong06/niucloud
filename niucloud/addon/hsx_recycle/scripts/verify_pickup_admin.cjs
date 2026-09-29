// 本地离线验证，不连接数据库、不发送寄件请求。
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../../../..')
const compiler = require(path.join(root, 'admin/node_modules/@vue/compiler-sfc'))
const ts = require(path.join(root, 'admin/node_modules/typescript'))
const sass = require(path.join(root, 'admin/node_modules/sass'))
const source = fs.readFileSync(path.join(__dirname, '../admin/views/express/order_record.vue'), 'utf8')
let checks = 0
function check(condition, label) { assert.ok(condition, label); checks++ }
function js(source) { return ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2020 } }).outputText }
function section(start, end) {
    const a = source.indexOf(start), b = source.indexOf(end, a + start.length)
    assert.ok(a >= 0 && b > a, `Missing test section: ${start}`)
    return source.slice(a, b)
}
for (const relative of ['express/order_record.vue', 'third_party/express_order.vue']) {
    const file = path.join(__dirname, '../admin/views', relative)
    const text = fs.readFileSync(file, 'utf8')
    const parsed = compiler.parse(text, { filename: file })
    check(parsed.errors.length === 0, `parse ${relative}`)
    compiler.compileScript(parsed.descriptor, { id: 'pickup-regression' })
    const template = compiler.compileTemplate({ source: parsed.descriptor.template.content, filename: file, id: 'pickup-regression', compilerOptions: { expressionPlugins: ['typescript'] } })
    check(template.errors.length === 0, `template ${relative}`)
    for (const style of parsed.descriptor.styles) if (style.lang === 'scss') sass.compileString(style.content)
    check(text === fs.readFileSync(path.join(root, 'admin/src/addon/hsx_recycle/views', relative), 'utf8'), `mirror ${relative}`)
}
const helpers = vm.runInNewContext(js(section('const pickupData =', '// 只保存随机预约编号') + '\n;({ pickupReason, canResolveUnbooked, bookingState, feePending, courierPhone })'))
check(!helpers.canResolveUnbooked({ pickup: { booking_state: 'unknown' } }), 'unknown alone cannot unlock manual shipping')
check(helpers.canResolveUnbooked({ can_resolve_unbooked: true }), 'server eligibility authorizes action')
check(!helpers.canResolveUnbooked({ can_resolve_unbooked: 1 }), 'nonboolean eligibility denied')
check(helpers.pickupReason({ pickup: { manual_review: { remark: '渠道确认无任务' }, failure_reason: '旧错误' } }) === '渠道确认无任务', 'verified result visible')
check(helpers.bookingState({ order_status: 'pending', pickup: { booking_state: 'unknown' } }) === 'unknown', 'actual pickup state wins')
check(helpers.feePending({ pickup: { provider: 'kuaidi100' }, actual_cost: 0 }), 'zero initial fee is not settled')
check(!helpers.feePending({ pickup: { provider: 'kuaidi100', fee_verification_state: 'manual_confirmed' }, actual_cost: 0 }), 'verified zero fee supported')
check(helpers.courierPhone({ pickup: { courier_phone: '13800000000' } }) === '13800000000', 'courier phone normalized')
const cache = new Map()
let ids = 0
const requestContext = { storage: { get: () => 100000 }, sessionStorage: { getItem: k => cache.get(k), setItem: (k, v) => cache.set(k, v), removeItem: k => cache.delete(k) }, window: { crypto: { randomUUID: () => `random-${++ids}` } } }
const request = vm.runInNewContext(js(section('function manualRequestStorageKey()', 'const handleResolveUnbooked =') + '\n;({stableManualRequestId,finishManualRequest})'), requestContext)
const key = request.stableManualRequestId()
check(request.stableManualRequestId() === key, 'reopen keeps stable request key')
request.finishManualRequest('other-key')
check(request.stableManualRequestId() === key, 'other records do not discard unknown request')
request.finishManualRequest(key)
check(request.stableManualRequestId() !== key, 'confirmed completion rotates request key')
async function testSubmit(state, throws = false) {
    const notices = [], cleared = [], sent = []
    const context = {
        ensureOrderSendTime() {}, validateShipment: () => true, quoteState: { valid: true, signature: 'q' }, quoteSignature: () => 'q',
        shipmentForm: { thirdOrderNo: 'stable-42' }, stableManualRequestId: () => 'must-not-change', selectedQuoteIsKuaidi100: { value: false },
        shipmentLoading: {}, createDialogVisible: { value: true }, finishManualRequest: k => cleared.push(k), refreshPage: async () => {},
        ElMessage: { success: m => notices.push(['success', m]), warning: m => notices.push(['warning', m]) },
        createExpressOrderDirect: async p => { sent.push(p); if (throws) throw new Error('timeout'); return { data: { booking_state: state } } }
    }
    const run = vm.runInNewContext(js(section('const runShipmentCreate =', 'const canOperateClose =') + '\n;runShipmentCreate'), context)
    await run()
    check(sent[0].thirdOrderNo === 'stable-42', `${state} preserves request id`)
    check(context.shipmentLoading.create === false, `${state} clears loading`)
    if (['unknown', 'submitting', 'failed', 'exception'].includes(state) || throws) {
        check(!notices.some(x => x[0] === 'success'), `${state} never reports success`)
        check(cleared.length === (state === 'failed' && !throws ? 1 : 0), `${state} preserves uncertain id`)
    } else {
        check(notices.some(x => x[0] === 'success') && cleared.length === 1, `${state} confirmed submission ends draft`)
    }
}
;(async () => {
    for (const state of ['unknown', 'submitting', 'failed', 'exception', 'accepted', 'confirmed']) await testSubmit(state)
    await testSubmit('unknown', true)
    check(source.includes('v-if="!isKuaidi100(row) && (row.order_no || row.delivery_id)"'), 'K100 labels hidden')
    check(source.includes('!isKuaidi100(row) && canOperateClose(row)'), 'K100 intercept hidden')
    check(source.includes('ElMessageBox.prompt') && source.includes('最终确认：核实未预约'), 'manual unlock requires evidence and second confirmation')
    console.log(`PASS ${checks} admin pickup checks (SFC + state/idempotency mocks, no DB/network)`)
})().catch(error => { console.error(error); process.exitCode = 1 })
