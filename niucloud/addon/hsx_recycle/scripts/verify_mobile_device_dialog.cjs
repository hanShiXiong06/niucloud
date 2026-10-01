// Exercise the real mobile component with isolated APIs; never submit orders.
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const req = require('node:module').createRequire(path.join(root, 'uni-app/package.json'))
const { parse, compileScript, compileTemplate, compileStyle } = req('@vue/compiler-sfc')
const { transformSync } = req('esbuild')
const relative = 'pages/order/components/DeviceListManager.vue'
const file = path.join(root, 'uni-app/src/addon/hsx_recycle', relative)
const source = fs.readFileSync(file, 'utf8')
const { descriptor, errors } = parse(source, { filename: file })
assert.deepEqual(errors, [])
const script = compileScript(descriptor, { id: 'device-dialog-qa' })
assert.deepEqual(compileTemplate({ source: descriptor.template.content, filename: file, id: 'device-dialog-qa', compilerOptions: { bindingMetadata: script.bindings } }).errors, [])
for (const style of descriptor.styles) {
  assert.deepEqual(compileStyle({ source: style.content, filename: file, id: 'device-dialog-qa', preprocessLang: style.lang, scoped: style.scoped }).errors, [])
}
let checks = 0
function check(name, fn) { fn(); checks++; console.log('PASS', name) }
const json = value => JSON.parse(JSON.stringify(value))
function deferred() {
  let resolve, reject
  const promise = new Promise((yes, no) => { resolve = yes; reject = no })
  return { promise, resolve, reject }
}
function setup() {
  const emitted = [], messages = [], timers = new Map()
  let timerId = 0, scan, unmount
  const api = {
    searchDeviceModelDictOptions: async () => ({ data: [] }),
    getDeviceModelDictChildren: async () => ({ data: [] })
  }
  const vue = { ...req('vue'), watch() {}, getCurrentInstance: () => ({ proxy: {} }), onBeforeUnmount: fn => { unmount = fn } }
  const context = {
    module: { exports: {} }, console,
    setTimeout: fn => { timers.set(++timerId, fn); return timerId },
    clearTimeout: id => timers.delete(id),
    uni: {
      showToast: value => messages.push(value), scanCode: options => { scan = options },
      createSelectorQuery() {
        return { in() { return this }, select() { return this }, boundingClientRect(fn) { fn({ height: 260 }); return this }, exec() {} }
      }
    },
    require(name) {
      if (name === 'vue') return vue
      if (name === '../../../api/order') return api
      if (name === './OrderTaskPopup.vue' || name === './OrderUiButton.vue') return {}
      throw new Error('Unexpected import: ' + name)
    }
  }
  vm.runInNewContext(transformSync(script.content, { loader: 'ts', format: 'cjs', target: 'es2020' }).code, context, { filename: file })
  const props = { devices: [], count: 1 }
  const state = context.module.exports.default.setup(props, { expose() {}, emit: (...args) => emitted.push(args) })
  return { state, api, emitted, messages, timers, props, scan: () => scan, unmount: () => unmount() }
}
async function main() {
  check('template/script/styles compile', () => assert.ok(script.content))
  const ctx = setup(), s = ctx.state
  s.handleBatchAdd()
  check('existing initial price visibility is preserved and serial help starts collapsed', () => {
    assert.equal(s.showSnHelp.value, false)
    assert.equal(s.showEstimatedPrice.value, true)
  })
  await s.confirmAdd()
  check('empty form shows both field errors without adding a device', () => {
    assert.ok(s.fieldErrors.value.model && s.fieldErrors.value.user_sn)
    assert.equal(s.errorFieldId.value, 'input-model')
    assert.equal(ctx.emitted.length, 0)
  })
  s.selectModelSuggestion({ id: 31, node_name: 'Test phone', category_path: [1, 3, 31] })
  check('model selection keeps leaf and full category mapping', () => assert.deepEqual(json(s.newDevice.value.category_path), [1, 3, 31]))
  s.newDevice.value.user_sn = '000aB1'
  s.handleUserSnInput()
  s.toggleEstimatedPrice()
  s.newDevice.value.initial_price = '1200.50'
  s.toggleEstimatedPrice()
  check('collapsing price preserves the entered value', () => assert.equal(s.newDevice.value.initial_price, '1200.50'))
  await s.confirmAdd()
  await s.confirmAdd()
  check('add keeps serial/price/mapping and only emits once', () => {
    assert.equal(ctx.emitted.length, 2)
    const device = ctx.emitted[0][1][0]
    assert.equal(device.user_sn, '000aB1')
    assert.equal(device.imei, '')
    assert.equal(device.initial_price, '1200.50')
    assert.equal(device.category_id, 31)
    assert.equal(ctx.emitted[1][1], 1)
  })
  s.handleBatchAdd()
  check('reopening starts clean', () => {
    assert.equal(s.newDevice.value.model, '')
    assert.equal(s.showEstimatedPrice.value, false)
    assert.equal(s.fieldErrors.value.model, '')
  })
  s.newDevice.value.model = 'Other phone'
  s.handleModelInput()
  check('editing model clears a previous association', () => assert.deepEqual(json(s.newDevice.value.category_path), []))
  s.scanUserSn()
  ctx.scan().success({ result: '353938205192374' })
  check('scanner preserves the full serial', () => assert.equal(s.newDevice.value.user_sn, '353938205192374'))
  s.scanUserSn()
  const staleScan = ctx.scan()
  s.closeAddDialog()
  s.handleBatchAdd()
  staleScan.success({ result: '999999999999999' })
  check('closed dialog ignores delayed scan results', () => assert.equal(s.newDevice.value.user_sn, ''))

  s.newDevice.value.model = 'Phone'
  const old = deferred(), latest = deferred()
  ctx.api.searchDeviceModelDictOptions = () => old.promise
  const first = s.searchModelSuggestions()
  ctx.api.searchDeviceModelDictOptions = () => latest.promise
  const second = s.searchModelSuggestions()
  latest.resolve({ data: [{ id: 2 }] }); await second
  old.resolve({ data: [{ id: 1 }] }); await first
  check('stale search responses cannot replace current results', () => assert.equal(s.modelSuggestions.value[0].id, 2))
  ctx.api.getDeviceModelDictChildren = async ({ pid }) => ({ data: pid === 0 ? [{ id: 3, node_name: 'Brand', has_children: 1 }] : [{ id: 31, node_name: 'Phone', has_children: 0 }] })
  await s.openModelPicker()
  await s.handleModelPickerNode(s.currentModelPickerOptions.value[0])
  check('parent selection navigates instead of adding', () => assert.equal(s.showModelPicker.value, true))
  await s.handleModelPickerNode(s.currentModelPickerOptions.value[0])
  check('leaf selection retains the picker path', () => assert.deepEqual(json(s.newDevice.value.category_path), [3, 31]))
  await s.openModelPicker()
  check('reopened picker shows root rows and root path together', () => {
    assert.equal(s.modelPickerPath.value.length, 0)
    assert.equal(s.currentModelPickerOptions.value[0].id, 3)
  })
  ctx.api.getDeviceModelDictChildren = async () => { throw new Error('offline') }
  await s.resetModelPickerPath()
  check('failed load has retry state and no stale selectable rows', () => {
    assert.equal(s.modelTreeFailed.value, true)
    assert.equal(s.currentModelPickerOptions.value.length, 0)
  })
  ctx.api.getDeviceModelDictChildren = async () => ({ data: [{ id: 4 }] })
  await s.retryModelPicker()
  check('retry restores the category list', () => assert.equal(s.currentModelPickerOptions.value[0].id, 4))
  const tree = deferred()
  ctx.api.getDeviceModelDictChildren = () => tree.promise
  const pending = s.openModelPicker()
  s.closeModelPicker()
  tree.resolve({ data: [{ id: 5 }] }); await pending
  check('closed picker ignores delayed category responses', () => assert.equal(s.currentModelPickerOptions.value.length, 0))
  ctx.unmount()
  check('unmount clears pending debounce work', () => assert.equal(ctx.timers.size, 0))
  check('addon distribution and app source stay identical', () => assert.equal(source, fs.readFileSync(path.join(root, 'niucloud/addon/hsx_recycle/uni-app', relative), 'utf8')))
  console.log(`Completed ${checks} checks. No business requests sent.`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
