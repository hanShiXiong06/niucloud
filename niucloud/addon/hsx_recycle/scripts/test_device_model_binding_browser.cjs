// Real entry list + Element Plus, with all APIs and USB reads replaced by fixtures.
const fs = require('node:fs')
const path = require('node:path')
const assert = require('node:assert/strict')
const { createRequire } = require('node:module')
const { chromium } = require('playwright')
const root = path.resolve(__dirname, '../../../../admin')
const local = createRequire(path.join(root, 'package.json'))
const { createServer } = local('vite')
const vue = local('@vitejs/plugin-vue').default
const filename = path.join(root, '__model-binding-preview__.vue')
const output = '/private/tmp/hsx-model-binding'
const api = '\0model-binding-api'
const component = `<template><main><DeviceEntryList :devices="rows" /></main></template>
<script setup>
import {reactive} from 'vue'
import DeviceEntryList from '/src/addon/hsx_recycle/components/device-entry/DeviceEntryList.vue'
const rows = reactive([{_k:1,imei:'TEST-SN',model:'16th',local_model_aliases:['16th'],initial_price:0,summary_fields:[],summary_values:{}}])
window.__entryRows=rows
</script>`
const apiFixture = `
const fixture=()=>window.__bindingFixture;
const copy=value=>structuredClone(value);
export async function getRecycleDeviceModelDictChildren({pid=0}) {
    return {data:copy(fixture().nodes.filter(node=>node.pid===Number(pid)))};
}
export async function getRecycleDeviceModelDictOptions({keyword=''}) {
    return {data:copy(fixture().nodes.filter(node=>!node.has_children && node.node_name.includes(keyword)))};
}
export async function getRecycleDeviceModelDictTree(){throw Error('Full tree should not be fetched')}
export async function resolveRecycleDeviceModelAlias(aliases){
    const id=aliases.map(alias=>fixture().mappings[alias]).find(Boolean);
    return {data:id?{matched:true,node:copy(fixture().nodes.find(node=>node.id===id))}:{matched:false}};
}
export async function bindRecycleDeviceModelAlias(data){
    fixture().binds.push(copy(data));
    if(fixture().failBinding)throw Error('fixture: binding failed');
    data.aliases.forEach(alias=>fixture().mappings[alias]=data.category_id);
    return {data:{node:copy(fixture().nodes.find(node=>node.id===data.category_id))}};
}
export async function ensureRecycleDeviceModelDictChild(data){
    fixture().creates.push(copy(data));
    throw Error('Unexpected category creation in a binding test');
}
export async function getCheckTemplateSchema(data){fixture().templates.push(copy(data));return {data:{groups:[]}}}
export async function addOrderDevice(){throw Error('No order writes allowed')}
export async function updateOrderDevice(){throw Error('No order writes allowed')}
export async function deleteOrderDevice(){throw Error('No order writes allowed')}
`

async function main() {
    for (const file of ['DeviceEntryList.vue', 'QuickAddModelDialog.vue', 'types.ts']) {
        const relative = 'components/device-entry/' + file
        assert.equal(fs.readFileSync(path.join(root, 'src/addon/hsx_recycle', relative), 'utf8'),
            fs.readFileSync(path.join(root, '../niucloud/addon/hsx_recycle/admin', relative), 'utf8'), 'Release copy: ' + file)
    }
    const server = await createServer({
        root, configFile: false, publicDir: false,
        cacheDir: path.join(root, 'node_modules/.vite-preview/model-binding'),
        css: { postcss: { plugins: [] } },
        server: { host: '127.0.0.1', port: 5197, strictPort: false },
        resolve: { alias: [
            ...['recycle_device_model_dict', 'recycle_order', 'check_template'].map(name => ({find:'@/addon/hsx_recycle/api/' + name,replacement:api})),
            { find: '@/addon/hsx_components/core', replacement: '\0model-binding-core' },
            { find: '@/', replacement: path.join(root, 'src') + '/' }
        ] },
        plugins: [{ name: 'model-binding-preview', enforce: 'pre',
            resolveId(id) {
                if (id === 'virtual:model-binding.vue' || id === '/__model-binding-preview__.vue') return filename
                if (id.startsWith('/__model-binding-preview__.vue?')) return filename + id.slice('/__model-binding-preview__.vue'.length)
                if (/\/(CheckSummaryDialog|CheckTemplateConfigDrawer|DeviceBridgeHelpDialog)\.vue$/.test(id)) return '\0model-binding-empty'
                if (id === './useLocalDevice') return '\0model-binding-usb'
            },
            load(id) {
                if (id === filename) return component
                if (id === api) return apiFixture
                if (id === '\0model-binding-core') return `export const HsxDataArchive={render:()=>null}`
                if (id === '\0model-binding-empty') return `export default {render:()=>null}`
                if (id === '\0model-binding-usb') return `import {ref} from 'vue'; export function useLocalDevice(){return {
                    fetching:ref(false),readWarnings:ref([]),fetchConnected:async()=>[{imei:'NEXT-SN',serial_number:'NEXT-SN',model:'16th',model_candidates:['16th'],raw:{source:'usb_mtp',platform:'android'}}],
                    mapToRow:value=>value,describeError:error=>error.message,startAuto:()=>{},stopAuto:()=>{}
                }}`
            },
            configureServer(server) {
                server.middlewares.use(async (req, res, next) => {
                    if (req.url.split('?')[0] !== '/__model-binding') return next()
                    res.setHeader('Content-Type', 'text/html; charset=utf-8')
                    res.end(await server.transformIndexHtml(req.url, `<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Model binding test</title><style>body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC",sans-serif}main{padding:24px}*{box-sizing:border-box}</style></head><body><div id="app"></div><script type="module">
                        import {createApp} from 'vue'; import ElementPlus from 'element-plus'; import 'element-plus/dist/index.css';
                        import Preview from 'virtual:model-binding.vue';
                        const app=createApp(Preview);app.component('upload-image',{render:()=>null});app.use(ElementPlus).mount('#app');
                    </script></body></html>`))
                })
            }
        }, vue()],
        optimizeDeps: { entries: [], include: ['vue', 'element-plus', '@element-plus/icons-vue'] }
    })
    let browser
    try {
        await server.listen()
        const origin = `http://127.0.0.1:${server.httpServer.address().port}`
        browser = await chromium.launch({ headless: true, channel: 'chrome' })
        const page = await browser.newPage({ viewport: { width: 1366, height: 900 } })
        const errors = []
        page.on('pageerror', error => errors.push(String(error)))
        page.setDefaultTimeout(12000)
        await page.route('**/*', route => {
            if (new URL(route.request().url()).origin !== origin) throw Error('Unexpected external request: ' + route.request().url())
            return route.continue()
        })
        await page.addInitScript(() => {
            localStorage.setItem('hsx_recycle:model_entry_tip:dismissed:v1', '1')
            window.__bindingFixture = {
                nodes: [
                    {id:1,pid:0,node_name:'手机',has_children:1},
                    {id:2,pid:1,node_name:'魅族',has_children:1},
                    {id:3,pid:2,node_name:'魅族16th',model_full_name:'手机/魅族/魅族16th',has_children:0,category_path:[1,2,3]},
                    {id:4,pid:2,node_name:'魅族16th Plus',model_full_name:'手机/魅族/魅族16th Plus',has_children:0,category_path:[1,2,4]}
                ], binds:[], creates:[], mappings:{}, templates:[], failBinding:location.search.includes('fail=1')
            }
        })
        const openPicker = async (fromEmptySearch = false) => {
            await page.getByRole('button', { name: fromEmptySearch ? '浏览已有型号' : '关联已有型号', exact: true }).first().click()
            await page.locator('.el-cascader-node__label').filter({ hasText: /^手机$/ }).hover()
            await page.locator('.el-cascader-node__label').filter({ hasText: /^魅族$/ }).hover()
            await page.locator('.el-cascader-node__label').filter({ hasText: /^魅族16th$/ }).click()
        }
        await page.goto(origin + '/__model-binding')
        await page.getByRole('button', { name: '关联已有型号', exact: true }).waitFor()
        fs.mkdirSync(output, { recursive: true })
        await page.screenshot({ path: path.join(output, 'unmatched.png'), animations: 'disabled' })
        // A failed search must lead back to browsing existing categories, not to a creation form.
        await page.getByPlaceholder('搜索或逐级选择型号').fill('没有的型号')
        await page.getByText('未找到“没有的型号”', { exact: true }).waitFor()
        await openPicker(true)
        await page.waitForFunction(() => window.__entryRows[0].local_model_resolved_category_id === 3)
        assert.deepEqual(await page.evaluate(() => window.__bindingFixture.binds), [{aliases:['16th'],category_id:3}])
        assert.deepEqual(await page.evaluate(() => window.__bindingFixture.creates), [])
        assert.deepEqual(await page.evaluate(() => window.__entryRows[0].category_path), [1,2,3])
        assert.equal(await page.evaluate(() => window.__bindingFixture.nodes.length), 4)
        await page.screenshot({ path: path.join(output, 'bound.png'), animations: 'disabled' })

        await page.getByRole('button', { name: '读取本地设备', exact: true }).click()
        await page.waitForFunction(() => window.__entryRows.some(row => row.imei === 'NEXT-SN' && row.category_id === 3))
        assert.equal(await page.evaluate(() => window.__bindingFixture.binds.length), 1, 'Next device consumes mapping without creating or rebinding')
        assert.equal(await page.evaluate(() => window.__bindingFixture.creates.length), 0)

        // Selecting the model succeeds even if saving its alias fails, and can be retried.
        await page.goto(origin + '/__model-binding?fail=1')
        await openPicker()
        await page.getByText('型号已选中，但关联未保存', { exact: true }).waitFor()
        assert.equal(await page.evaluate(() => window.__entryRows[0].category_id), 3)
        assert.equal(await page.evaluate(() => window.__entryRows[0].local_model_resolved_category_id || 0), 0)
        await page.screenshot({ path: path.join(output, 'retry.png'), animations: 'disabled' })
        await page.evaluate(() => { window.__bindingFixture.failBinding = false })
        await page.getByRole('button', { name: '重试关联', exact: true }).click()
        await page.waitForFunction(() => window.__entryRows[0].local_model_resolved_category_id === 3)
        assert.equal(await page.getByRole('button', { name: '重试关联', exact: true }).count(), 0)
        assert.equal(await page.evaluate(() => window.__bindingFixture.creates.length), 0)

        // Creation remains a separate explicit action with no write merely from opening it.
        await page.goto(origin + '/__model-binding')
        await page.getByRole('button', { name: '新增型号', exact: true }).click()
        await page.getByRole('dialog', { name: '新增型号', exact: true }).waitFor()
        await page.getByRole('button', { name: '创建型号并使用', exact: true }).waitFor()
        await page.getByRole('button', { name: '取消', exact: true }).click()
        assert.equal(await page.evaluate(() => window.__bindingFixture.creates.length), 0)
        await page.setViewportSize({width:1000,height:800})
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Desktop width does not overflow')
        await page.screenshot({ path: path.join(output, 'compact-desktop.png'), animations: 'disabled' })
        assert.deepEqual(errors, [])
        console.log('PASS browser: existing leaf binding, browse after empty search, next-device matching, retry, separate creation, no catalog/order writes, release copies')
        console.log('Screenshots: ' + output)
    } finally {
        if (browser) await browser.close()
        await server.close()
    }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
