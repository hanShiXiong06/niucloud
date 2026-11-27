// 离线验证：编译插件回执抽屉并模拟框架只读日志 API，不访问数据库或网络。
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../../../..')
const compiler = require(path.join(root, 'admin/node_modules/@vue/compiler-sfc'))
const ts = require(path.join(root, 'admin/node_modules/typescript'))
let checks = 0
const check = (condition, label) => { assert.ok(condition, label); checks++ }
const clone = value => JSON.parse(JSON.stringify(value))
const componentPath = path.join(__dirname, '../admin/views/third_party/components/PickupNoticeReceipts.vue')
const utilityPath = path.join(__dirname, '../admin/utils/pickup-notice-receipts.ts')
const source = fs.readFileSync(componentPath, 'utf8')
const utilitySource = fs.readFileSync(utilityPath, 'utf8')
const parsed = compiler.parse(source, { filename: componentPath })
check(parsed.errors.length === 0, 'receipt SFC parses')
compiler.compileScript(parsed.descriptor, { id: 'pickup-receipts' })
check(compiler.compileTemplate({ source: parsed.descriptor.template.content, filename: componentPath, id: 'pickup-receipts' }).errors.length === 0, 'receipt template compiles')
check(source === fs.readFileSync(path.join(root, 'admin/src/addon/hsx_recycle/views/third_party/components/PickupNoticeReceipts.vue'), 'utf8'), 'receipt component mirror is exact')
check(utilitySource === fs.readFileSync(path.join(root, 'admin/src/addon/hsx_recycle/utils/pickup-notice-receipts.ts'), 'utf8'), 'receipt mapping mirror is exact')
check(source.includes("from '@/app/api/notice'") && !source.includes('setPickupNoticeConfig'), 'receipt component only consumes framework read API')
const utilityModule = { exports: {} }
const compiledUtility = ts.transpileModule(utilitySource, { compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS } }).outputText
vm.runInNewContext(compiledUtility, { module: utilityModule, exports: utilityModule.exports })
const { PICKUP_NOTICE_KEY, toPickupReceiptRow } = utilityModule.exports
const accepted = { id: 1, key: PICKUP_NOTICE_KEY, create_time: '2026-09-29 14:30:00', notice_type: 'weapp', params: { order_id: 10, vars: { order_no: 'R10' }, receipt: { send_accepted: true, customer_read: false, message: '微信接口已受理，不代表已读' } } }
let row = toPickupReceiptRow(accepted)
check(row.status === 'recorded' && row.statusText === '框架已记录' && row.tagType === 'info', 'framework history never upgrades old acceptance flag to confirmed delivery')
check(row.order === 'R10' && row.channel === '微信小程序' && row.time === accepted.create_time, 'order, channel and timestamp map correctly')
check(row.message.includes('送达未确认') && row.message.includes('不能据此判定微信受理'), 'framework log displays truthful delivery boundary')
const declined = clone(accepted)
declined.notice_type = 'wechat'
declined.params.receipt = { send_accepted: false, message: '微信拒绝通知：43101 user refuse' }
delete declined.params.vars
row = toPickupReceiptRow(declined)
check(row.status === 'recorded' && row.statusText === '框架已记录' && row.channel === '微信公众号', 'historical failure remains a record rather than certain non-delivery')
check(row.order === '订单 ID：10' && row.message.includes('43101'), 'missing order number falls back to order id and preserves failure reason')
const old = { id: 3, notice_type: 'weapp', params: { order_id: 10 }, is_click: 1, result: '' }
row = toPickupReceiptRow(old)
check(row.status === 'recorded' && row.message.includes('送达未确认'), 'native row without receipt never means success')
for (const receipt of [null, [], 'bad-json', { send_accepted: 'true' }, { send_accepted: 1 }]) {
    check(toPickupReceiptRow({ ...old, params: { receipt } }).message.includes('送达未确认'), 'missing or nonboolean receipt leaves delivery unconfirmed')
}
check(toPickupReceiptRow({ ...accepted, params: JSON.stringify(accepted.params) }).order === 'R10', 'historical JSON string params can be read safely')
check(toPickupReceiptRow({ params: 'not-json' }).order === '—', 'malformed JSON does not crash display')
check(toPickupReceiptRow({ ...old, create_time: 1790663400 }).time !== '1790663400', 'numeric timestamp is formatted for display')
check(toPickupReceiptRow({ notice_type: 'wechat', params: { order_no: 'R-native-wechat' } }).order === 'R-native-wechat', 'native Wechat flat vars are supported')
check(toPickupReceiptRow({ notice_type: 'weapp', params: { vars: { order_no: 'R-native-weapp' } } }).order === 'R-native-weapp', 'native Weapp nested vars are supported')
check(toPickupReceiptRow({ ...old, result: '框架执行异常' }).message.includes('框架执行异常'), 'framework error text remains visible when provided')
check(source.includes('取件通知执行记录') && !source.includes('label="接口受理结果"'), 'drawer labels describe framework execution instead of provider acceptance')
const orderDialogPath = path.join(__dirname, '../admin/views/recycle_order/components/NoticeLogDialog.vue')
const orderDialogSource = fs.readFileSync(orderDialogPath, 'utf8')
const orderDialogParsed = compiler.parse(orderDialogSource, { filename: orderDialogPath })
check(orderDialogParsed.errors.length === 0, 'order notice log dialog parses')
compiler.compileScript(orderDialogParsed.descriptor, { id: 'order-notice-log' })
check(compiler.compileTemplate({ source: orderDialogParsed.descriptor.template.content, filename: orderDialogPath, id: 'order-notice-log' }).errors.length === 0, 'order notice log dialog template compiles')
check(orderDialogSource === fs.readFileSync(path.join(root, 'admin/src/addon/hsx_recycle/views/recycle_order/components/NoticeLogDialog.vue'), 'utf8'), 'order notice dialog mirror is exact')
const statusFunction = orderDialogParsed.descriptor.scriptSetup.content.match(/const getStatusType = [\s\S]*?\n};/)[0]
const getStatusType = vm.runInNewContext(ts.transpileModule(statusFunction + '\n;getStatusType', { compilerOptions: { target: ts.ScriptTarget.ES2020 } }).outputText)
check(getStatusType(4) === 'info' && getStatusType('4') === 'info', 'framework-dispatched status 4 stays informational rather than delivery success')
check(orderDialogSource.includes('不代表微信已受理或客户已收到'), 'order dialog explains dispatched status delivery boundary')

const script = parsed.descriptor.scriptSetup.content.replace(/^import .*$/gm, '') + '\n;({visible, page, limit, total, loading, error, rows, loadReceipts})'
const executable = ts.transpileModule(script, { compilerOptions: { target: ts.ScriptTarget.ES2020 } }).outputText
function harness() {
    const props = { modelValue: false }
    const state = { calls: [], emitted: [], response: { data: { data: [accepted, { ...accepted, id: 99, key: 'other_notice' }], total: 1 } }, error: null, queue: [] }
    const context = {
        PICKUP_NOTICE_KEY, toPickupReceiptRow,
        ref: value => ({ value }),
        computed: config => ({ get value() { return config.get() }, set value(value) { config.set(value) } }),
        defineProps: () => props,
        defineEmits: () => (...args) => state.emitted.push(args),
        watch: (getter, callback, options) => { state.watch = callback; if (options?.immediate) callback(getter()) },
        getNoticeLog: async params => {
            state.calls.push(clone(params))
            if (state.queue.length) return state.queue.shift()
            if (state.error) throw state.error
            return clone(state.response)
        }
    }
    const page = vm.runInNewContext(executable, context)
    const toggle = async value => { props.modelValue = value; state.watch(value); await Promise.resolve(); await Promise.resolve() }
    return { props, state, page, toggle }
}
async function main() {
    const { state, page, toggle } = harness()
    await page.loadReceipts()
    check(state.calls.length === 0, 'closed drawer never loads logs')
    await toggle(true)
    check(state.calls.length === 1 && state.calls[0].key === PICKUP_NOTICE_KEY && state.calls[0].page === 1 && state.calls[0].limit === 10, 'opening queries only fixed pickup notice key with default pagination')
    check(page.rows.value.length === 1 && page.rows.value[0].order === 'R10', 'unrelated notice rows cannot leak into pickup drawer')
    await page.loadReceipts(3)
    check(state.calls.at(-1).page === 3 && state.calls.at(-1).key === PICKUP_NOTICE_KEY, 'page changes preserve fixed notice filter')
    page.limit.value = 20
    await page.loadReceipts(1)
    check(state.calls.at(-1).limit === 20 && state.calls.at(-1).page === 1, 'page-size change reloads the first page')
    page.visible.value = false
    check(state.emitted.at(-1)[0] === 'update:modelValue' && state.emitted.at(-1)[1] === false, 'drawer close emits standard v-model event')
    state.error = { msg: '没有接口权限' }
    await page.loadReceipts()
    check(page.error.value.includes('没有接口权限') && page.error.value.includes('消息发送记录') && page.rows.value.length === 0 && !page.loading.value, 'permission failure gives actionable error without stale rows')
    state.error = null
    state.response = { data: {} }
    await page.loadReceipts()
    check(page.error.value.includes('返回格式异常') && page.total.value === 0, 'malformed response is not shown as successful empty history')
    await toggle(false)
    check(page.rows.value.length === 0 && page.error.value === '' && page.total.value === 0, 'closing clears receipt and error state')

    const race = harness()
    race.props.modelValue = true
    let resolveOld
    race.state.queue.push(new Promise(resolve => { resolveOld = resolve }))
    const oldRequest = race.page.loadReceipts(1)
    race.state.response = { data: { data: [declined], total: 1 } }
    await race.page.loadReceipts(2)
    resolveOld({ data: { data: [accepted], total: 99 } })
    await oldRequest
    check(race.page.page.value === 2 && race.page.total.value === 1 && race.page.rows.value[0].message.includes('43101'), 'late old page result cannot overwrite latest page')
    let resolveHidden
    race.state.queue.push(new Promise(resolve => { resolveHidden = resolve }))
    const hiddenRequest = race.page.loadReceipts(1)
    await race.toggle(false)
    resolveHidden({ data: { data: [accepted], total: 1 } })
    await hiddenRequest
    check(race.page.rows.value.length === 0 && !race.page.loading.value, 'late result after close stays hidden and cleared')
    console.log(`PASS ${checks} checks (SFC + receipt mapping + mock framework log API; no network)`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
