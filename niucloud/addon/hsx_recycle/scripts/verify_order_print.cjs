// 编译实际 Vue/TS 源码并校验 A4 白名单输出；不访问业务接口。
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const admin = path.join(root, 'admin')
const requireAdmin = require('node:module').createRequire(path.join(admin, 'package.json'))
const { transformSync } = require(path.join(admin, 'node_modules/esbuild'))
const { parse, compileScript, compileTemplate, compileStyle } = require(path.join(admin, 'node_modules/@vue/compiler-sfc'))
const base = path.join(admin, 'src/addon/hsx_recycle')
let checks = 0
function check(label, callback) { callback(); checks++; console.log('PASS', label) }
function moduleFrom(file, requireStub = requireAdmin) {
  const code = transformSync(fs.readFileSync(file, 'utf8'), { loader: 'ts', format: 'cjs', target: 'es2020' }).code
  const context = { module: { exports: {} }, require: requireStub, window: global.testWindow }
  vm.runInNewContext(code, context)
  return context.module.exports
}
const utils = moduleFrom(path.join(base, 'utils/recycleOrderPrint.ts'))
async function main() {
const devices = Array.from({ length: 45 }, (_, i) => ({
  id: i + 1, model: i ? '苹果 iPhone 14 Pro Max 256GB' : '<img src=x onerror=alert(1)>',
  imei: String(353938205192300n + BigInt(i)), sn: '0000SN',
  status: i === 1 ? 6 : 5, confirm_status: i === 1 ? 2 : 1, pay_status: i === 1 ? 0 : 1,
  final_price: '1200.50', check_result: '禁止打印的长质检内容', info: { raw: '原始备份信息' },
  print_summary: { capacity: '256GB', color: '银色', battery: '75', warranty_info: '2027-09-17', package_type: '原盒全套', battery_cycle: '212' },
  print_device_url: `https://print-test.invalid/adminapp/addon/hsx_recycle/pages/check/scan?device_id=${i + 1}&site_id=100005&mode=query`,
}))
const prepared = await utils.prepareRecycleOrderPrint({
  id: 1, order_no: 'R202609280001', status_name: '已完成', customer_name: '打印验收客户',
  customer_phone: '13800000000', create_at: '2026-09-28 08:09:10', sign_at: 1790558400,
  count: 45, devices, remark: '内部备注不打印', pay_account: '秘密账户',
})
const html = utils.buildRecycleOrderPrintHtml(prepared, new Date('2026-09-28T10:00:00+08:00'))
check('A4 纵向每页20台，重复订单头和分页', () => { assert.match(html, /size: A4 portrait/); assert.equal((html.match(/class="print-page"/g) || []).length, 3); assert.equal((html.match(/订单号：R202609280001/g) || []).length, 3); assert.ok(html.includes('本页 20 台')); assert.ok(html.includes('第 3 / 3 页')) })
check('每台独立单元格，不按当前页截断', () => assert.equal((html.match(/class="row-number"/g) || []).length, 45))
check('20台分页边界不丢设备、不多空白页', () => {
  for (const count of [0, 1, 10, 11, 20, 21, 40, 41, 45]) {
    const sample = utils.buildRecycleOrderPrintHtml({ devices: prepared.devices.slice(0, count) })
    assert.equal((sample.match(/class="print-page"/g) || []).length, Math.max(1, Math.ceil(count / 20)))
    const numbers = [...sample.matchAll(/class="row-number">(\d+)\./g)].map(match => Number(match[1])).sort((a, b) => a - b)
    assert.deepEqual(numbers, Array.from({ length: count }, (_, i) => i + 1))
  }
})
check('完整 IMEI / SN 保留', () => { assert.ok(html.includes(devices[0].imei)); assert.ok(html.includes('0000SN')) })
check('不打印长质检、原始信息、备注、账户', () => ['禁止打印的长质检内容', '原始备份信息', '内部备注不打印', '秘密账户'].forEach(text => assert.ok(!html.includes(text))))
check('用户内容不能作为 HTML 执行', () => { assert.ok(!html.includes('<img src=x')); assert.ok(html.includes('&lt;img')) })
check('报价保留小数', () => assert.ok(html.includes('1200.50')))
check('未填写或未定价的金额格留空', () => {
  for (const final_price of [undefined, null, '', 0, '0.00', '不是数字']) {
    const sample = utils.buildRecycleOrderPrintHtml({ devices: [{ final_price }] })
    assert.ok(sample.includes('<span class="amount"></span>'))
    assert.ok(!sample.includes('待定价'))
  }
})
check('拒绝不标记为实物签收', () => { assert.ok(html.includes('拒绝 / 退回处理')); assert.ok(html.includes('仍需按退货流程交还')) })
check('空时间与字符串时间', () => { assert.equal(utils.formatPrintTime(0), '—'); assert.equal(utils.formatPrintTime('2026-09-28 08:09:10'), '2026-09-28 08:09:10') })
check('秒与毫秒时间戳一致', () => assert.equal(utils.formatPrintTime(1790558400), utils.formatPrintTime(1790558400000)))
check('空设备提示', () => assert.ok(utils.buildRecycleOrderPrintHtml({}).includes('尚未录入设备')))
check('打印容量、颜色、电池、保修、包装和循环', () => ['256GB', '银色', '75%', '2027-09-17', '原盒全套', '212次'].forEach(value => assert.ok(html.includes(value))))
check('每台设备各有二维码，使用本地 PNG 数据', () => { assert.equal((html.match(/class="device-qr"/g) || []).length, 45); assert.equal((html.match(/src="data:image\/png;base64,/g) || []).length, 45) })
check('不同设备二维码不同且不改输入对象', () => { assert.notEqual(prepared.devices[0].print_qrcode, prepared.devices[1].print_qrcode); assert.equal(devices[0].print_qrcode, undefined) })
check('缺失字段与未解析编号不伪造内容', () => {
  const h = utils.buildRecycleOrderPrintHtml({ devices: [{ print_summary: { capacity: '2', color: '', battery: '0', package_type: '1' } }] })
  assert.ok(h.includes('待核对')); assert.ok(h.includes('未录入')); assert.ok(h.includes('0%'))
})
check('摘要文本转义且百分号不重复', () => {
  const h = utils.buildRecycleOrderPrintHtml({ devices: [{ print_summary: { color: '<script>alert(1)</script>', battery: '85%' } }] })
  assert.ok(h.includes('&lt;script&gt;')); assert.ok(!h.includes('<script>')); assert.ok(!h.includes('85%%'))
})
await assert.rejects(utils.prepareRecycleOrderPrint({ devices: [{ id: 1 }] }), /更新回收插件后端/)
checks++; console.log('PASS 后端未更新时明确提示，不静默打印缺失数据')
await assert.rejects(utils.prepareRecycleOrderPrint({ devices: [{ print_summary: {}, print_device_url: 'javascript:alert(1)' }] }), /二维码链接/)
checks++; console.log('PASS 拒绝无效设备链接')
for (const relative of ['views/recycle_order/list.vue', 'views/recycle_order/components/RecycleOrderDesktopTable.vue', 'views/recycle_order/components/RecycleOrderMobileCards.vue', 'views/device_export/list.vue']) {
  check('Vue 编译 ' + relative, () => {
    const filename = path.join(base, relative)
    const { descriptor, errors } = parse(fs.readFileSync(filename, 'utf8'), { filename })
    assert.equal(errors.length, 0)
    const script = compileScript(descriptor, { id: 'qa' })
    transformSync(script.content, { loader: 'ts', target: 'es2020' })
    const template = compileTemplate({ source: descriptor.template.content, filename, id: 'qa', compilerOptions: { bindingMetadata: script.bindings } })
    assert.deepEqual(template.errors, [])
    descriptor.styles.forEach(style => assert.deepEqual(compileStyle({ source: style.content, filename, id: 'qa', scoped: style.scoped, preprocessLang: style.lang }).errors, []))
  })
}
check('时间列加宽且不折行', () => {
  const source = fs.readFileSync(path.join(base, 'views/recycle_order/components/RecycleOrderDesktopTable.vue'), 'utf8')
  assert.match(source, /label="时间" width="220"/)
  assert.match(source, /\.order-time-value \{ white-space: nowrap/)
})
check('每次打开导出默认不附带条码', () => {
  const source = fs.readFileSync(path.join(base, 'views/device_export/list.vue'), 'utf8')
  assert.match(source, /const includeBarcode = ref\(false\)/)
  assert.match(source, /const exportEvent = \(\) => \{\s+includeBarcode.value = false/)
  assert.match(source, /include_barcode: includeBarcode.value \? 1 : 0/)
})
const dir = process.argv[2]
if (dir) {
  fs.mkdirSync(dir, { recursive: true })
  fs.writeFileSync(path.join(dir, 'print-preview.html'), html)
  fs.writeFileSync(path.join(dir, 'print-one-page.html'), utils.buildRecycleOrderPrintHtml({
    id: 1, order_no: 'R202609280002', customer_name: '打印验收客户', customer_phone: '13800000000',
    status_name: '已完成', create_at: '2026-09-28 08:09:10', sign_at: '2026-09-28 09:09:10',
    count: 3, devices: prepared.devices.slice(1, 4),
  }))
  fs.writeFileSync(path.join(dir, 'device-qr.png'), Buffer.from(prepared.devices[0].print_qrcode.split(',')[1], 'base64'))
}

async function testHook() {
  let requestCount = 0, printCount = 0, error = '', resolveRequest
  const pending = () => new Promise(resolve => { resolveRequest = resolve })
  let request = pending
  let target
  function newTarget() {
    return {
      closed: false, opener: {}, close() { this.closed = true }, focus() {}, print() { printCount++ },
      setTimeout(fn) { fn() },
      document: { title: '', body: { appendChild() {} }, createElement: () => ({ style: {}, textContent: '' }),
        open() {}, close() {}, write(value) { this.html = value }, fonts: { ready: Promise.resolve() }, images: [],
        getElementById: () => ({ addEventListener() {} }) },
    }
  }
  global.testWindow = { open: () => { target = newTarget(); return target } }
  const { useRecycleOrderPrint } = moduleFrom(path.join(base, 'hooks/useRecycleOrderPrint.ts'), name => {
    if (name === 'vue') return { ref: value => ({ value }), readonly: value => value }
    if (name.includes('/api/')) return { getRecycleOrderPrintInfo: () => { requestCount++; return request() } }
    return utils
  })
  const hook = useRecycleOrderPrint({ onError: message => { error = message } })
  let run = hook.printOrder({ id: 1 })
  check('用户点击时同步打开等待页', () => { assert.ok(target); assert.equal(target.document.title, '正在准备回收单'); assert.equal(target.opener, null) })
  await hook.printOrder({ id: 2 })
  check('加载中不重复提交打印请求', () => assert.equal(requestCount, 1))
  resolveRequest({ data: { id: 1, devices: devices.slice(0, 3), order_no: 'RQA' } })
  await run
  check('完整数据就绪后调用浏览器打印', () => { assert.equal(printCount, 1); assert.equal(hook.printingOrderId.value, null) })
  global.testWindow.open = () => null
  await hook.printOrder({ id: 1 })
  check('弹窗被拦截有明确提示且不请求接口', () => { assert.match(error, /允许本站弹窗/); assert.equal(requestCount, 1) })
  global.testWindow.open = () => { target = newTarget(); return target }
  request = () => Promise.reject({ msg: '当前账号没有查看权限' })
  await hook.printOrder({ id: 1 })
  check('接口失败关闭空窗口并提示原因', () => { assert.ok(target.closed); assert.equal(error, '当前账号没有查看权限'); assert.equal(hook.printingOrderId.value, null) })
  request = pending
  run = hook.printOrder({ id: 1 })
  target.closed = true
  resolveRequest({ data: { id: 1, devices: [] } })
  await run
  check('用户关闭等待页后不再唤起打印', () => assert.equal(printCount, 1))
  console.log('PASS', checks, 'checks')
}
await testHook()
}
main().catch(error => { console.error(error); process.exitCode = 1 })
