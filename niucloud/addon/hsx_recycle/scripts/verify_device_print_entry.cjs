// 执行实际移动扫码页及现有登录返回 hook；接口隔离，不请求业务数据。
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const src = path.join(root, 'site-uniapp/src')
const req = require('node:module').createRequire(path.join(root, 'admin/package.json'))
const { transformSync } = req('esbuild')
const { parse, compileScript, compileTemplate, compileStyle } = req('@vue/compiler-sfc')
const vue = req('vue')
const scanPath = '/addon/hsx_recycle/pages/check/scan'
const calls = [], messages = [], storage = new Map()
let token = 'test-token', onLoad, scanSuccess, deviceResult, loginHook, checks = 0
const redirect = (options) => { calls.push(['redirect', options]); options.success?.() }
const uni = {
  getStorageSync: key => storage.get(key), setStorageSync: (key, value) => storage.set(key, value),
  removeStorageSync: key => storage.delete(key),
  showModal: options => messages.push(options), showToast: options => messages.push(options),
  scanCode: options => { scanSuccess = options.success },
}
function evaluate(file) {
  let source = fs.readFileSync(file, 'utf8')
  if (file.endsWith('.vue')) {
    const { descriptor, errors } = parse(source, { filename: file })
    assert.deepEqual(errors, [])
    const script = compileScript(descriptor, { id: 'print-entry' })
    assert.deepEqual(compileTemplate({ source: descriptor.template.content, filename: file, id: 'print-entry', compilerOptions: { bindingMetadata: script.bindings } }).errors, [])
    for (const style of descriptor.styles) assert.deepEqual(compileStyle({ source: style.content, filename: file, id: 'print-entry', preprocessLang: style.lang }).errors, [])
    source = script.content
  }
  const context = { module: { exports: {} }, console, uni, setTimeout, clearTimeout,
    require(name) {
      if (name === 'vue') return vue
      if (name === '@dcloudio/uni-app') return { onLoad: callback => { onLoad = callback }, onShow() {} }
      if (name === '@/utils/common') return { getToken: () => token, redirect }
      if (name === '@/utils/pages') return { getAppPages: () => [], getSubPackagesPages: () => [scanPath], getTabbarPages: () => [] }
      if (name === '@/hooks/useLogin') return loginHook
      if (name.endsWith('devicePrintLink')) return evaluate(path.join(src, 'addon/hsx_recycle/utils/devicePrintLink.ts'))
      if (name.endsWith('/api/order')) return {
        getDevice: async id => { calls.push(['getDevice', id]); if (deviceResult instanceof Error) throw deviceResult; return deviceResult || { data: { id: Number(id), order_id: 2527, status: 1, imei: '353938205192374' } } },
        scanSearchDevice: async params => { calls.push(['scanSearchDevice', params]); return { data: [] } },
      }
      if (name.endsWith('useRecyclePrintActions')) return { useRecyclePrintActions: () => ({
        loadManualPrintActions: () => calls.push(['loadPrintActions']), getVisiblePrintActions: () => [],
        executePrintAction: () => calls.push(['executePrintAction']),
      }) }
      return {}
    } }
  vm.runInNewContext(transformSync(source, { loader: 'ts', format: 'cjs', target: 'es2020' }).code, context, { filename: file })
  return context.module.exports
}
function reset() { calls.length = 0; messages.length = 0; token = 'test-token'; deviceResult = null; storage.clear(); storage.set('siteId', 100005) }
function setup() { return evaluate(path.join(src, 'addon/hsx_recycle/pages/check/scan.vue')).default.setup({}, { expose() {} }) }
const settle = () => new Promise(resolve => setTimeout(resolve, 15))
function check(label, fn) { fn(); checks++; console.log('PASS', label) }
async function main() {
  loginHook = evaluate(path.join(src, 'hooks/useLogin.ts'))
  const { parseDevicePrintTarget } = evaluate(path.join(src, 'addon/hsx_recycle/utils/devicePrintLink.ts'))
  const link = 'https://print-test.invalid/adminapp' + scanPath + '?device_id=3036&site_id=100005&mode=query'
  check('链接识别精确设备和站点', () => { const target = parseDevicePrintTarget(link); assert.equal(target.deviceId, '3036'); assert.equal(target.siteId, 100005) })
  for (const value of ['353938205192374', 'https://example.invalid/?device_id=3036', null, [], { device_id: '0' }, { device_id: '-1' }, { device_id: '3036\n' }, { device_id: '3036x' }, { device_id: '9007199254740992' }, { device_id: '3036', site_id: '100005\n' }]) {
    check('拒绝无效直达目标 ' + JSON.stringify(value), () => assert.equal(parseDevicePrintTarget(value), null))
  }
  reset()
  let page = setup()
  onLoad({ device_id: '3036', site_id: '100005', mode: 'query' })
  await settle()
  check('实际扫码页直接按ID读取，不模糊检索', () => { assert.equal(calls.filter(c => c[0] === 'getDevice').length, 1); assert.equal(calls.find(c => c[0] === 'getDevice')[1], '3036'); assert.equal(calls.filter(c => c[0] === 'scanSearchDevice').length, 0); assert.equal(page.deviceData.value.id, 3036) })
  check('直达码只查询，不自动质检、定价、打印或跳订单', () => { assert.equal(page.scanMode.value, 'query'); assert.equal(page.checkPopupVisible.value, false); assert.equal(page.pricePopupVisible.value, false); assert.equal(calls.filter(c => ['redirect', 'executePrintAction'].includes(c[0])).length, 0) })
  reset(); token = ''; setup()
  const params = { device_id: '3036', site_id: '100005', mode: 'query' }
  onLoad(params)
  await settle()
  check('未登录保留全部设备参数并前往登录，不请求设备', () => { assert.equal(storage.get('loginBack').url, scanPath); assert.deepEqual(storage.get('loginBack').param, params); assert.equal(calls.find(c => c[0] === 'redirect')[1].url, '/app/pages/auth/login'); assert.equal(calls.some(c => c[0] === 'getDevice'), false) })
  token = 'test-token'; calls.length = 0
  await loginHook.useLogin().handleLoginBack()
  check('现有登录hook返回同一设备及站点，成功消费返回地址', () => { assert.equal(calls[0][1].url, scanPath + '?device_id=3036&site_id=100005&mode=query'); assert.equal(storage.has('loginBack'), false) })
  reset(); storage.set('siteId', 100024); page = setup()
  onLoad(params); await settle()
  check('不同门店明确拦截，不自动切站或读取其他门店设备', () => { assert.equal(calls.some(c => c[0] === 'getDevice'), false); assert.equal(messages[0].title, '站点不匹配'); assert.equal(storage.get('siteId'), 100024); assert.equal(page.deviceData.value, null) })
  reset(); page = setup(); page.scanDevice(); scanSuccess({ result: link }); await settle()
  check('管理端扫码同样按精确设备ID打开', () => { assert.equal(calls.find(c => c[0] === 'getDevice')[1], '3036'); assert.equal(page.deviceData.value.id, 3036) })
  reset(); page = setup(); page.manualText.value = link; page.handleManualSubmit(); await settle()
  check('粘贴设备链接仍精确查询', () => assert.equal(calls.find(c => c[0] === 'getDevice')[1], '3036'))
  reset(); page = setup(); page.manualText.value = '353938205192374'; page.handleManualSubmit(); await settle()
  check('原IMEI扫描保留候选检索逻辑', () => { assert.equal(calls.find(c => c[0] === 'scanSearchDevice')[1].keyword, '353938205192374'); assert.equal(calls.some(c => c[0] === 'getDevice'), false) })
  reset(); deviceResult = new Error('无查看权限'); page = setup(); onLoad(params); await settle()
  check('接口拒绝时不展示旧设备，显示明确原因并结束loading', () => { assert.equal(page.deviceData.value, null); assert.equal(page.loading.value, false); assert.equal(messages.at(-1).title, '无查看权限') })
  console.log('完成：' + checks + ' 项检查；实际扫码 Vue 及登录返回逻辑执行通过，未访问业务接口。')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
