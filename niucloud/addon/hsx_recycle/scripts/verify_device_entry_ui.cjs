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
  console.log('完成：' + checks + ' 项检查；4 个实际 Vue 组件编译及保存方法执行通过，接口均为隔离 spy。')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
