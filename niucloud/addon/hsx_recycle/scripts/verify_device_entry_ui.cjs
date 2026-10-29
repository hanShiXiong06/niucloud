// 编译并执行实际录入组件的 setup/保存方法，外部接口隔离为 spy；不发送业务请求。
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const admin = path.join(root, 'admin')
const base = path.join(admin, 'src/addon/hsx_recycle')
const req = require('node:module').createRequire(path.join(admin, 'package.json'))
const { transformSync } = req('esbuild')
const { parse, compileScript, compileTemplate, compileStyle } = req('@vue/compiler-sfc')
const vue = { ...req('vue'), watch() {}, onMounted() {}, onBeforeUnmount() {} }
const calls = [], messages = [], emitted = []
const feedback = Object.fromEntries(['warning', 'error', 'success'].map(key => [key, value => messages.push(value)]))
const api = new Proxy({}, { get: (_, key) => async (...args) => { calls.push([key, ...args]); return { code: 1, data: { device_id: 88, id: 90 } } } })
let checks = 0
function check(name, fn) { fn(); checks++; console.log('PASS', name) }
function evaluate(file) {
  let source = fs.readFileSync(file, 'utf8')
  if (file.endsWith('.vue')) {
    const { descriptor, errors } = parse(source, { filename: file })
    assert.equal(errors.length, 0)
    const script = compileScript(descriptor, { id: 'imei-qa' })
    const template = compileTemplate({ source: descriptor.template.content, filename: file, id: 'imei-qa', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    for (const style of descriptor.styles) assert.deepEqual(compileStyle({ source: style.content, filename: file, id: 'imei-qa', preprocessLang: style.lang, scoped: style.scoped }).errors, [])
    source = script.content
  }
  const context = { module: { exports: {} }, console, window: { innerWidth: 1280 }, setTimeout, clearTimeout,
    require(name) {
      if (name === 'vue') return vue
      if (name === 'element-plus') return { ElMessage: feedback, ElMessageBox: { confirm: async () => { calls.push(['confirm']); return true } } }
      if (name.includes('hsx_components/core')) return { useFeedback: () => feedback }
      if (name.includes('/api/')) return api
      if (name.endsWith('.vue') || name.includes('icons-vue')) return {}
      if (name.endsWith('useLocalDevice')) return { useLocalDevice: () => ({ fetching: vue.ref(false), readWarnings: vue.ref([]), stopAuto() {} }) }
      if (name.endsWith('hooks/useSubmit')) return { useSubmit: () => ({ loading: vue.ref(false), run: fn => fn() }), confirmDanger: async () => true }
      if (name.endsWith('deviceReadings')) return {}
      const absolute = name.startsWith('@/') ? path.join(admin, 'src', name.slice(2)) : path.resolve(path.dirname(file), name)
      return evaluate(absolute + '.ts')
    } }
  vm.runInNewContext(transformSync(source, { loader: 'ts', format: 'cjs', target: 'es2020' }).code, context, { filename: file })
  return context.module.exports
}
function setup(file, props) { return evaluate(path.join(base, file)).default.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }) }
async function main() {
  const validation = evaluate(path.join(base, 'components/device-entry/imeiValidation.ts'))
  for (const value of ['123456', '000001', 'AbC123', '353938205192374', 123456]) check('有效串号 ' + value, () => assert.equal(validation.deviceImeiError(value), ''))
  for (const value of ['', null, '12345', ' 123456', '123456 ', '123 456', '123456\n', 'ABC-123', 'ABC_123', '１２３４５６', 'ABC#123', '1234567890123456', [], true, Infinity]) check('非法串号 ' + JSON.stringify(value), () => assert.ok(validation.deviceImeiError(value)))

  let ensureCount = 0
  const row = { imei: '', model: '测试机型', initial_price: 0, category_id: 1 }
  const list = setup('components/device-entry/DeviceEntryList.vue', { devices: [row], orderId: '', ensureOrder: async () => { ensureCount++; return 90 }, autoAppend: false })
  await list.saveDeviceRow(row, 0)
  check('空IMEI不创建草稿、不提交设备，并标记错误行', () => { assert.equal(ensureCount, 0); assert.equal(calls.length, 0); assert.equal(row.imei_touched, true); assert.match(messages.at(-1), /第 1 台设备.*填写/) })
  row.imei = '0000aB'
  await list.saveDeviceRow(row, 0)
  check('有效串号保存且不丢前导零、大小写', () => { assert.equal(ensureCount, 1); assert.equal(calls.at(-1)[2].imei, '0000aB'); assert.equal(row.saved, true) })
  calls.length = 0
  row.imei = '0000aB '
  await list.updateDeviceRow(row)
  check('带空格不能保存修改，不能通过trim偷偷修正', () => { assert.equal(calls.length, 0); assert.match(messages.at(-1), /空格/) })
  row.imei = ''
  await list.updateDeviceRow(row)
  check('已保存行清空后仍不能保存', () => assert.equal(calls.length, 0))
  const card = setup('components/device-entry/DeviceEntryCard.vue', { device: row, index: 0, canRemove: false })
  check('输入框显示必填错误', () => assert.equal(card.imeiError.value, '请填写 IMEI'))
  row.imei = 'AbC123'
  // 组件测试传入普通对象；重新计算 setup 以验证修正后的提示。
  check('修正后错误消失', () => assert.equal(setup('components/device-entry/DeviceEntryCard.vue', { device: row, index: 0 }).imeiError.value, ''))

  emitted.length = 0
  card.handleCommand('remove')
  check('仅剩一行时更多菜单不能删除设备', () => assert.equal(emitted.length, 0))
  const removable = setup('components/device-entry/DeviceEntryCard.vue', { device: row, index: 0, canRemove: true })
  removable.handleCommand('remove')
  check('更多菜单删除仍走原有删除事件', () => assert.equal(emitted.at(-1)[0], 'remove'))
  emitted.length = 0
  row.saving = true
  removable.handleCommand('remove')
  check('保存中不允许通过更多菜单删除设备', () => assert.equal(emitted.length, 0))
  row.saving = false
  const cardSource = fs.readFileSync(path.join(base, 'components/device-entry/DeviceEntryCard.vue'), 'utf8')
  const listSource = fs.readFileSync(path.join(base, 'components/device-entry/DeviceEntryList.vue'), 'utf8')
  check('更多菜单使用完整 hover 生命周期，不用混合触发导致移入即关闭', () => {
    assert.match(cardSource, /<el-dropdown trigger="hover" :show-timeout="0" :hide-timeout="250" placement="bottom-end"/)
    assert.doesNotMatch(cardSource, /:trigger="\['hover', 'click'\]"/)
  })
  check('表头与行共用列宽且报价员是独立列', () => {
    assert.match(listSource, /--device-entry-columns: 24px minmax\(190px/)
    assert.match(cardSource, /grid-template-columns: var\(--device-entry-columns/)
    assert.ok(cardSource.indexOf('class="device-card__quote"') < cardSource.indexOf('class="device-card__ops"'))
    assert.match(listSource, /device-entry__table-scroll/)
    assert.match(listSource, /el-tooltip :content="row.model/)
  })

  const add = setup('views/recycle_order/components/AddOrderDialog.vue', { visible: true })
  add.draftOrder.value = { id: 90, order_no: 'RQA' }
  add.form.value.devices = [{ ...row, imei: '', id: 1, saved: true, dirty: true }]
  await add.handleConfirm()
  check('代下单最终签收拦截保存后被清空的串号', () => { assert.equal(calls.length, 0); assert.match(messages.at(-1), /请填写 IMEI/) })
  add.form.value.devices = [{ ...row, imei: 'AbC123', id: 1, saved: true }, { imei: '', model: '', saved: false }]
  await add.handleConfirm()
  check('代下单签收忽略自动追加的空白占位行', () => { const call = calls.find(item => item[0] === 'updateRecycleOrder'); assert.ok(call); assert.equal(call[2].devices.length, 1); assert.equal(call[2].devices[0].imei, 'AbC123') })

  const confirm = setup('views/recycle_order/components/DeviceConfirmDialog.vue', { visible: true, orderId: 90, deviceList: [] })
  emitted.length = 0
  confirm.rows.value = [{ ...row, imei: 'ABCDE', id: 1, saved: true }]
  await confirm.handleConfirm()
  check('签收弹窗不足6位不发送confirm', () => assert.equal(emitted.length, 0))
  confirm.rows.value = [{ ...row, imei: '000001', id: 1, saved: true }, { imei: '', model: '', saved: false }]
  await confirm.handleConfirm()
  check('签收弹窗有效串号可提交，空白占位不算设备', () => { assert.equal(emitted.length, 1); assert.equal(emitted[0][1].devices.length, 1); assert.equal(emitted[0][1].devices[0].imei, '000001') })
  const reflected = confirm.mapDeviceToRow({ id: 20, imei: 'ABC456', model: '报价测试', initial_price: '6000.00',
    category_id: 1, customer_quote: { uid: 11, name: '张报价' }, info: { customer_quote: { uid: 11, name: '张报价' }, sign_summary: { battery: 98 } } })
  check('签收回显客户的预估价和报价员且不混入质检摘要', () => {
    assert.equal(reflected.initial_price, 6000); assert.equal(reflected.initial_price_uid, 11)
    assert.equal(reflected.initial_price_name, '张报价'); assert.equal(reflected.summary_values.customer_quote, undefined)
  })
  const quoteCard = setup('components/device-entry/DeviceEntryCard.vue', { device: reflected, index: 0,
    quoteUsers: [{ uid: 11, name: '张报价' }, { uid: 12, name: '李报价' }], assignToQuoteUser: true })
  quoteCard.updateQuoteUser(12)
  check('管理员改选报价员标记待保存', () => { assert.equal(reflected.initial_price_uid, 12); assert.equal(reflected.dirty, true) })
  const quoteList = setup('components/device-entry/DeviceEntryList.vue', { devices: [reflected], orderId: 90, autoAppend: false })
  await quoteList.updateDeviceRow(reflected)
  check('保存修改携带报价员ID，不提交可信姓名或直接写price_uid', () => {
    assert.equal(calls.at(-1)[2].initial_price_uid, 12); assert.equal(calls.at(-1)[2].price_uid, undefined)
    assert.equal(calls.at(-1)[2].initial_price_name, undefined)
  })
  quoteCard.updateQuoteUser(undefined)
  await quoteList.updateDeviceRow(reflected)
  check('后台报价员可清空并正常保存', () => assert.equal(calls.at(-1)[2].initial_price_uid, 0))
  quoteCard.updateQuoteUser(11)
  confirm.rows.value = [reflected]
  emitted.length = 0
  await confirm.handleConfirm()
  check('最终签收载荷保留报价员', () => assert.equal(emitted[0][1].devices[0].initial_price_uid, 11))
  const actions = evaluate(path.join(base, 'hooks/useRecycleDeviceActions.ts')).useRecycleDeviceActions({
    list: vue.ref([]), pagination: vue.ref({ page: 1 }), getList: async () => {},
    checkDeviceLogVisible: vue.ref(true), priceDeviceLogVisible: vue.ref(false)
  })
  await actions.submitDeviceCheck({ id: 20, check_result_seller: '正常', next_assignee_uid: 12 })
  check('完成质检不丢失业务员指定的下一负责人', () => assert.equal(calls.at(-1)[2].next_assignee_uid, 12))
  console.log('完成：' + checks + ' 项检查；4 个实际 Vue 组件编译及保存方法执行通过，接口均为隔离 spy。')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
