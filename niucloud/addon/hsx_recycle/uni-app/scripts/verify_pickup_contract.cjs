/* Offline checks only: no HTTP, shipping creation, or real notification. */
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../../../../..')
const base = path.join(root, 'niucloud/addon/hsx_recycle/uni-app')
const ts = require(path.join(root, 'uni-app/node_modules/typescript'))
const sfc = require(path.join(root, 'uni-app/node_modules/@vue/compiler-sfc'))
let checks = 0
function check(value, message) { assert.ok(value, message); checks++ }
function compileModule(file, imports = {}, globals = {}) {
  const source = fs.readFileSync(path.join(base, file), 'utf8')
  const compiled = ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS }, reportDiagnostics: true, fileName: file })
  check(!compiled.diagnostics?.length, `${file}: TypeScript syntax`)
  const module = { exports: {} }
  vm.runInNewContext(compiled.outputText, { module, exports: module.exports, require: key => {
    if (!(key in imports)) throw new Error(`Unmocked import: ${key}`)
    return imports[key]
  }, console: { log() {}, error() {} }, ...globals }, { filename: file })
  return module.exports
}

const utils = compileModule('utils/pickup.ts')
const now = new Date(2030, 0, 1, 8, 0)
check(utils.validatePickupTime('', false, now) === '', 'optional blank time')
check(utils.validatePickupTime('', true, now) !== '', 'required blank time rejected')
check(utils.validatePickupTime('2030-01-01 09:00-11:00', true, now) === '', 'future valid slot')
for (const value of ['2030-01-01 07:00-09:00', '2030-01-01 11:00-09:00', '2030-01-01 09:00-09:00', '2030-02-30 09:00-11:00', '2030-01-01 25:00-26:00', '2030-01-01 09:00-', 'tomorrow']) {
  check(utils.validatePickupTime(value, true, now) !== '', `invalid slot ${value}`)
}
check(utils.normalizePickup().state === 'not_requested', 'missing data never implies success')
check(utils.normalizePickup({ state: 'new_vendor_state' }).state === 'unknown', 'unrecognized state is unknown')
for (const state of ['not_requested', 'submitting', 'accepted', 'confirmed', 'assigned', 'picked_up', 'in_transit', 'delivered', 'unknown', 'manual', 'exception']) {
  const result = utils.normalizePickup({ state, can_manual: true })
  check(result.can_manual === false, `${state} cannot switch to manual shipping`)
  check(result.title.length > 0 && result.message.length > 0, `${state} has honest next step`)
}
for (const state of ['failed', 'cancelled']) {
  check(utils.normalizePickup({ state, can_manual: true }).can_manual, `${state} can follow server permission`)
  check(!utils.normalizePickup({ state, can_manual: false }).can_manual, `${state} respects server denial`)
}
check(utils.pickupReceiverText({ contact_name: '测试门店', mobile: '00000000000', province: '测试省', city: '测试市', address: '测试地址' }).includes('测试省测试市测试地址'), 'copy receiver address')

async function testAvailability() {
  let response = { code: 1, data: { enabled: false, has_shop_address: true } }
  const hook = compileModule('hooks/usePlatformDelivery.ts', {
    vue: { ref: value => ({ value }), onMounted() {} },
    './useAddressParser': { useAddressParser: () => ({ parseAddressInfo: () => ({}) }) },
    '../api/express': { checkExpressEnabled: async () => response },
    '@/app/api/member': { getAddressList: async () => ({ code: 1, data: [] }) }
  }, { uni: { showToast() {} } }).usePlatformDelivery()
  await hook.detectProvider()
  check(!hook.pickupAvailable.value, 'disabled configuration cannot book pickup')
  await hook.handlePlatformDeliveryToggle()
  check(!hook.enablePlatformDelivery.value, 'cannot toggle unconfigured pickup on')
  response = { code: 1, data: { pickup_enabled: true, enabled: true, has_shop_address: true, carrier_name: '测试承运商', payment_tips: '寄件人支付，实际运费以快递员确认为准', pickup_time_supported: true, pickup_time_required: true } }
  await hook.detectProvider()
  check(hook.pickupAvailable.value && hook.needPickupTime.value, 'explicitly configured pickup/time allowed')
  check(hook.paymentTips.value === response.data.payment_tips, 'configured payer explanation is available read-only')
  hook.platformDeliveryForm.value.pickup_time = '2030-01-02 09:00-11:00'
  await hook.detectProvider()
  check(hook.platformDeliveryForm.value.pickup_time === '2030-01-02 09:00-11:00', 'rechecking supported service preserves selected slot')
  response = { code: 1, data: { pickup_enabled: true, has_shop_address: false } }
  await hook.detectProvider()
  check(!hook.pickupAvailable.value, 'missing store address cannot book pickup')
  response = { code: 0 }
  await hook.detectProvider()
  check(!hook.pickupAvailable.value && !hook.checkingPickup.value, 'failed check stays unavailable')
  check(hook.paymentTips.value === '', 'failed check does not retain stale payer explanation')
}

const scopedFiles = ['api/order.ts', 'api/express.ts', 'types/order.ts', 'utils/pickup.ts', 'hooks/useOrderSubmit.ts', 'hooks/usePlatformDelivery.ts', 'pages/order/order.vue', 'pages/order/detail.vue', 'pages/order/components/ExpressInfoSection.vue', 'pages/order/components/OrderCard.vue', 'pages/order/components/PickupStatusCard.vue']
for (const file of scopedFiles.filter(file => file.endsWith('.vue'))) {
  const filename = path.join(base, file)
  const { descriptor, errors } = sfc.parse(fs.readFileSync(filename, 'utf8'), { filename })
  check(errors.length === 0, `${file}: parse`)
  const script = sfc.compileScript(descriptor, { id: file })
  const compiled = sfc.compileTemplate({ source: descriptor.template.content, filename, id: file, compilerOptions: { bindingMetadata: script.bindings } })
  check(compiled.errors.length === 0, `${file}: template`)
}

async function testSubmit(state, reject = false, validationError = false) {
  const calls = [], modals = [], subscriptions = []
  const api = { createOrder: async data => {
    calls.push(data)
    if (validationError) throw { code: 400, msg: '请选择可用时段' }
    if (reject) throw new Error('mock network interrupted')
    return { code: 1, data: { id: 7, pickup: { state } } }
  } }
  const hook = compileModule('hooks/useOrderSubmit.ts', {
    vue: { ref: value => ({ value }) }, '../api/order': api,
    '../utils/pickup': utils,
    '@/hooks/useSubscribeMessage': { useSubscribeMessage: () => ({ request: key => subscriptions.push(key) }) },
    './useWechatFollow': { useWechatFollow: () => ({ checkAndShowFollow: async () => false, dismissFollow() {} }) }
  }, {
    uni: { showToast() {}, showLoading() {}, hideLoading() {}, navigateTo() {}, showModal: value => { modals.push(value); value.complete?.(); value.success?.() } },
    setTimeout: fn => fn()
  }).useOrderSubmit()
  const params = {
    form: { count: 1, delivery_type: 1, express_no: 'OLD_MANUAL_NUMBER' }, phoneList: [], currentTab: 0,
    usePlatformDelivery: true, isAgreeRecycle: true, logisticsVehicleForm: {},
    platformDeliveryForm: { sender_name: '测试', sender_mobile: '00000000000', province: '省', city: '市', district: '区', detail_address: '测试地址', pickup_time: '', weight: '1', provider: 'should-not-send', product_code: 'should-not-send', pay_type: 'should-not-send' },
    formRef: { validate: async () => true }
  }
  await hook.submitOrder(params)
  check(calls.length === 1, `${state}: exactly one create`)
  check(!('provider' in calls[0].express_config) && !('product_code' in calls[0].express_config) && !('pay_type' in calls[0].express_config), `${state}: no client channel or payer fields`)
  check(calls[0].express_no === '', `${state}: no stale manual waybill in pickup request`)
  check(subscriptions[0] === 'hsx_recycle_pickup_update', `${state}: dedicated pickup subscription`)
  check(!hook.submitting.value, `${state}: submit lock released`)
  if (validationError) {
    check(modals.length === 0, 'known validation error does not pretend result is unknown')
    await hook.submitOrder(params)
    check(calls.length === 2, 'known validation error allows correction and resubmit')
  } else if (reject) {
    check(modals[0].title === '提交结果待核实', 'network uncertainty never reported as pickup failure')
    await hook.submitOrder(params)
    check(calls.length === 1, 'uncertain create cannot be retried from same form')
  } else {
    check(modals[0].title === '回收订单已提交', `${state}: separate recycle-order success`)
    check(modals[0].content.includes(utils.normalizePickup({ state }).title), `${state}: display actual pickup result`)
  }
}

;(async () => {
  await testAvailability()
  for (const state of ['accepted', 'confirmed', 'failed', 'unknown']) await testSubmit(state)
  await testSubmit('unknown', true)
  await testSubmit('validation', false, true)
  const formSource = fs.readFileSync(path.join(base, 'pages/order/components/ExpressInfoSection.vue'), 'utf8')
  check(!formSource.includes('useReceivingChannels') && !formSource.includes('v-for="channel'), 'no customer channel selector')
  const orderSource = fs.readFileSync(path.join(base, 'pages/order/order.vue'), 'utf8')
  check(!formSource.includes('包邮') && !orderSource.includes('包邮'), 'customer booking UI never promises free shipping')
  check(formSource.includes('Number(props.orderCount || 0) >= Number(props.freeShippingMinCount || 1)'), 'existing quantity threshold is retained')
  check(formSource.includes('运费说明：{{ paymentTips ||') && formSource.includes('预约服务不代表免费寄件'), 'payer explanation rendered with safe fallback')
  check(orderSource.includes('paymentTips.value = String(res.data.payment_tips ||') && orderSource.includes('运费说明：${paymentMessage}'), 'latest payer explanation included in submission confirmation')
  const apiSource = fs.readFileSync(path.join(base, 'api/order.ts'), 'utf8')
  check(apiSource.includes('recycle/recycle_order/${id}/pickup/manual'), 'manual waybill targets existing order')
  check(apiSource.includes('recycle/recycle_order/${id}/pickup/refresh'), 'refresh targets existing order')
  const cardSource = fs.readFileSync(path.join(base, 'pages/order/components/PickupStatusCard.vue'), 'utf8')
  check(cardSource.includes('^[a-zA-Z0-9-]{6,50}$') && cardSource.includes('maxlength="50"'), 'manual waybill length matches server and existing schema')
  check(cardSource.includes('showManual && pickup.can_manual') && cardSource.includes('!pickup.value.can_manual) return'), 'manual form and submit both enforce current permission')
  for (const file of scopedFiles) {
    check(fs.readFileSync(path.join(base, file), 'utf8') === fs.readFileSync(path.join(root, 'uni-app/src/addon/hsx_recycle', file), 'utf8'), `${file}: mirror matches`)
  }
  console.log(`PASS ${checks} checks (offline pickup UI contract; no live shipment or notification)`)
})().catch(error => { console.error(error); process.exitCode = 1 })
