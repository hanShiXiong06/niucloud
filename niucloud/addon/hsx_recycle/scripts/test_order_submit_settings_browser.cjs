// Real settings components, isolated API fixtures. Never writes business data.
const fs = require('node:fs')
const path = require('node:path')
const assert = require('node:assert/strict')
const { createRequire } = require('node:module')
const { chromium } = require('playwright')
const root = path.resolve(__dirname, '../../../../admin')
const local = createRequire(path.join(root, 'package.json'))
const { createServer } = local('vite')
const vue = local('@vitejs/plugin-vue').default
const output = '/private/tmp/hsx-order-settings'
const apiId = '\0order-settings-api'
const erpId = '\0order-settings-erp'
const coreId = '\0order-settings-core'
const bridgeId = '\0order-settings-bridge'
const fixture = {
    device_add_enabled: 1, default_count: 1,
    notice: { enabled: 0, title: '下单提示', content: '测试通知保留' },
    delivery_modes: { mail: 1, self: 1, logistics_vehicle: 0 },
    logistics_vehicle: { arrival_mode: 'half_day', morning_cutoff: '12:00', same_day_time: '16:00', next_day_time: '09:00' },
    profile: { enabled: 1, payment_required: 1, payment_min_count: 1, id_card_required: 1 },
    flow: { mode: 'order' }, payment: { mode: 'order' },
    platform_delivery: {
        display_name: '京东快递', provider: 'yisu', provider_name: '亿速物流', product_code: 'JD', product_name: '京东', free_shipping_min_count: 2,
        provider_options: [{ provider: 'yisu', provider_name: '亿速物流', is_default: 1 }],
        product_options: [{ provider: 'yisu', product_code: 'JD', product_name: '京东' }]
    },
    follow_official_account: { enabled: 0, wechat_name: '保留公众号', title: '关注公众号', content: '保留说明', qr_code: '' },
    customer_service: { enabled: 0, type: 'wechat', title: '联系客服', content: '保留客服说明', qrcode: '' },
    consignment: { enabled: 0, user_title: '测试代卖入口', user_desc: '保留代卖说明' },
    work_wechat: { enabled: 0, channels: { order_urge: { enabled: 1, name: '订单催办群', webhook_url: '' } } },
    device_bridge: { windows_url: '', windows_version: '', macos_arm64_url: '', macos_arm64_version: '', tutorial_url: '' }
}
const entry = `import {createApp,h} from 'vue'; import ElementPlus from 'element-plus';
import {createRouter,createMemoryHistory,RouterView} from 'vue-router';
import 'element-plus/dist/index.css'; import Settings from '/src/addon/hsx_recycle/views/order_config/submit.vue';
const router=createRouter({history:createMemoryHistory(),routes:[{path:'/',component:Settings},{path:'/diy/theme_style',component:{render:()=>h('p','Theme fixture')}}]});
const app=createApp(RouterView);
app.component('UploadImage',{props:['modelValue'],render:()=>h('button',{type:'button'},'上传二维码')});
app.use(ElementPlus).use(router);router.isReady().then(()=>app.mount('#app'));`

async function main() {
    const server = await createServer({
        root, configFile: false, publicDir: false,
        cacheDir: path.join(root, 'node_modules/.vite-preview/order-settings'),
        css: { postcss: { plugins: [] } },
        server: { host: '127.0.0.1', port: 5197, strictPort: false },
        resolve: { alias: [
            { find: '@/addon/hsx_recycle/api/order_config', replacement: apiId },
            { find: '@/addon/hsx_recycle/api/erp_integration', replacement: erpId },
            { find: '@/addon/hsx_recycle/api/device_bridge', replacement: bridgeId },
            { find: '@/addon/hsx_components/core', replacement: coreId },
            { find: '@/', replacement: path.join(root, 'src') + '/' }
        ] },
        plugins: [{ name: 'settings-preview', enforce: 'pre',
            resolveId(id) { if (id === 'virtual:settings-entry') return '\0' + id },
            load(id) {
                if (id === '\0virtual:settings-entry') return entry
                if (id === bridgeId) return `export async function getDeviceBridgeDownloads(){return {data:{}}}`
                if (id === coreId) return `import '/src/addon/hsx_components/styles/theme.scss';
                    export {default as HsxPage} from '/src/addon/hsx_components/components/HsxPage/index.vue';
                    export {default as HsxNotice} from '/src/addon/hsx_components/components/HsxNotice/index.vue';
                    export {useFeedback} from '/src/addon/hsx_components/hooks/useFeedback';`
                if (id === apiId) return `export async function getOrderSubmitConfig(){if(window.__settings.loadError)throw Error('offline');return {data:structuredClone(window.__settings.data)}}
                    export async function saveOrderSubmitConfig(data){
                        window.__settings.calls.push(structuredClone(data));
                        if(window.__settings.delay)await new Promise(r=>setTimeout(r,window.__settings.delay));
                        if(window.__settings.saveError)throw Error('测试保存失败');
                        window.__settings.data=structuredClone(data);return {data:true};
                    }`
                if (id === erpId) return `export async function getRecycleErpIntegration(){window.__settings.erpLoads++;return {data:{installed:true,configured:true,mode:'local',changed_at:0}}}
                    export async function saveRecycleErpIntegration(){window.__settings.erpSaves++;return {data:true}}`
            },
            configureServer(server) {
                server.middlewares.use(async (req, res, next) => {
                    if (req.url.split('?')[0] !== '/__settings-preview') return next()
                    res.setHeader('Content-Type', 'text/html; charset=utf-8')
                    res.end(await server.transformIndexHtml(req.url, '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Order settings test</title><style>body{margin:0;background:#f5f6f8;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC",sans-serif}*{box-sizing:border-box}</style></head><body><div id="app"></div><script type="module">import "virtual:settings-entry"</script></body></html>'))
                })
            }
        }, vue()],
        optimizeDeps: { entries: [], include: ['vue', 'vue-router', 'element-plus', '@element-plus/icons-vue', '@vueuse/core'] }
    })
    let browser
    try {
        await server.listen()
        const origin = `http://127.0.0.1:${server.httpServer.address().port}`
        browser = await chromium.launch({ headless: true, channel: 'chrome' })
        const page = await browser.newPage({ viewport: { width: 1366, height: 960 } })
        const pageErrors = []
        page.on('pageerror', e => pageErrors.push(String(e)))
        await page.route('**/*', route => {
            if (new URL(route.request().url()).origin !== origin) throw Error('External request blocked: ' + route.request().url())
            return route.continue()
        })
        await page.addInitScript(data => { window.__settings = { data, calls: [], erpLoads: 0, erpSaves: 0, loadError: false, saveError: false, delay: 0 } }, fixture)
        await page.goto(origin + '/__settings-preview')
        const tab = name => page.getByRole('tab', { name, exact: true })
        const save = () => page.getByRole('button', { name: '保存设置', exact: true }).click()
        await tab('客户下单').waitFor()
        assert.equal(await page.getByRole('tab').count(), 6)
        assert.equal(await page.evaluate(() => window.__settings.erpLoads), 0, 'ERP loads only when opened')
        assert.equal(await page.locator('#device-bridge').isVisible(), false)
        fs.mkdirSync(output, { recursive: true })
        await page.screenshot({ path: path.join(output, 'customer-desktop.png'), animations: 'disabled', fullPage: true })

        await page.locator('#default-count input').fill('3')
        await page.locator('#default-count input').blur()
        await page.getByText('有未保存的修改', { exact: true }).waitFor()
        await tab('通知与客服').click()
        assert.equal(await page.locator('#notice-content').count(), 0, 'Disabled options are collapsed')
        await page.locator('#notice-enabled .el-switch').click()
        assert.equal(await page.locator('#notice-content textarea').inputValue(), '测试通知保留')
        await page.locator('#notice-content textarea').fill('')
        await tab('客户下单').click()
        assert.equal(await page.locator('#default-count input').inputValue(), '3')
        await save()
        await page.getByText('请填写通知内容', { exact: true }).waitFor()
        assert.equal(await tab('通知与客服').getAttribute('aria-selected'), 'true')
        assert.equal(await page.evaluate(() => window.__settings.calls.length), 0)
        await page.locator('#notice-content textarea').fill('跨分区保存的通知')

        await page.locator('#work-wechat-enabled .el-switch').click()
        await tab('客户下单').click()
        await save()
        await page.getByText('请填写订单催办群的 Webhook 地址', { exact: true }).waitFor()
        await page.locator('#channel-webhook-order_urge input').waitFor({ state: 'visible' })
        await page.locator('#channel-webhook-order_urge input').fill('https://example.com/fixture-webhook')
        await tab('设备与工具').click()
        assert.equal(await page.getByPlaceholder('https://.../hsx_device_bridge-版本-windows-x64-setup.exe').count(), 0, 'Sites cannot edit platform download links')
        await page.getByRole('button', { name: '安装与帮助', exact: true }).waitFor()
        await save()
        await page.waitForFunction(() => window.__settings.calls.length === 1)
        assert.equal(await page.getByText('有未保存的修改', { exact: true }).count(), 0)
        const saved = await page.evaluate(() => window.__settings.calls[0])
        assert.equal(saved.default_count, 3)
        assert.equal(saved.notice.content, '跨分区保存的通知')
        assert.equal('device_bridge' in saved, false, 'Site save does not write shared downloads')
        assert.equal(saved.consignment.user_title, '测试代卖入口', 'Collapsed values survive saves')
        assert.equal(saved.follow_official_account.wechat_name, '保留公众号')

        await tab('ERP 联动').click()
        await page.getByText('自有 ERP 已安装', { exact: true }).waitFor()
        assert.equal(await page.getByRole('button', { name: '保存设置', exact: true }).count(), 0, 'ERP has an independent save')
        await tab('客户下单').click()
        await tab('ERP 联动').click()
        assert.equal(await page.evaluate(() => window.__settings.erpLoads), 1, 'Switching sections does not reload or discard ERP edits')
        assert.equal(await page.evaluate(() => window.__settings.erpSaves), 0)

        await tab('业务流转').click()
        await page.locator('#flow-mode .el-radio-button').filter({ hasText: '按设备流转' }).click()
        await page.getByRole('button', { name: '取消', exact: true }).click()
        assert.equal(await page.getByRole('radio', { name: '整单流转', exact: true }).isChecked(), true)
        await page.locator('#flow-mode .el-radio-button').filter({ hasText: '按设备流转' }).click()
        await page.getByRole('button', { name: '确认切换', exact: true }).click()
        await save()
        await page.waitForFunction(() => window.__settings.calls.length === 2)
        assert.equal(await page.evaluate(() => window.__settings.calls[1].payment.mode), 'device')

        await tab('客户下单').click()
        await page.locator('#default-count input').fill('4')
        await page.locator('#default-count input').blur()
        await page.evaluate(() => { window.__settings.saveError = true })
        await save()
        await page.getByText('测试保存失败', { exact: true }).waitFor()
        assert.equal(await page.locator('#default-count input').inputValue(), '4')
        await page.evaluate(() => { window.__settings.saveError = false; window.__settings.delay = 500 })
        await save()
        await page.locator('#default-count input').fill('5')
        await page.locator('#default-count input').blur()
        await page.waitForFunction(() => window.__settings.data.default_count === 4)
        assert.equal(await page.getByText('有未保存的修改', { exact: true }).count(), 1, 'In-flight edits remain unsaved')
        await page.evaluate(() => { window.__settings.delay = 0 })
        await page.locator('.el-message').waitFor({ state: 'hidden' })

        for (const width of [1366, 900, 390]) {
            await page.setViewportSize({ width, height: 900 })
            for (const name of ['客户下单', '配送与取货', '业务流转', '通知与客服', '设备与工具', 'ERP 联动']) {
                await tab(name).click()
                if (name === '配送与取货' && width === 1366) await page.locator('#delivery-vehicle .el-switch').click()
                if (name === '业务流转' && width === 1366) await page.locator('#consignment-enabled .el-switch').click()
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${width}px ${name}: no page overflow`)
                await page.screenshot({ path: path.join(output, `${width}-${name}.png`), animations: 'disabled', fullPage: true })
            }
        }
        await page.setViewportSize({ width: 1366, height: 960 })
        await tab('设备与工具').click()
        await page.getByRole('button', { name: '主题风格', exact: true }).click()
        await page.getByText('设置尚未保存，确定离开吗？', { exact: true }).waitFor()
        await page.getByRole('button', { name: '继续编辑', exact: true }).click()
        assert.equal(await tab('设备与工具').isVisible(), true)

        await save()
        await page.getByText('有未保存的修改', { exact: true }).waitFor({ state: 'hidden' })
        await page.reload()
        await tab('客户下单').waitFor()
        await page.getByRole('tab', { name: '设备与工具', exact: true }).click()
        await page.getByRole('button', { name: '主题风格', exact: true }).click()
        await page.getByText('Theme fixture').waitFor()
        assert.deepEqual(pageErrors, [])
        const failed = await browser.newPage({ viewport: { width: 1366, height: 900 } })
        await failed.addInitScript(data => { window.__settings = { data, calls: [], erpLoads: 0, erpSaves: 0, loadError: true } }, fixture)
        await failed.goto(origin + '/__settings-preview')
        await failed.getByText('设置加载失败，暂不能修改或保存。请重新加载。', { exact: true }).waitFor()
        assert.equal(await failed.getByRole('button', { name: '保存设置', exact: true }).isDisabled(), true)
        await failed.evaluate(() => { window.__settings.loadError = false })
        await failed.getByRole('button', { name: '重新加载', exact: true }).click()
        await failed.getByRole('tab', { name: '客户下单', exact: true }).waitFor()
        console.log('PASS: six sections, preserved fields, validation navigation, ERP isolation, flow confirmation, save/load failures, in-flight changes, leave guard, desktop/narrow layouts')
        console.log('Screenshots: ' + output)
    } finally {
        if (browser) await browser.close()
        await server.close()
    }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
