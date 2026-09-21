// Render the real help/settings components with isolated download and health fixtures.
// No business APIs, real hardware, downloads, or installation are invoked.
const fs = require('node:fs')
const path = require('node:path')
const assert = require('node:assert/strict')
const { createRequire } = require('node:module')
const { chromium } = require('playwright')
const root = path.resolve(__dirname, '../../../../admin')
const local = createRequire(path.join(root, 'package.json'))
const { createServer } = local('vite')
const vue = local('@vitejs/plugin-vue').default
const filename = path.join(root, '__bridge-onboarding-preview__.vue')
const output = process.env.BRIDGE_TEST_OUTPUT || '/private/tmp/hsx-bridge-onboarding'
const fixtureModule = '\0bridge-download-fixture'
const coreModule = '\0bridge-platform-core'
const component = `<template>
    <main>
        <el-button v-if="!settings" :icon="Download" @click="visible = true">安装与帮助</el-button>
        <DeviceBridgeHelpDialog v-model="visible" @read="reads++" />
        <p v-if="!settings" data-testid="reads">{{ reads }}</p>
        <PlatformSettings v-if="settings" />
    </main>
</template>
<script setup>
import { ref } from 'vue'
import { Download } from '@element-plus/icons-vue'
import DeviceBridgeHelpDialog from '/src/addon/hsx_recycle/components/device-entry/DeviceBridgeHelpDialog.vue'
import PlatformSettings from '/src/addon/hsx_recycle/views/device_bridge/settings.vue'
const visible = ref(false), reads = ref(0)
const settings = new URLSearchParams(location.search).has('settings')
</script>`
const entry = `import {createApp,h} from 'vue'; import ElementPlus from 'element-plus';
import {createRouter,createMemoryHistory,RouterView} from 'vue-router';
import 'element-plus/dist/index.css'; import Preview from 'virtual:bridge-preview.vue';
const router=createRouter({history:createMemoryHistory(),routes:[{path:'/',component:Preview},{path:'/away',component:{render:()=>h('p','Away fixture')}}]});
window.__bridgeRouter=router;
createApp(RouterView).use(ElementPlus).use(router).mount('#app');`

async function main() {
    const server = await createServer({
        root, configFile: false, publicDir: false,
        cacheDir: path.join(root, 'node_modules/.vite-preview/bridge-onboarding'),
        css: { postcss: { plugins: [] } },
        server: { host: '127.0.0.1', port: 5196, strictPort: false },
        resolve: { alias: [
            { find: '@/addon/hsx_recycle/api/device_bridge', replacement: fixtureModule },
            { find: '@/addon/hsx_components/core', replacement: coreModule },
            { find: '@/', replacement: path.join(root, 'src') + '/' }
        ] },
        plugins: [{ name: 'bridge-onboarding-preview', enforce: 'pre',
            resolveId(id) {
                if (id === 'virtual:bridge-preview.vue' || id === '/__bridge-onboarding-preview__.vue') return filename
                if (id.startsWith('/__bridge-onboarding-preview__.vue?')) return filename + id.slice('/__bridge-onboarding-preview__.vue'.length)
                if (id === 'virtual:bridge-entry') return '\0' + id
            },
            load(id) {
                if (id === filename) return component
                if (id === '\0virtual:bridge-entry') return entry
                if (id === coreModule) return `import '/src/addon/hsx_components/styles/theme.scss';
                    export {default as HsxPage} from '/src/addon/hsx_components/components/HsxPage/index.vue';`
                if (id === fixtureModule) return `export async function getDeviceBridgeDownloads(){
                    if(window.__bridgeFixture.downloadError)throw Error('fixture unavailable');
                    return {data:window.__bridgeFixture.downloads || {}};
                }
                export async function getPlatformDeviceBridgeConfig(){
                    if(window.__bridgeFixture.loadError)throw Error('fixture offline');
                    return {data:structuredClone(window.__bridgeFixture.downloads || {})};
                }
                export async function savePlatformDeviceBridgeConfig(data){
                    window.__bridgeFixture.saves.push(structuredClone(data));
                    if(window.__bridgeFixture.saveError)throw Error('fixture failed');
                    window.__bridgeFixture.downloads=structuredClone(data);return {data:true};
                }`
            },
            configureServer(server) {
                server.middlewares.use(async (req, res, next) => {
                    if (req.url.split('?')[0] !== '/__bridge-preview') return next()
                    res.setHeader('Content-Type', 'text/html; charset=utf-8')
                    res.end(await server.transformIndexHtml(req.url, '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bridge onboarding test</title><style>body{margin:0;background:#fff;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC",sans-serif}main{padding:24px;max-width:1000px;margin:auto}*{box-sizing:border-box}</style></head><body><div id="app"></div><script type="module">import "virtual:bridge-entry"</script></body></html>'))
                })
            }
        }, vue()],
        optimizeDeps: { entries: [], include: ['vue', 'vue-router', 'element-plus', '@element-plus/icons-vue', 'axios'] }
    })
    let browser
    try {
        await server.listen()
        const port = server.httpServer.address().port
        const origin = `http://127.0.0.1:${port}`
        const url = origin + '/__bridge-preview'
        browser = await chromium.launch({ headless: true, channel: process.env.BRIDGE_TEST_BROWSER || 'chrome' })
        const page = await browser.newPage({ viewport: { width: 1366, height: 900 }, userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0.0.0' })
        let healthMode = 'offline', healthRequests = 0
        const pageErrors = []
        page.on('pageerror', error => pageErrors.push(String(error)))
        await page.addInitScript(() => { window.__bridgeFixture = { downloads: {}, saves: [], loadError: location.search.includes('loadError=1') } })
        await page.route('**/*', async route => {
            const target = new URL(route.request().url())
            if (target.origin === origin) return route.continue()
            if (target.origin === 'http://127.0.0.1:17890' && target.pathname === '/v1/health') {
                healthRequests++
                if (healthMode === 'offline') return route.abort('connectionrefused')
                if (healthMode === 'blocked') return route.fulfill({ status: 403, contentType: 'application/json', body: '{}' })
                return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ code: 0, data: {
                    service: healthMode === 'invalid' ? 'other-service' : 'hsx_device_bridge',
                    version: healthMode === 'generic' ? '0.3.0' : healthMode === 'android' ? '0.2.0' : '0.1.3',
                    device_count: ['generic', 'android'].includes(healthMode) ? 1 : 0,
                    scan_error: '', driver: { ready: true }, capabilities: {
                        android_mtp: ['generic', 'android'].includes(healthMode),
                        android_mtp_scope: healthMode === 'generic' ? 'generic' : ''
                    }
                } }) })
            }
            throw Error('Unexpected external request: ' + route.request().url())
        })
        await page.goto(url)
        await page.getByRole('button', { name: '安装与帮助', exact: true }).waitFor()
        assert.equal(healthRequests, 0, 'No localhost probing before an explicit click')
        await page.getByRole('button', { name: '安装与帮助', exact: true }).click()
        await page.getByText('未连接到设备桥。', { exact: false }).waitFor()
        assert.equal(await page.getByRole('button', { name: '待提供安装包' }).count(), 2)
        assert.equal(await page.getByRole('link', { name: '下载安装包' }).count(), 0)
        fs.mkdirSync(output, { recursive: true })
        await page.screenshot({ path: path.join(output, 'desktop-offline.png'), animations: 'disabled' })
        await page.getByRole('tab', { name: '安卓 · MTP' }).click()
        await page.getByText('现有 Windows 包仅支持 iPhone', { exact: false }).waitFor()
        await page.getByRole('button', { name: '关闭', exact: true }).click()

        await page.evaluate(() => { window.__bridgeFixture.downloads = { windows_url: '/fixtures/bridge.exe', windows_version: '0.2.0', macos_arm64_url: '/fixtures/bridge.pkg', macos_arm64_version: '0.2.0', tutorial_url: 'javascript:alert(1)' } })
        healthMode = 'connected'
        await page.getByRole('button', { name: '安装与帮助', exact: true }).click()
        await page.getByText('设备桥已连接', { exact: true }).waitFor()
        await page.getByText('可更新至 0.2.0', { exact: false }).waitFor()
        assert.equal(await page.locator('a.bridge-download').count(), 2)
        assert.equal(await page.locator('a[href^="javascript:"]').count(), 0)
        await page.screenshot({ path: path.join(output, 'desktop-connected.png'), animations: 'disabled' })
        await page.getByRole('button', { name: '返回并读取' }).click()
        await page.getByTestId('reads').filter({ hasText: '1' }).waitFor()

        await page.setViewportSize({ width: 390, height: 844 })
        healthMode = 'blocked'
        await page.getByRole('button', { name: '安装与帮助', exact: true }).click()
        await page.getByText('当前后台域名未获授权', { exact: false }).waitFor()
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'No horizontal page overflow')
        const footer = await page.locator('.bridge-footer').boundingBox()
        assert.ok(footer.y + footer.height <= 844, 'Footer remains in the viewport')
        await page.screenshot({ path: path.join(output, 'mobile-blocked.png'), animations: 'disabled' })
        healthMode = 'invalid'
        await page.getByRole('button', { name: '已安装，检测连接' }).click()
        await page.getByText('本地端口返回的不是设备桥服务', { exact: false }).waitFor()
        healthMode = 'connected'
        await page.getByRole('button', { name: '已安装，检测连接' }).click()
        await page.getByText('设备桥已连接', { exact: true }).waitFor()
        healthMode = 'android'
        await page.getByRole('button', { name: '已安装，检测连接' }).click()
        await page.getByText('版本 0.2.0 · 1 台设备', { exact: true }).waitFor()
        await page.getByText('此 Mac 版本支持三星 MTP 基础读取', { exact: false }).waitFor()
        healthMode = 'generic'
        await page.getByRole('button', { name: '已安装，检测连接' }).click()
        await page.getByText('版本 0.3.0 · 1 台设备', { exact: true }).waitFor()
        await page.getByText('支持 iPhone、安卓 MTP 基础读取（Mac）', { exact: true }).waitFor()
        await page.getByText('按 MTP 协议读取，不限制品牌', { exact: false }).waitFor()
        await page.screenshot({ path: path.join(output, 'mobile-generic-mtp.png'), animations: 'disabled' })
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true)
        await page.setViewportSize({ width: 1366, height: 900 })
        await page.screenshot({ path: path.join(output, 'desktop-generic-mtp.png'), animations: 'disabled' })
        await page.getByRole('button', { name: '返回并读取' }).click()

        await page.evaluate(() => { window.__bridgeFixture.downloadError = true })
        await page.getByRole('button', { name: '安装与帮助', exact: true }).click()
        await page.getByText('下载信息加载失败', { exact: false }).waitFor()
        assert.equal(await page.locator('a.bridge-download').count(), 0, 'No stale download links on failure')
        await page.evaluate(() => { window.__bridgeFixture.downloadError = false })
        await page.getByRole('button', { name: '重试', exact: true }).click()
        await page.locator('a.bridge-download').first().waitFor()
        await page.getByRole('button', { name: '返回并读取' }).click()

        await page.goto(url + '?settings=1')
        const save = page.getByRole('button', { name: '保存配置', exact: true })
        assert.equal(await save.isDisabled(), true)
        await page.getByPlaceholder('https://.../hsx_device_bridge-版本-windows-x64-setup.exe').fill('javascript:alert(1)')
        await page.getByText('请填写完整 http(s) 地址', { exact: true }).waitFor()
        await page.getByPlaceholder('https://.../hsx_device_bridge-版本-windows-x64-setup.exe').fill('/upload/bridge.exe')
        await save.click()
        await page.getByText('请填写完整 http(s) 下载或教程地址。', { exact: true }).waitFor()
        assert.equal(await page.evaluate(() => window.__bridgeFixture.saves.length), 0)
        await page.getByPlaceholder('https://.../hsx_device_bridge-版本-windows-x64-setup.exe').fill('https://downloads.example.com/bridge.exe')
        await page.getByPlaceholder('如 0.2.0').first().fill('latest')
        await page.getByText('请填写数字版本，如 0.2.0', { exact: true }).waitFor()
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true)
        await page.screenshot({ path: path.join(output, 'mobile-settings.png'), animations: 'disabled' })
        await page.getByPlaceholder('如 0.2.0').first().fill('0.2.0')
        await page.evaluate(() => { window.__bridgeFixture.saveError = true })
        await save.click()
        await page.getByText('保存失败，修改内容已保留，请重试。', { exact: true }).waitFor()
        assert.equal(await page.getByPlaceholder('如 0.2.0').first().inputValue(), '0.2.0')
        await page.evaluate(() => { window.__bridgeFixture.saveError = false })
        await save.click()
        await page.getByText('平台下载配置已保存', { exact: true }).waitFor()
        assert.equal(await save.isDisabled(), true)
        assert.equal(await page.evaluate(() => window.__bridgeFixture.downloads.windows_version), '0.2.0')
        await page.screenshot({ path: path.join(output, 'platform-mobile.png'), animations: 'disabled' })
        await page.setViewportSize({ width: 1366, height: 900 })
        await page.screenshot({ path: path.join(output, 'platform-desktop.png'), animations: 'disabled' })
        await page.getByPlaceholder('如 0.2.0').first().fill('0.2.1')
        await page.evaluate(() => { void window.__bridgeRouter.push('/away') })
        await page.getByText('下载配置尚未保存，确定离开吗？', { exact: true }).waitFor()
        await page.getByRole('button', { name: '继续编辑', exact: true }).click()
        assert.equal(await page.getByPlaceholder('如 0.2.0').first().inputValue(), '0.2.1')
        await page.goto(url + '?settings=1&loadError=1')
        await page.getByText('平台配置加载失败，请重新加载。', { exact: true }).waitFor()
        assert.equal(await save.isDisabled(), true)
        await page.evaluate(() => { window.__bridgeFixture.loadError = false })
        await page.getByRole('button', { name: '重新加载', exact: true }).click()
        await page.getByPlaceholder('如 0.2.0').first().waitFor()
        assert.deepEqual(pageErrors, [])
        console.log('PASS browser: explicit probes, missing packages, update hint, capability-based Android help, retry, read event, 403, invalid service, desktop/390px layout, platform validation/save/failures/leave guard')
        console.log('Screenshots: ' + output)
    } finally {
        if (browser) await browser.close()
        await server.close()
    }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
