// Compile the order UI and exercise courier validation without business requests.
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const req = require('node:module').createRequire(path.join(root, 'uni-app/package.json'))
const { parse, compileScript, compileTemplate, compileStyle } = req('@vue/compiler-sfc')
const { transformSync } = req('esbuild')
const sourceRoot = path.join(root, 'uni-app/src/addon/hsx_recycle')
const packageRoot = path.join(root, 'niucloud/addon/hsx_recycle/uni-app')
const uniSourceRoot = path.join(root, 'uni-app/src')
const globalScss = fs.readFileSync(path.join(uniSourceRoot, 'uni.scss'), 'utf8')
const pages = [
  'order.vue', 'list.vue', 'detail.vue',
  'components/DeliveryModeToggle.vue', 'components/DeliveryIcon.vue', 'components/DeviceListManager.vue',
  'components/ShopInfoCard.vue', 'components/ExpressInfoSection.vue',
  'components/LogisticsVehicleSection.vue', 'components/OrderNoticeBar.vue',
  'components/OrderUiButton.vue', 'components/OrderCard.vue', 'components/OrderActions.vue',
  'components/OrderDeviceList.vue', 'components/OrderListFilters.vue', 'components/OrderDetailHeader.vue',
  'components/OrderStatusBadge.vue', 'components/OrderStatusProgress.vue',
  'components/DeviceDetailCard.vue', 'components/DeviceBatchToolbar.vue', 'components/PickupStatusCard.vue',
  'components/OrderTaskPopup.vue', 'components/DeviceInputModal.vue', 'components/AddressSelectPopup.vue',
  'components/AddressEditPopup.vue', 'components/InspectionReportPopup.vue', 'components/ExpressTrackingModal.vue',
  'components/CustomerServicePopup.vue', 'components/FollowOfficialAccountPopup.vue'
]
let checks = 0
function check(name, fn) { fn(); checks++; console.log('PASS', name) }
function loadModule(file, imports = {}) {
  const context = {
    module: { exports: {} }, console, process,
    require(name) {
      if (Object.hasOwn(imports, name)) return imports[name]
      throw new Error('Unexpected import: ' + name)
    }
  }
  vm.runInNewContext(transformSync(fs.readFileSync(file, 'utf8'), { loader: 'ts', format: 'cjs', target: 'es2020' }).code, context, { filename: file })
  return context.module.exports
}
async function main() {
  for (const page of pages) {
    const relative = 'pages/order/' + page
    const file = path.join(sourceRoot, relative)
    const source = fs.readFileSync(file, 'utf8')
    check('H5 and mini templates, styles, package mirror: ' + page, () => {
      const { descriptor, errors } = parse(source, { filename: file })
      assert.deepEqual(errors, [])
      const script = compileScript(descriptor, { id: 'order-ui-qa' })
      assert.deepEqual(compileTemplate({ source: descriptor.template.content, filename: file, id: 'order-ui-qa', compilerOptions: { bindingMetadata: script.bindings } }).errors, [])
      for (const style of descriptor.styles) {
        // Match uni-app's injected uni.scss; bare SFC compilation misses @use ordering failures.
        assert.deepEqual(compileStyle({
          source: style.lang === 'scss' ? globalScss + '\n' + style.content : style.content,
          filename: file, id: 'order-ui-qa', preprocessLang: style.lang, scoped: style.scoped,
          preprocessOptions: {
            importer: url => url.startsWith('@/') ? { file: path.join(uniSourceRoot, url.slice(2)) } : null,
            silenceDeprecations: ['legacy-js-api', 'import', 'global-builtin', 'color-functions']
          }
        }).errors, [])
      }
      const mini = req('@dcloudio/uni-mp-compiler').compile(descriptor.template.content, { filename: file, isTS: true, expressionPlugins: ['typescript'], bindingMetadata: script.bindings })
      assert.ok(mini.code)
      assert.equal(source, fs.readFileSync(path.join(packageRoot, relative), 'utf8'))
    })
  }
  check('shared order stylesheet is included in the plugin package', () => {
    const relative = 'pages/order/order-ui.scss'
    assert.equal(fs.readFileSync(path.join(sourceRoot, relative), 'utf8'), fs.readFileSync(path.join(packageRoot, relative), 'utf8'))
  })

  check('status tabs keep labels and counts unwrapped in H5 and do not shrink', () => {
    const file = path.join(sourceRoot, 'pages/order/components/OrderListFilters.vue')
    const source = fs.readFileSync(file, 'utf8')
    const { descriptor } = parse(source, { filename: file })
    const { code, errors } = compileStyle({
      source: descriptor.styles[0].content, filename: file, id: 'order-filter-qa',
      preprocessLang: 'scss', preprocessOptions: { silenceDeprecations: ['legacy-js-api'] }
    })
    assert.deepEqual(errors, [])
    const css = req('postcss').parse(code)
    const declarations = selector => {
      const result = {}
      css.walkRules(rule => {
        if (rule.selectors.includes(selector)) {
          rule.walkDecls(decl => { result[decl.prop] = decl.value })
        }
      })
      return result
    }
    for (const selector of ['.status-filter__item', '.status-filter__label', '.status-filter__count']) {
      assert.equal(declarations(selector)['white-space'], 'nowrap')
      assert.equal(declarations(selector).flex, '0 0 auto')
    }
    assert.equal(declarations('.status-filter__scroll').height, '48px')
    assert.equal(declarations('.status-filter__track').height, '48px')
    assert.equal(declarations('.search-toggle').flex, '0 0 44px')
    assert.match(source, /class="status-filter__label"/)
    assert.match(source, /:show-scrollbar="false"/)
    assert.match(source, /:scroll-into-view="activeStatusId"/)
    assert.match(source, /:id="'order-status-' \+ index"/)
  })

  check('shipping choices distinguish service icons from selection and retain availability gating', () => {
    const source = fs.readFileSync(path.join(sourceRoot, 'pages/order/components/ExpressInfoSection.vue'), 'utf8')
    assert.match(source, /<DeliveryIcon :type="3"/)
    assert.match(source, /<DeliveryIcon :type="1"/)
    assert.equal((source.match(/class="toggle-item-description"/g) || []).length, 2)
    assert.match(source, /\.mode-check \{ position: absolute;/)
    assert.match(source, /v-if="!canUsePlatformDelivery" class="platform-threshold-tip"/)
    assert.match(source, /Number\(props.orderCount \|\| 0\) >= Number\(props.freeShippingMinCount \|\| 1\)/)
    assert.ok(!source.includes('platformDeliveryTag'))
  })
  check('order cards use narrower outer gutters without removing inner padding', () => {
    const source = fs.readFileSync(path.join(sourceRoot, 'pages/order/components/OrderCard.vue'), 'utf8')
    assert.match(source, /\.order-card \{ padding: 32rpx; margin: 0 12px var\(--recycle-order-section-gap, 12px\)/)
  })
  check('task popups share theme, scrolling, close and footer boundaries', () => {
    const read = name => fs.readFileSync(path.join(sourceRoot, 'pages/order/components', name + '.vue'), 'utf8')
    const shell = read('OrderTaskPopup')
    assert.ok(shell.includes('mode="bottom"'))
    assert.ok(shell.includes('if (!props.busy)'))
    assert.ok(shell.includes('env(safe-area-inset-bottom)'))
    for (const name of ['DeviceListManager', 'DeviceInputModal', 'AddressSelectPopup', 'AddressEditPopup', 'InspectionReportPopup', 'ExpressTrackingModal', 'CustomerServicePopup', 'FollowOfficialAccountPopup']) {
      assert.ok(read(name).includes('<OrderTaskPopup'), name)
    }
    assert.ok(read('AddressSelectPopup').includes('show && !showEditPopup'))
    assert.ok(read('AddressEditPopup').includes(':busy="saving"'))
    assert.ok(read('ExpressTrackingModal').includes('v-else-if="loadFailed"'))
    assert.ok(read('DeviceDetailCard').includes('props.device.check_summary'))
  })

  const theme = loadModule(path.join(sourceRoot, 'utils/theme.ts'))
  check('configured theme colors are preserved', () => {
    const vars = theme.buildRecycleThemeVars({ brand: '#00AA55', button_bg: '#334455', button_text: '#F5F6F7', card_bg: '#F0F1F2' })
    for (const token of ['--recycle-brand:#00AA55', '--recycle-button-bg:#334455', '--recycle-button-text:#F5F6F7', '--recycle-bg-card:#F0F1F2']) assert.ok(vars.includes(token))
    const page = fs.readFileSync(path.join(sourceRoot, 'pages/order/order.vue'), 'utf8')
    assert.match(page, /:style="themeVars"/)
    assert.match(page, /buildRecycleThemeVars\(orderSubmitConfig\.value\.price_detail_theme\?\.colors/)
  })
  check('delivery selector uses theme tokens without decorative gradients', () => {
    const source = fs.readFileSync(path.join(sourceRoot, 'pages/order/components/DeliveryModeToggle.vue'), 'utf8')
    assert.ok(source.includes('var(--recycle-brand)'))
    assert.ok(source.includes('var(--recycle-bg-card)'))
    assert.ok(!/linear-gradient|backdrop-filter|box-shadow/.test(source))
  })
  check('all delivery illustrations exist in the app icon font', () => {
    const source = fs.readFileSync(path.join(sourceRoot, 'pages/order/components/DeliveryIcon.vue'), 'utf8')
    const icons = fs.readFileSync(path.join(root, 'uni-app/src/styles/official-iconfont.css'), 'utf8')
    for (const icon of ['nc-icon-daifahuoV6xx', 'nc-icon-dianpuV6xx', 'nc-icon-daishouhuoV6xx']) {
      assert.ok(source.includes(icon))
      assert.ok(icons.includes('.' + icon + ':before'))
    }
  })
  check('mini program registers callable validation rules after page readiness', () => {
    const source = fs.readFileSync(path.join(sourceRoot, 'pages/order/order.vue'), 'utf8')
    assert.match(source, /onReady\(\(\) => \{[\s\S]*?formRef\.value\?\.setRules\(rules\)/)
    assert.match(source, /watch\(\[currentTab, enablePlatformDelivery\][\s\S]*?clearValidate\('express_no'\)/)
  })

  const hook = 'hooks/useOrderForm.ts'
  check('form hook package mirror', () => assert.equal(fs.readFileSync(path.join(sourceRoot, hook), 'utf8'), fs.readFileSync(path.join(packageRoot, hook), 'utf8')))
  const { useOrderForm } = loadModule(path.join(sourceRoot, hook), { vue: req('vue'), '@/stores/member': () => ({ info: { mobile: '' } }) })
  const Schema = loadModule(req.resolve('uview-plus/libs/util/async-validator.js')).default
  const tab = req('vue').ref(0)
  let platform = false
  const { rules } = useOrderForm(tab, () => platform)
  const validate = value => new Promise(resolve => new Schema(rules).validate({ express_no: value }, errors => resolve(errors ? Array.from(errors, item => item.message) : [])))
  for (const value of ['', '   ', '\n\t']) {
    const errors = await validate(value)
    check('manual shipping empty value gives Chinese prompt: ' + JSON.stringify(value), () => assert.deepEqual(errors, ['请输入或扫描快递单号']))
  }
  for (const value of ['SF1234567890123', 'YT00123456789', '123456789012', 'sf00123456']) {
    const errors = await validate(value)
    check('letter and numeric waybills remain valid: ' + value, () => assert.deepEqual(errors, []))
  }
  platform = true
  let errors = await validate('')
  check('pickup does not require a waybill before booking', () => assert.deepEqual(errors, []))
  platform = false
  for (const mode of [1, 2]) {
    tab.value = mode
    errors = await validate('')
    check('non-postal delivery has no waybill requirement: ' + mode, () => assert.deepEqual(errors, []))
  }
  tab.value = 0
  errors = await validate('')
  check('switching back to manual shipping restores validation', () => assert.deepEqual(errors, ['请输入或扫描快递单号']))
  console.log(`Completed ${checks} checks. No business requests sent.`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
