// Run popup state transitions with isolated API stubs, never against business data.
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const req = require('node:module').createRequire(path.join(root, 'uni-app/package.json'))
const { parse, compileScript } = req('@vue/compiler-sfc')
const { transformSync } = req('esbuild')
const vue = req('vue')
let checks = 0
function check(name, run) { run(); checks++; console.log('PASS', name) }
const plain = value => JSON.parse(JSON.stringify(value))
const flush = async () => { await Promise.resolve(); await vue.nextTick(); await Promise.resolve() }

function setup(name, props, imports = {}) {
  const file = path.join(root, 'uni-app/src/addon/hsx_recycle/pages/order/components', name + '.vue')
  const { descriptor } = parse(fs.readFileSync(file, 'utf8'), { filename: file })
  const script = compileScript(descriptor, { id: name })
  const emitted = [], messages = []
  const context = {
    module: { exports: {} }, console,
    uni: { showToast: value => messages.push(value) },
    require(name) {
      if (name === 'vue') return vue
      if (name.endsWith('.vue')) return {}
      if (Object.hasOwn(imports, name)) return imports[name]
      throw new Error('Unexpected import: ' + name)
    }
  }
  vm.runInNewContext(transformSync(script.content, { loader: 'ts', format: 'cjs', target: 'es2020' }).code, context, { filename: file })
  const scope = vue.effectScope()
  const state = scope.run(() => context.module.exports.default.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
  return { state, emitted, messages, stop: () => scope.stop() }
}

async function main() {
  const popupProps = { show: true, busy: false }
  const popup = setup('OrderTaskPopup', popupProps)
  popup.state.close()
  popupProps.busy = true
  popup.state.close()
  check('busy popup cannot emit an accidental close', () => assert.deepEqual(plain(popup.emitted), [['close']]))
  popup.stop()

  let addressResponse = { code: 0 }
  const addresses = setup('AddressSelectPopup', { show: false }, { '@/app/api/member': { getAddressList: async () => addressResponse } })
  await addresses.state.loadAddressList()
  check('address failure is distinct from an empty list', () => assert.equal(addresses.state.loadFailed.value, true))
  const address = { id: 1, name: 'Test', mobile: '13800000000', full_address: 'Test address' }
  addressResponse = { code: 1, data: [address] }
  await addresses.state.loadAddressList()
  check('address retry restores selectable records', () => {
    assert.equal(addresses.state.loadFailed.value, false)
    assert.equal(addresses.state.addressList.value.length, 1)
  })
  addresses.state.selectAddress(address)
  check('selecting an address returns it and closes the picker', () => assert.deepEqual(plain(addresses.emitted), [['select', address], ['update:show', false]]))
  addresses.stop()

  let resolveSave, writes = 0, submitted
  const save = new Promise(resolve => { resolveSave = resolve })
  const editor = setup('AddressEditPopup', { show: false }, { '@/app/api/member': { addAddress: data => { writes++; submitted = plain(data); return save } } })
  editor.state.formRef.value = { validate: async () => true }
  Object.assign(editor.state.formData, { name: 'Test', mobile: '13800000000', area: 'ProvinceCity', address: 'Street1' })
  editor.state.handleSave()
  editor.state.handleSave()
  await flush()
  editor.state.handleClose()
  check('address saving blocks repeat submission and dismissal', () => {
    assert.equal(writes, 1)
    assert.equal(editor.state.saving.value, true)
    assert.equal(editor.emitted.length, 0)
    assert.equal(submitted.full_address, 'ProvinceCityStreet1')
  })
  resolveSave({ code: 1 })
  await flush()
  check('successful save refreshes the list and closes once', () => {
    assert.deepEqual(plain(editor.emitted), [['success'], ['update:show', false]])
    assert.equal(editor.state.saving.value, false)
    assert.equal(editor.state.formData.address, '')
  })
  editor.stop()

  let expressResponse = { code: 0 }
  const express = setup('ExpressTrackingModal', { visible: false, expressNo: 'TEST1' }, { '../../../api/order': { getExpress: async () => expressResponse } })
  await express.state.loadExpressInfo()
  check('tracking failure exposes retry state', () => assert.equal(express.state.loadFailed.value, true))
  expressResponse = { code: 1, data: { list: [{ time: '2026-10-02 12:00', context: 'Collected' }] } }
  await express.state.loadExpressInfo()
  check('tracking retry restores normalized events', () => {
    assert.equal(express.state.loadFailed.value, false)
    assert.equal(express.state.trackingList.value[0].context, 'Collected')
  })
  express.stop()

  const input = setup('DeviceInputModal', { visible: true, mode: 'single', enablePricing: false })
  input.state.handleConfirm()
  check('empty device cannot be added', () => assert.equal(input.emitted.length, 0))
  input.state.singleInput.value = { user_sn: '000001', initial_price: '1200' }
  input.state.localEnablePricing.value = true
  input.state.handleConfirm()
  check('visible price toggle controls the submitted optional price', () => {
    assert.equal(input.emitted[0][0], 'confirm')
    assert.equal(input.emitted[0][1][0].initial_price, '1200')
    assert.deepEqual(plain(input.emitted[1]), ['update:visible', false])
  })
  input.stop()
  console.log(`Completed ${checks} isolated popup checks. No business requests sent.`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
