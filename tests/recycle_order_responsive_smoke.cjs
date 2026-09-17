// 回收订单 UI 定向编译和布局约束检查；不发布、不修改数据库。
const fs = require('node:fs')
const path = require('node:path')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '..')
const compiler = require(path.join(root, 'admin/node_modules/@vue/compiler-sfc'))
const ts = require(path.join(root, 'admin/node_modules/typescript'))
const files = ['list.vue', 'components/RecycleOrderDesktopTable.vue', 'components/RecycleOrderMobileCards.vue', 'components/RecycleOrderDeviceList.vue']
const sources = {}

for (const file of files) {
  const relative = 'views/recycle_order/' + file
  const filename = path.join(root, 'admin/src/addon/hsx_recycle', relative)
  const source = fs.readFileSync(filename, 'utf8')
  sources[file] = source
  assert.equal(source, fs.readFileSync(path.join(root, 'niucloud/addon/hsx_recycle/admin', relative), 'utf8'), '插件包和开发源码保持一致：' + file)
  const { descriptor, errors } = compiler.parse(source, { filename })
  assert.deepEqual(errors, [])
  const script = compiler.compileScript(descriptor, { id: 'recycle-responsive' })
  const diagnostics = ts.transpileModule(script.content, {
    fileName: filename + '.ts', reportDiagnostics: true,
    compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ESNext }
  }).diagnostics || []
  assert.equal(diagnostics.length, 0, diagnostics.map(item => ts.flattenDiagnosticMessageText(item.messageText, '\n')).join('\n'))
  const template = compiler.compileTemplate({
    source: descriptor.template.content, filename, id: 'recycle-responsive',
    compilerOptions: { bindingMetadata: script.bindings, expressionPlugins: ['typescript'] }
  })
  assert.deepEqual(template.errors, [])
  for (const style of descriptor.styles) {
    const result = compiler.compileStyle({ source: style.content, filename, id: 'data-v-recycle-responsive', preprocessLang: style.lang })
    assert.deepEqual(result.errors, [])
  }
  console.log('PASS 编译及源码同步：' + file)
}

const page = sources['list.vue']
assert.match(page, /useResizeObserver\(orderTableRegion/)
assert.match(page, /contentRect\.width < 1160/)
assert.match(page, /contentRect\.width < 600/)
assert.match(page, /v-if="!isPhoneList"/)
assert.match(page, /:compact="isCompactList"/)
assert.doesNotMatch(page, /height: clamp\(320px, 52vh/)
assert.doesNotMatch(page, /mobileExpandedOrders/)
assert.match(page, /setExpandedRows\(exists/)
const desktop = sources['components/RecycleOrderDesktopTable.vue']
assert.equal((desktop.match(/<el-table\s/g) || []).length, 1, '展开区不重复引入固定宽列的 Element 表格')
assert.doesNotMatch(desktop, /height="100%"|trigger="hover"/)
assert.match(desktop, /v-if="!props.compact" label="设备进度"/)
assert.match(desktop, /v-if="!props.compact" label="时间"/)
assert.match(desktop, /getOrderTimes\(row\)/)
for (const view of ['components/RecycleOrderDesktopTable.vue', 'components/RecycleOrderMobileCards.vue']) {
  assert.match(sources[view], /<RecycleOrderDeviceList/)
  assert.match(sources[view], /handleDeviceSelectionChange\(devices, row.id\)/)
  assert.match(sources[view], /batchRecycleDevices\(row.id\)/)
  assert.match(sources[view], /hasActionPerm/)
}
const devices = sources['components/RecycleOrderDeviceList.vue']
assert.match(devices, /<table class="order-devices__table"/)
assert.match(devices, /table-layout: fixed/)
assert.match(devices, /min-width: 620px/)
assert.match(devices, /overflow-x: auto/)
assert.doesNotMatch(devices, /repeat\(auto-fit|box-shadow/)
assert.match(devices, /compact: true/)
assert.match(devices, /v-if="!props.compact" scope="col">IMEI \/ SN/)
assert.match(devices, /overflow-wrap: anywhere/)
assert.match(devices, /min-height: 36px/)
assert.match(devices, /device\.imei/)
assert.match(devices, /device\.user_sn/)
assert.match(devices, /confirm_status_name/)
assert.match(devices, /pay_status_name/)
assert.match(devices, /<th scope="col">当前进度/)
assert.match(devices, /<HsxTag :text="progress.label"/)
assert.match(devices, /trigger="click".*title="进度说明"/)
assert.doesNotMatch(devices, /order-device__settlement/)
assert.match(devices, /viewConsignment\(device\)/)
assert.match(devices, /viewDetail\(device\)/)
assert.match(devices, /action\.handler\(\)/)
console.log('PASS 响应式、展开状态、触屏操作及业务入口保留约束')

// 对真实组件 setup 的选择逻辑回归；仅使用内存夹具，不触发质检、定价、付款请求。
const vm = require('node:vm')
const vue = require(path.join(root, 'admin/node_modules/vue'))
const { descriptor } = compiler.parse(devices, { filename: 'RecycleOrderDeviceList.vue' })
const compiled = compiler.compileScript(descriptor, { id: 'recycle-device-selection' })
const js = ts.transpileModule(compiled.content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const uiSource = fs.readFileSync(path.join(root, 'admin/src/addon/hsx_recycle/hooks/useRecycleOrderUi.ts'), 'utf8')
const uiSandbox = { exports: {}, require: () => ({}) }
vm.runInNewContext(ts.transpileModule(uiSource, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText, uiSandbox)
const sandbox = { exports: {}, require: name => name === 'vue' ? vue : name.endsWith('/useRecycleOrderUi') ? uiSandbox.exports : {}, console }
vm.runInNewContext(js, sandbox)
const first = { id: 11, model: '测试设备 A', imei: 'TEST-000000000001', status: 4 }
const second = { id: 12, model: '测试设备 B', imei: 'TEST-000000000002', status: 4 }
const props = vue.reactive({
  order: { id: 1, status: '4', devices: [first, second] }, selectedDevices: [],
  formatPrice: value => String(value), getDevicePrimaryAction: () => null,
  getDeviceMoreActions: () => [], viewConsignment: () => {}, viewDetail: () => {}
})
const emitted = []
const state = sandbox.exports.default.setup(props, {
  expose() {}, emit(name, value) { emitted.push({ name, value }); if (name === 'selection-change') props.selectedDevices = value }
})
assert.equal(state.canBatch.value, true)
state.selectDevice(first, true)
assert.equal(state.partlySelected.value, true)
assert.equal(state.selectedIds.value.size, 1)
state.selectDevice({ ...first, id: '11' }, true)
assert.equal(state.selectedIds.value.size, 1, '数字/字符串 ID 不重复勾选')
state.selectAll(true)
assert.equal(state.allSelected.value, true)
assert.equal(props.selectedDevices.length, 2)
assert.notEqual(props.selectedDevices, props.order.devices, '全选不直接修改订单设备数组')
state.selectDevice({ ...first, id: '11' }, false)
assert.equal(state.partlySelected.value, true)
assert.equal(state.selectedIds.value.has('11'), false)
state.selectAll(false)
assert.equal(state.selectedIds.value.size, 0)
assert.equal(props.order.devices.length, 2)
props.order.status = '5'
assert.equal(state.canBatch.value, false, '保留原批量确认的订单状态门槛')
props.order.devices = []
assert.equal(state.allSelected.value, false)
assert.equal(state.deviceRows.value.length, 0)
assert.equal(emitted.every(event => event.name === 'selection-change'), true)
console.log('PASS 15 项设备选择、类型兼容、空数据及批量操作状态回归')

// 展示只归纳当前待办：不改变原字段，也不把部分打款/未知状态归为未付款。
const progressCases = [
  [{ status: 5, confirm_status: 1, pay_status: 0 }, '待打款', 'warning'],
  [{ status: '5', confirm_status: '1', pay_status: '0' }, '待打款', 'warning'],
  [{ status: 5, confirm_status: 1, pay_status: 2 }, '待补款', 'warning'],
  [{ status: 5, confirm_status: 1, pay_status: '2' }, '待补款', 'warning'],
  [{ status: 5, confirm_status: 1, pay_status: 1 }, '已打款', 'success'],
  [{ status: 5, confirm_status: 1 }, '打款待核对', 'warning'],
  [{ status: 5, confirm_status: 1, pay_status: null }, '打款待核对', 'warning'],
  [{ status: 5, confirm_status: 1, pay_status: '' }, '打款待核对', 'warning'],
  [{ status: 5, confirm_status: 1, pay_status: 9 }, '打款待核对', 'warning'],
  [{ status: 4, confirm_status: 0, pay_status: 0 }, '待客户确认', 'warning'],
  [{ status: 4, confirm_status: 1, pay_status: 0 }, '待打款', 'warning'],
  [{ status: 4, confirm_status: 1, pay_status: 2 }, '待补款', 'warning'],
  [{ status: 4, confirm_status: 2, pay_status: 0 }, '客户已拒绝', 'danger'],
  [{ status: 4, pay_status: 0 }, '确认待核对', 'warning'],
  [{ status: 3, pay_status: 0 }, '待定价', 'primary'],
  [{ status: 6, confirm_status: 2, pay_status: 1 }, '已退回', 'danger'],
  [{ status: 9, confirm_status: 1, pay_status: 2 }, '已转代卖', 'neutral'],
  [{ status: 5, confirm_status: 1, pay_status: 0, dispose_type: 'consign' }, '已转代卖', 'neutral'],
]
for (const [device, label, tone] of progressCases) {
  const before = JSON.stringify(device)
  const result = state.getDeviceProgress(device)
  assert.equal(result.label, label, '当前进度：' + before)
  assert.equal(result.tone, tone, '状态颜色：' + before)
  assert.equal(JSON.stringify(device), before, '展示不修改设备数据')
}
const pending = state.getDeviceProgress({ status: 5, confirm_status: 1, pay_status: 0 })
assert.equal(JSON.stringify(pending.details.map(item => item.value)), JSON.stringify(['已回收', '已确认', '未打款']), '完整三维明细仅在弹层中保留')
assert.equal(state.getDeviceProgress({ status: 5, confirm_status: 1, pay_status: 2, pay_status_name: '未打款' }).details.at(-1).value, '部分打款', '状态值优先，避免部分付款被旧文案遮蔽')
assert.equal(state.getDeviceProgress({ status: 1, pay_status: 0 }).details.length, 1, '未进入打款环节不堆叠付款信息')
console.log('PASS 18 项进度场景及 3 项明细、不修改原数据检查')
