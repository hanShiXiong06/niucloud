// Exercise real customer hooks/handlers with in-memory API boundaries only.
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const req = require('node:module').createRequire(path.join(root, 'uni-app/package.json'))
const { transformSync } = req('esbuild')
const vue = req('vue')
const app = path.join(root, 'uni-app/src/addon/hsx_recycle')
const pkg = path.join(root, 'niucloud/addon/hsx_recycle/uni-app')
let checks = 0
function check(name, test) { test(); checks++; console.log('PASS', name) }
function read(file) {
  const text = fs.readFileSync(path.join(app, file), 'utf8')
  assert.equal(text, fs.readFileSync(path.join(pkg, file), 'utf8'), 'plugin mirror: ' + file)
  return text
}
function run(source, context = {}) {
  const sandbox = { ...context, module: { exports: {} } }
  vm.runInNewContext(transformSync(source, { loader: 'ts', format: 'cjs', target: 'es2020' }).code, sandbox)
  return sandbox.module.exports
}
async function main() {
  const utils = run(read('utils/pickup.ts'))
  for (const cancelState of ['pending', 'unknown', 'manual_review', 'confirmed']) {
    const dialogs = [], cancelToasts = []
    const actions = run(read('hooks/useOrderActions.ts').replace(/^import .*$/gm, ''), {
      updateOrderStatus: async () => ({ code: 1, data: { pickup: { state: 'accepted', cancellation: { state: cancelState }, message: '请联系门店核实原预约' } } }),
      uni: { showLoading() {}, hideLoading() {}, showToast: args => cancelToasts.push(args),
        showModal: args => { dialogs.push(args); if (args.title === '提示') args.success({ confirm: true }) } }
    }).useOrderActions()
    assert.equal(await actions.cancelOrder({ id: 123 }), true)
    check(`customer cancellation explains carrier result: ${cancelState}`, () => {
      if (cancelState === 'confirmed') assert.equal(cancelToasts[0].title, '订单已取消')
      else { assert.equal(cancelToasts.length, 0); assert.equal(dialogs[1].title, '订单已取消，快递需核实') }
    })
  }
  check('pending cancellation cannot unlock manual shipping or imply refreshed success', () => {
    assert.equal(utils.normalizePickup({ state: 'failed', can_manual: true, cancellation: { state: 'unknown' } }).can_manual, false)
    assert.ok(utils.pickupRefreshFeedback({ cancellation: { state: 'unknown' }, refresh_result: { status: 'updated' } }).message.includes('取消尚未确认'))
  })
  const now = new Date('2026-10-02T05:44:54+08:00')
  check('log-time appointment validates as Beijing time', () => assert.equal(utils.validatePickupTime('2026-10-02 09:00-18:00', true, now), ''))
  check('expired appointment does not pass', () => assert.match(utils.validatePickupTime('2026-10-02 05:00-18:00', true, now), /刷新/))
  check('invalid calendar date does not roll into next month', () => assert.match(utils.validatePickupTime('2026-02-30 09:00-18:00', true, now), /无效/))
  for (const state of ['unknown', 'submitting', 'accepted', 'confirmed']) {
    check('uncertain/active booking cannot self-ship: ' + state, () => assert.equal(utils.normalizePickup({ state, can_manual: true }).can_manual, false))
  }
  check('confirmed failure exposes self-shipping only when backend allows it', () => {
    assert.equal(utils.normalizePickup({ state: 'failed', can_manual: true }).can_manual, true)
    assert.equal(utils.normalizePickup({ state: 'failed', can_manual: true, conflict: true }).can_manual, false)
  })
  let response = { code: 1, data: { pickup_enabled: true, has_shop_address: true, pickup_time_supported: true, pickup_time_required: true,
    pickup_time: '2026-10-02 09:00-18:00', pickup_time_text: '今天（10月02日）09:00-18:00', payment_tips: '运费由商家承担，您无需支付。' } }
  let fail = false
  const policyRequests = []
  const checkExpressEnabled = async selected => { policyRequests.push(selected); if (fail) throw Error('offline'); return response }
  const hook = run(read('hooks/usePlatformDelivery.ts'), {
    require(name) {
      if (name === 'vue') return { ...vue, onMounted() {} }
      if (name === './useAddressParser') return { useAddressParser: () => ({ parseAddressInfo() {} }) }
      if (name === '../api/express') return { checkExpressEnabled }
      if (name === '@/app/api/member') return { getAddressList: async () => ({ code: 1, data: [] }) }
      throw Error('Unexpected import ' + name)
    }
  }).usePlatformDelivery()
  await hook.detectProvider()
  check('service policy populates customer form without selecting time', () => {
    assert.equal(hook.pickupAvailable.value, true)
    assert.equal(hook.platformDeliveryForm.value.pickup_time, response.data.pickup_time)
    assert.equal(hook.platformDeliveryForm.value.pickup_time_text, response.data.pickup_time_text)
    assert.equal(hook.paymentTips.value, response.data.payment_tips)
  })
  fail = true
  await hook.detectProvider()
  check('failed policy refresh clears stale appointment and disables booking', () => {
    assert.equal(hook.pickupAvailable.value, false)
    assert.equal(hook.platformDeliveryForm.value.pickup_time, '')
    assert.equal(hook.platformDeliveryForm.value.pickup_time_text, '')
  })
  const page = read('pages/order/order.vue')
  const promptSource = page.slice(page.indexOf('const shouldContinueWithPlatformPrompt ='), page.indexOf('const canUsePlatformDelivery ='))
  assert.ok(promptSource.length > 500)
  const confirmations = [], toasts = []
  const form = hook.platformDeliveryForm
  form.value.pickup_time = 'old'
  fail = false
  const prompt = run(promptSource + '\nexport { shouldContinueWithPlatformPrompt }', {
    currentTab: vue.ref(0), enablePlatformDelivery: vue.ref(true), canUsePlatformDelivery: vue.ref(true),
    orderSubmitConfig: vue.ref({ platform_delivery: { free_shipping_min_count: 1 } }),
    pickupTimeSupported: hook.pickupTimeSupported, needPickupTime: hook.needPickupTime, platformDeliveryForm: form,
    applyPickupPolicy: hook.applyPickupPolicy, paymentTips: hook.paymentTips, checkExpressEnabled, console: { error() {} },
    uni: { showToast: args => toasts.push(args) },
    showPlatformDeliveryMemoConfirm: async text => { confirmations.push(text); return true }
  }).shouldContinueWithPlatformPrompt
  assert.equal(await prompt(), true)
  check('submit refreshes time and shows exact appointment plus freight before confirmation', () => {
    assert.equal(form.value.pickup_time, response.data.pickup_time)
    assert.ok(confirmations[0].includes(response.data.pickup_time_text))
    assert.ok(confirmations[0].includes(response.data.payment_tips))
  })
  const chosen = '2026-10-04 13:00-15:00'
  form.value.pickup_time = chosen
  form.value.pickup_time_selected = true
  response = { code: 1, data: { ...response.data, pickup_time: chosen, pickup_time_text: '后天（10月04日）13:00-15:00' } }
  assert.equal(await prompt(), true)
  check('chosen future day goes to server preflight and is never silently replaced by default', () => {
    assert.equal(policyRequests.at(-1), chosen)
    assert.equal(form.value.pickup_time, chosen)
    assert.ok(confirmations.at(-1).includes('后天'))
  })
  response = { code: 1, data: { ...response.data, pickup_time_changed: true, pickup_time: '2026-10-05 09:00-18:00' } }
  assert.equal(await prompt(), false)
  check('expired explicit choice blocks submission and asks customer to reconfirm', () => {
    assert.equal(confirmations.length, 2)
    assert.equal(form.value.pickup_time_selected, false)
    assert.ok(toasts.at(-1).title.includes('原取件时段'))
  })
  response = { code: 1, data: { ...response.data, pickup_time_changed: false, pickup_time: '' } }
  assert.equal(await prompt(), false)
  check('missing time never proceeds to order confirmation', () => {
    assert.equal(confirmations.length, 2)
    assert.ok(toasts.at(-1).title.includes('取件时段'))
  })
  const section = read('pages/order/components/ExpressInfoSection.vue')
  check('customer gets default interval and can open date/slot selector without manual time input', () => {
    assert.ok(!section.includes('mode="date"') && !section.includes('mode="time"'))
    assert.ok(section.includes('pickup_time_text'))
    assert.ok(section.includes('<PickupTimePicker') && section.includes('pickup_time_selected: true'))
    assert.ok(section.includes('if (props.checkingPickup) return'))
  })
  const card = read('pages/order/components/PickupStatusCard.vue')
  check('manual waybill form is in the shared closeable popup', () => {
    assert.ok(card.includes('<OrderTaskPopup'))
    assert.ok(card.includes(':show="showManual && pickup.can_manual"'))
    assert.ok(card.includes(':busy="saving"'))
    assert.ok(card.includes('回收订单已提交，不需要重新下单'))
  })
  const saveSource = card.slice(card.indexOf('const saveManual ='), card.indexOf('const subscribePickup ='))
  const saving = vue.ref(false), showManual = vue.ref(true), pickup = vue.ref({ can_manual: true })
  const calls = [], events = []
  let finish
  const save = run(saveSource + '\nexport { saveManual }', {
    saving, showManual, pickup, refreshing: vue.ref(false), props: { orderId: 3124 },
    manualCompany: vue.ref('顺丰速运'), manualTracking: vue.ref('SF000000123456'),
    uni: { showToast: args => toasts.push(args) }, emit: event => events.push(event),
    submitManualPickup: async (id, payload) => { calls.push({ id, payload }); return new Promise(resolve => { finish = resolve }) }
  }).saveManual
  const first = save()
  await save()
  check('manual submission uses original order and prevents duplicate clicks', () => {
    assert.equal(calls.length, 1)
    assert.equal(calls[0].id, 3124)
    assert.equal(calls[0].payload.express_no, 'SF000000123456')
    assert.equal(saving.value, true)
  })
  finish({ code: 1 }); await first
  check('successful waybill save closes popup and refreshes order', () => {
    assert.equal(showManual.value, false)
    assert.equal(saving.value, false)
    assert.deepEqual(events, ['updated'])
  })
  pickup.value.can_manual = false
  await save()
  check('changed booking state cannot submit manual waybill', () => assert.equal(calls.length, 1))
  console.log(`Completed ${checks} checks. No DB, network, actual order or notification.`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
