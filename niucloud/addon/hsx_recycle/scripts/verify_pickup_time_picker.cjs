// Real Vue SFC rendered in an isolated browser; popup/icon stand-ins, no business API/network.
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const req = require('node:module').createRequire(path.join(root, 'uni-app/package.json'))
const sfc = req('@vue/compiler-sfc'), { transformSync } = req('esbuild')
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright')
const filename = path.join(root, 'niucloud/addon/hsx_recycle/uni-app/pages/order/components/PickupTimePicker.vue')
const { descriptor } = sfc.parse(fs.readFileSync(filename, 'utf8'), { filename })
const id = 'data-v-pickup-test'
const script = sfc.compileScript(descriptor, { id, inlineTemplate: true, templateOptions: { compilerOptions: { isCustomElement: tag => ['view', 'text', 'scroll-view'].includes(tag) } } })
const js = transformSync(script.content, { loader: 'ts', format: 'cjs' }).code
const css = sfc.compileStyle({ source: descriptor.styles[0].content, filename, id, scoped: true }).code
const vue = fs.readFileSync(req.resolve('vue/dist/vue.global.js'), 'utf8')
const days = ['今天', '明天', '后天', '大后天'].map((label, index) => {
  const date = `2030-01-0${index + 1}`
  return { date, label, date_text: `01月0${index + 1}日`, slots: ['09:00-18:00', '09:00-11:00', '11:00-13:00', '13:00-15:00', '15:00-17:00', '17:00-18:00'].map((range, i) => ({ value: `${date} ${range}`, label: range, text: `${label}（01月0${index + 1}日）${range}`, hint: i === 0 ? '此时段均可' : '' })) }
})
;(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || undefined })
  let checks = 0
  try {
    for (const width of [320, 375, 430]) {
      const page = await browser.newPage({ viewport: { width, height: 812 } })
      await page.route('**/*', route => route.abort())
      const errors = []
      page.on('pageerror', e => errors.push(e.message))
      await page.setContent(`<style>body{margin:0;font-family:Arial,sans-serif;background:#f3f4f6}view{display:block}scroll-view{display:block;overflow-y:auto}text{display:inline}.popup{position:fixed;bottom:0;left:0;right:0;background:white}.close{position:absolute;right:10px;top:8px;z-index:3} ${css.replace(/([\d.]+)rpx/g, (_, value) => `${Number(value) * width / 750}px`)}</style><div id="app"></div>`)
      await page.addScriptTag({ content: vue })
      // 滚动锁在 verify_popup_scroll.cjs 单独验证；这里聚焦时段选择交互。
      await page.addScriptTag({ content: `const module={exports:{}};const exports=module.exports;const require=name=>{if(name==='vue')return Vue;if(name==='../../../hooks/useRecyclePopupScroll')return {useRecyclePopupLock(){}};throw Error(name)};${js};window.Picker=module.exports.default;Picker.__scopeId='${id}';` })
      await page.evaluate(days => {
        const state = Vue.reactive({ show: true, value: days[0].slots[0].value, selected: 0 })
        window.testState = state
        const app = Vue.createApp({ render: () => Vue.h(Picker, { show: state.show, modelValue: state.value, days, 'onUpdate:show': value => { state.show = value }, onSelect: slot => { state.value = slot.value; state.selected++ } }) })
        app.component('u-popup', { props: ['show'], emits: ['close'], render() { return this.show ? Vue.h('div', { class: 'popup' }, [Vue.h('button', { class: 'close', onClick: () => this.$emit('close') }, '关闭'), this.$slots.default()]) : null } })
        app.component('up-icon', { render: () => Vue.h('span', '✓') })
        app.mount('#app')
      }, days)
      assert.equal(await page.locator('.picker-date').count(), 4); checks++
      assert.equal(await page.locator('.picker-slot').count(), 6); checks++
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)); checks++
      const right = await page.locator('.picker-slots').boundingBox()
      assert.ok(right.width > 180 && right.x + right.width <= width + 1); checks++
      await page.locator('.picker-date').nth(2).dispatchEvent('tap')
      assert.equal(await page.evaluate(() => testState.value), days[0].slots[0].value); checks++
      await page.locator('.picker-slot').nth(3).dispatchEvent('tap')
      assert.equal(await page.evaluate(() => testState.value), '2030-01-03 13:00-15:00'); checks++
      assert.equal(await page.locator('.popup').count(), 0); checks++
      await page.evaluate(() => { testState.show = true })
      assert.ok((await page.locator('.picker-date.active').textContent()).includes('后天')); checks++
      assert.ok((await page.locator('.picker-slot.selected').textContent()).includes('13:00-15:00')); checks++
      if (width === 375) await page.screenshot({ path: '/tmp/hsx-pickup-time-picker-375.png' })
      await page.locator('.close').click()
      assert.equal(await page.evaluate(() => testState.selected), 1); checks++
      assert.equal(await page.locator('.popup').count(), 0); checks++
      assert.deepEqual(errors, []); checks++
      await page.close()
    }
    console.log(`PASS ${checks} browser checks at 320/375/430px. Isolated component with popup/icon stand-ins; no actual booking.`)
  } finally { await browser.close() }
})().catch(error => { console.error(error); process.exitCode = 1 })
