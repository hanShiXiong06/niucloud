// 页面作用域滚动锁 / 微信编译产物检查。无网络、无业务写入、非手机真机测试。
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const req = require('node:module').createRequire(path.join(root, 'uni-app/package.json'))
const vue = req('vue'), { transformSync } = req('esbuild')
const base = path.join(root, 'niucloud/addon/hsx_recycle/uni-app')
let checks = 0, current
const check = (value, message) => { assert.ok(value, message); checks++ }
const api = { ...vue,
  provide: (key, value) => current.context.set(key, value),
  inject: (key, fallback) => current.context.get(key) || fallback,
  onUnmounted: callback => current.unmount.push(callback)
}
const lifecycle = { onShow: callback => current.show.push(callback), onHide: callback => current.hide.push(callback) }
const moduleObject = { exports: {} }
vm.runInNewContext(transformSync(fs.readFileSync(path.join(base, 'hooks/useRecyclePopupScroll.ts'), 'utf8'), { loader: 'ts', format: 'cjs' }).code, {
  module: moduleObject, exports: moduleObject.exports,
  require: name => { if (name === 'vue') return api; if (name === '@dcloudio/uni-app') return lifecycle; throw Error(name) }
})
const { useRecyclePopupPage, useRecyclePopupLock } = moduleObject.exports
const make = (context = new Map()) => ({ context, show: [], hide: [], unmount: [], scope: vue.effectScope() })
function page() { const owner = make(); current = owner; owner.state = owner.scope.run(useRecyclePopupPage); return owner }
function popup(owner, open = false) {
  const child = make(owner.context); child.open = vue.ref(open); current = child
  child.scope.run(() => useRecyclePopupLock(() => child.open.value))
  child.destroy = () => { child.scope.stop(); child.unmount.forEach(fn => fn()) }
  return child
}
const first = page(), second = page(), a = popup(first), b = popup(first), c = popup(second)
check(first.state.popupPageStyle.value === '', 'closed popup does not lock')
a.open.value = true
check(first.state.popupScrollLocked.value, 'opening one popup locks page')
check(first.state.popupPageStyle.value.includes('overflow:hidden'), 'native page-meta receives overflow hidden')
check(!second.state.popupScrollLocked.value, 'different page unaffected')
b.open.value = true; a.open.value = false
check(first.state.popupScrollLocked.value, 'closing one of two popups retains lock')
b.open.value = false
check(!first.state.popupScrollLocked.value, 'last popup closed restores page')
a.open.value = true; c.open.value = true
a.destroy()
check(!first.state.popupScrollLocked.value, 'unmounted popup releases its own lock')
check(second.state.popupScrollLocked.value, 'unmount cannot release another page lock')
second.hide.forEach(fn => fn())
check(!second.state.popupScrollLocked.value, 'page hidden releases page-style')
second.show.forEach(fn => fn())
check(second.state.popupScrollLocked.value, 'returning page with open popup restores lock')
c.destroy()
check(!second.state.popupScrollLocked.value, 'child removal releases lock even without close event')
first.state.setPopupOpen('native-editor', true)
first.state.setPopupOpen('native-editor', true)
first.state.setPopupOpen('native-editor', false)
check(!first.state.popupScrollLocked.value, 'duplicate open event is idempotent')
first.state.setPopupOpen('editor', true)
first.unmount.forEach(fn => fn())
check(!first.state.popupScrollLocked.value, 'page teardown clears remaining locks')
first.scope.stop(); second.scope.stop(); b.destroy()

const files = ['hooks/useRecyclePopupScroll.ts', 'pages/order/order.vue', 'pages/order/detail.vue', 'pages/order/list.vue', 'pages/payment/index.vue', 'pages/payment/area-select.vue', 'pages/return_order/detail.vue', 'pages/order/components/OrderTaskPopup.vue', 'pages/order/components/PickupTimePicker.vue', 'pages/order/components/DeviceDetailCard.vue', 'pages/order/components/DeviceListManager.vue', 'pages/order/components/DeviceInputModal.vue']
for (const file of files) check(fs.readFileSync(path.join(base, file), 'utf8') === fs.readFileSync(path.join(root, 'uni-app/src/addon/hsx_recycle', file), 'utf8'), 'runtime/package mirror: ' + file)
const sfc = req('@vue/compiler-sfc')
for (const file of files.filter(file => file.endsWith('.vue'))) {
  const filename = path.join(base, file), { descriptor, errors } = sfc.parse(fs.readFileSync(filename, 'utf8'), { filename })
  check(errors.length === 0, 'SFC syntax: ' + file)
  const script = sfc.compileScript(descriptor, { id: file })
  const mini = req('@dcloudio/uni-mp-compiler').compile(descriptor.template.content, { filename, isTS: true, expressionPlugins: ['typescript'], bindingMetadata: script.bindings })
  check(Boolean(mini.code), 'mini template: ' + file)
}
const source = file => fs.readFileSync(path.join(base, file), 'utf8')
check(source('pages/order/list.vue').includes(':scrollable="!popupScrollLocked"'), 'order list background scroll-view explicitly locked')
check(source('pages/order/list.vue').includes(':refresher-enabled="!popupScrollLocked"'), 'pull refresh disabled while popup open')
check(source('pages/order/components/OrderTaskPopup.vue').includes('scroll-y'), 'popup retains independent vertical scroll')
check(!source('pages/order/components/OrderTaskPopup.vue').includes('class="order-task-popup" :style="{ height }" @touchmove.stop.prevent'), 'do not prevent default for entire scrollable popup')
check(source('pages/order/order.vue').includes('position: sticky'), 'delivery tabs sticky')
check(source('pages/order/order.vue').includes('getRecycleNavbarMetrics().navbarHeightPx'), 'sticky offset uses actual capsule/status bar')
for (const name of ['DeviceInputModal', 'DeviceListManager']) check(source('pages/order/components/' + name + '.vue').includes('#primary') && source('pages/order/components/' + name + '.vue').includes('#secondary'), name + ' uses native 1:2 footer columns')
check(source('pages/order/detail.vue').includes('class="detail-content"'), 'detail retains card gutters')

// 编译后必须是页面的首节点；自动 layout 包裹内的 page-meta 在小程序中不符合要求。
const build = process.env.MP_BUILD_DIR || '/tmp/hsx-recycle-ui-mp'
for (const file of ['order/order', 'order/detail', 'order/list', 'return_order/detail', 'payment/index']) {
  const wxml = fs.readFileSync(path.join(build, 'addon/hsx_recycle/pages', file + '.wxml'), 'utf8')
  check(wxml.startsWith('<page-meta '), 'compiled page-meta is first native node: ' + file)
  check(wxml.includes('<layout-default-uni'), 'default application layout preserved: ' + file)
}
const wxml = fs.readFileSync(path.join(build, 'addon/hsx_recycle/pages/order/components/OrderTaskPopup.wxml'), 'utf8')
check(wxml.includes('catchtouchmove='), 'mini touch event propagation blocked')
check(/<scroll-view\b[^>]*\sscroll-y(?:\s|=|>)/.test(wxml), 'mini popup scroll-view still enabled')
console.log(`PASS ${checks} popup lifecycle, mini compilation, mirror and layout checks. No real-device or production actions.`)
