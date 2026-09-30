'use strict'
// Actual Vue / Element Plus rendering with isolated in-memory API fixtures.
// No application accounts, database or carrier endpoints are used.
const fs = require('node:fs'), path = require('node:path'), http = require('node:http'), assert = require('node:assert/strict'), crypto = require('node:crypto')
const root = path.resolve(__dirname, '../../../..'), base = path.join(root, 'niucloud/addon/hsx_express/admin'), deps = path.join(root, 'admin/node_modules')
const sfc = require(path.join(deps, '@vue/compiler-sfc')), esbuild = require(path.join(deps, 'esbuild')), sass = require(path.join(deps, 'sass'))
const { chromium } = require(process.env.PLAYWRIGHT_PACKAGE || '/Users/a123/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright')
const out = process.env.EXPRESS_QA_OUTPUT || '/tmp/hsx-express-browser-qa'
const shopPage = path.join(root, 'niucloud/addon/phone_shop/admin/views/delivery/electronic_sheet_config.vue')
const shopLang = { ...JSON.parse(fs.readFileSync(path.join(root, 'niucloud/addon/phone_shop/admin/lang/zh-cn/delivery.electronic_sheet_config.json'), 'utf8')), save: '保存' }
// 使用后端实际输出的公开字段定义，不在前端夹具里另维护一套承运商规则。
const carrierOptions = JSON.parse(require('node:child_process').execFileSync(process.env.PHP_BINARY || 'php', ['-r',
    'require $argv[1]; echo json_encode(\\addon\\hsx_express\\app\\service\\core\\WaybillCarrierCatalog::options(), JSON_UNESCAPED_UNICODE);',
    path.join(base, '../app/service/core/WaybillCarrierCatalog.php')], { encoding: 'utf8' }))

function compile(file) {
    const { descriptor: d, errors } = sfc.parse(fs.readFileSync(file, 'utf8'), { filename: file }); assert.deepEqual(errors, [])
    const id = crypto.createHash('sha256').update(file).digest('hex').slice(0, 8)
    const script = sfc.compileScript(d, { id })
    const template = sfc.compileTemplate({ source: d.template.content, filename: file, id, scoped: d.styles.some(style => style.scoped), compilerOptions: { bindingMetadata: script.bindings, expressionPlugins: ['typescript'] } }); assert.deepEqual(template.errors, [])
    const styles = d.styles.map(style => {
        const source = style.lang === 'scss' ? sass.compileString(style.content, { loadPaths: [path.dirname(file)] }).css : style.content
        const result = sfc.compileStyle({ source, id: 'data-v-' + id, scoped: style.scoped }); assert.deepEqual(result.errors, []); return result.code
    }).join('\n')
    return script.content.replace('export default', 'const __component =') + '\n' + template.code + '\n__component.render=render;__component.__scopeId=' + JSON.stringify('data-v-' + id) + ';export default __component;\nconst style=document.createElement("style");style.textContent=' + JSON.stringify(styles) + ';document.head.appendChild(style);'
}

const apiFixture = `
const state = window.qa;
export async function getExpressConfig(){return {data: JSON.parse(JSON.stringify(state.config))}}
export async function saveExpressConfig(data){state.saves.push(data); if(state.failSave){state.failSave=false;throw {message:'模拟保存失败，请重试'}} state.config={...state.config,...data,has_key:true,has_secret:true,has_partner_key:true,has_partner_secret:true};return {data:state.config}}
export async function checkExpressConfig(){state.checks++;return {data:state.config.readiness}}
export async function getExpressTasks(){return {data:{data:state.tasks,total:state.tasks.length}}}
export async function getExpressTask(id){return {data:state.tasks.find(row=>row.id===id)}}
export async function reprintExpressTask(id){state.reprints.push(id);if(state.failReprint){state.failReprint=false;throw {message:'模拟补打请求待核实'}}return {data:state.tasks.find(row=>row.id===id)}}
export async function recoverExpressTask(id){state.recovers.push(id);const row=state.tasks.find(row=>row.id===id);row.state='ready';row.waybill_no='TEST-RECOVERED';row.can_recover=false;return {data:row}}
export async function cancelExpressTask(id,reason){state.cancels.push({id,reason});const row=state.tasks.find(row=>row.id===id);row.state='cancelled';row.can_cancel=false;row.can_reprint=false;return {data:row}}
`

const config = {
    provider: 'kuaidi100', enabled: 0, scene: 'waybill_web', key: '******', secret: '******', has_key: true, has_secret: true,
    carrier: 'shunfeng', exp_type: '顺丰标快', partner_id: 'fixture-account', partner_name: '', partner_key: '******', partner_secret: '******', has_partner_key: true, has_partner_secret: true,
    pay_type: 'MONTHLY', template_id: 'fixture-template', device_id: '', callback_base_url: 'https://example.test', use_ack: 1,
    readiness: { ready: false, checks: [{ key: 'device', label: '云打印设备', passed: false, message: '模拟：请填写已绑定的设备码' }], external_verified: false },
    options: { carriers: carrierOptions, catalog: { checked_at: '2026-09-30', source: 'https://api.kuaidi100.com/document/5f0ff6e82977d50a94e10237' } }
}
const tasks = [
    { id: 1, state: 'unknown', task_no: 'TEST-UNKNOWN', business_type: 'phone_shop', business_id: '100:fixture', business_no:'ORDER-100', carrier: 'shunfeng', print_type: 'CLOUD', waybill_no: '', label: '', can_reprint: false, can_cancel: false, can_recover:true, message: '请求结果待核实，请勿重复取号', create_at: 1790726400, logs: [] },
    { id: 2, state: 'ready', task_no: 'TEST-READY', business_type: 'phone_shop', business_id: '101:fixture', business_no:'ORDER-101', carrier: 'zhongtong', carrier_name: '中通快递', exp_type: '中通标快', print_type: 'IMAGE', waybill_no: 'TEST-WAYBILL', provider_task_id: 'provider-task-fixture', label: 'https://api.kuaidi100.com/label/fixture', can_reprint: true, can_cancel: true, create_at: 1790726400, logs: [{ operation: 'create', at: 1790726400, message: '取号结果已确认' }] },
    { id: 3, state: 'cancel_unknown', task_no: 'TEST-CANCEL-UNKNOWN', business_type: 'phone_shop', business_id: '102:fixture', business_no:'ORDER-102', carrier: 'shunfeng', print_type: 'CLOUD', waybill_no: 'TEST-3', label: '', can_reprint: false, can_cancel: false, can_recover:false, create_at: 1790726400, logs: [] }
]
const shopConfig = { interface_type: 'kdbird', kdniao_id: '', kdniao_api_key: '', server_port1: '8000', server_port2: '18000', https_port: '8443', providers: [
    { key: 'kdbird', label: '快递鸟（原有方式）', external: false },
    { key: 'hsx_express_kuaidi100', label: '快递100（物流服务）', external: true, config_url: '/hsx_express/config', description: '先取号、打印面单，实际交件后再确认发货。' }
] }
const shopFixture = `export async function getElectronicSheetConfig(){if(window.qa.failShopLoad){window.qa.failShopLoad=false;throw {message:'模拟商城接口暂不可用'}}return {data:JSON.parse(JSON.stringify(window.qa.shopConfig))}};export async function setElectronicSheetConfig(data){window.qa.shopSaves.push(JSON.parse(JSON.stringify(data)));window.qa.shopConfig={...window.qa.shopConfig,...data};return {data:true}};`

async function main() {
    fs.mkdirSync(out, { recursive: true })
    const result = await esbuild.build({ stdin: { contents: `import {createApp,h,ref} from 'vue';import ElementPlus from 'element-plus';import 'element-plus/dist/index.css';import Config from ${JSON.stringify(path.join(base, 'views/config/index.vue'))};import Tasks from ${JSON.stringify(path.join(base, 'views/tasks/index.vue'))};import ShopConfig from ${JSON.stringify(shopPage)};createApp({setup(){const tab=ref('config');return()=>h('main',[h('nav',[h('button',{'data-testid':'qa-config',onClick:()=>tab.value='config'},'配置测试'),h('button',{'data-testid':'qa-tasks',onClick:()=>tab.value='tasks'},'任务测试'),h('button',{'data-testid':'qa-shop',onClick:()=>tab.value='shop'},'商城配置测试')]),tab.value==='config'?h(Config):tab.value==='shop'?h(ShopConfig):h(Tasks)])}}).use(ElementPlus).mount('#app');`, resolveDir: path.join(root, 'admin'), loader: 'js' },
        bundle: true, write: false, outfile: path.join(out, 'app.js'), nodePaths: [deps], platform: 'browser', define: { 'process.env.NODE_ENV': '"production"', __VUE_OPTIONS_API__: 'true', __VUE_PROD_DEVTOOLS__: 'false' },
        plugins: [{ name: 'isolated-express', setup(build) {
            build.onResolve({ filter: /^\.\.\/\.\.\/api$/ }, args => args.importer.startsWith(base) ? ({ path: 'api', namespace: 'fixture' }) : null)
            build.onResolve({ filter: /^@\/addon\/phone_shop\/api\/electronic_sheet$/ }, () => ({ path: 'shop-api', namespace: 'fixture' }))
            build.onResolve({ filter: /^@\/lang$/ }, () => ({ path: 'lang', namespace: 'fixture' }))
            build.onResolve({ filter: /^vue-router$/ }, () => ({ path: 'router', namespace: 'fixture' }))
            build.onLoad({ filter: /.*/, namespace: 'fixture' }, args => ({ contents: args.path === 'api' ? apiFixture : args.path === 'shop-api' ? shopFixture : args.path === 'lang' ? `const labels=${JSON.stringify(shopLang)};export const t=key=>labels[key]||key;` : `export const useRouter=()=>({push:path=>window.qa.routes.push(path),resolve:({path})=>({href:path})});export const useRoute=()=>({meta:{title:'电子面单设置'}});`, loader: 'js' }))
            build.onResolve({ filter: /^@\/addon\/hsx_components\/core$/ }, () => ({ path: 'core', namespace: 'hsx-core' }))
            build.onLoad({ filter: /.*/, namespace: 'hsx-core' }, () => ({ contents: 'import '+JSON.stringify(path.join(root,'admin/src/addon/hsx_components/styles/theme.scss'))+';\n'+['HsxPage','HsxDrawer','HsxNotice','HsxFold'].map(name => 'export {default as '+name+'} from '+JSON.stringify(path.join(root,'admin/src/addon/hsx_components/components',name,'index.vue'))+';').join('\n'), loader: 'js', resolveDir: root }))
            build.onResolve({ filter: /^@\// }, args => {
                const file = path.join(root, 'admin/src', args.path.slice(2))
                const resolved = [file, file+'.ts', file+'.js', path.join(file,'index.ts')].find(candidate => fs.existsSync(candidate) && fs.statSync(candidate).isFile())
                return resolved ? { path: resolved } : null
            })
            build.onLoad({ filter: /\.vue$/ }, args => ({ contents: compile(args.path), loader: 'ts', resolveDir: path.dirname(args.path) }))
            build.onLoad({ filter: /\.scss$/ }, args => ({ contents: sass.compile(args.path).css, loader: 'css' }))
        } }] })
    const assets = Object.fromEntries(result.outputFiles.map(file => ['/'+path.basename(file.path),file.contents]))
    const server = http.createServer((req,res) => {
        if(assets[req.url]) {res.setHeader('Content-Type',req.url.endsWith('.css')?'text/css':'application/javascript');res.end(assets[req.url]);return}
        res.setHeader('Content-Type','text/html;charset=utf-8');res.end('<html lang="zh"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/app.css"><style>body{margin:0;background:#f5f7fa;font-family:Arial,"PingFang SC",sans-serif}main{padding:18px;max-width:1280px;margin:auto;min-width:0}nav{margin-bottom:12px}*,*:before,*:after{box-sizing:border-box}</style><body><div id="app"></div><script>window.qa='+JSON.stringify({ config, tasks, shopConfig, shopSaves:[], saves:[], checks:0, reprints:[], recovers:[], cancels:[], routes:[] })+'</script><script src="/app.js"></script></body></html>')
    })
    await new Promise(resolve => server.listen(0,'127.0.0.1',resolve))
    let browser; const errors=[]
    try {
        browser=await chromium.launch({headless:true,channel:process.env.TEST_BROWSER_CHANNEL||'chrome',args:['--no-proxy-server']})
        const page=await browser.newPage({viewport:{width:1280,height:800}, reducedMotion:'reduce'})
        page.on('pageerror',e=>errors.push(e.message))
        await page.route('**/*', route => new URL(route.request().url()).hostname==='127.0.0.1' ? route.continue() : route.abort())
        const url='http://127.0.0.1:'+server.address().port
        assert.equal((await fetch(url)).status,200,'local fixture server ready')
        await page.goto(url)
        await page.addStyleTag({content:'*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}'})
        await page.getByRole('heading',{name:'选择起步方案'}).waitFor()
        assert.equal(await page.locator('input[type=password]').first().inputValue(),'','masked secret is never posted back as actual credential')
        assert.equal(await page.locator('input[placeholder="复制已绑定设备的 SIID"]').count(),0)
        await page.getByRole('button',{name:/已有兼容设备.*云打印面单/}).click()
        await page.locator('input[placeholder="复制已绑定设备的 SIID"]').waitFor()
        await page.getByRole('button',{name:/建议先从这里开始.*电脑打印面单/}).click()
        assert.equal(await page.locator('input[placeholder="复制已绑定设备的 SIID"]').count(),0)
        await page.evaluate(()=>qa.failSave=true)
        await page.getByRole('button',{name:'保存并检查配置',exact:true}).click()
        await page.getByText('模拟保存失败，请重试',{exact:true}).first().waitFor()
        await page.getByRole('button',{name:'保存并检查配置',exact:true}).click()
        await page.waitForFunction(()=>qa.checks===1)
        assert.equal(await page.evaluate(()=>qa.saves.at(-1).secret),'')
        const chooseOption = async (field, name) => {
            const select = page.locator('.el-select').filter({has: page.locator('input[aria-label="'+field+'"]')})
            await select.click()
            await page.locator('.el-select-dropdown__item:visible').filter({hasText:new RegExp('^'+name+'$')}).click()
        }
        const chooseCarrier = name => chooseOption('快递公司', name)
        await chooseCarrier('中通快递')
        await page.getByRole('heading',{name:'中通快递 · 网点电子面单资料'}).waitFor()
        const account = page.getByTestId('carrier-account-fields')
        assert.equal(await account.locator('input').count(),3,'ZTO only needs own account, key, station')
        assert.equal(await page.locator('input[aria-label="顾客编码"]').count(),0,'no SF customer code field for ZTO')
        assert.equal(await page.locator('input[aria-label="校验码"]').count(),0,'no SF secret field for ZTO')
        assert.equal(await page.locator('input[aria-label="电子面单账号"]').inputValue(),'','old SF account not reused')
        assert.equal(await page.locator('input[placeholder="从快递100模板管理复制 ID"]').inputValue(),'','old template not reused')
        await page.locator('input[aria-label="电子面单账号"]').fill('fixture-zto-account')
        await page.locator('input[aria-label="打单密钥"]').fill('fixture-zto-key')
        await page.locator('input[aria-label="网点编码"]').fill('fixture-zto-net')
        await chooseOption('快递产品', '中通标快')
        await page.locator('input[placeholder="从快递100模板管理复制 ID"]').fill('fixture-zto-template')
        await page.getByText('接口固定标识由系统自动填写：',{exact:false}).waitFor()
        await page.getByRole('button',{name:'保存并检查配置',exact:true}).click()
        await page.waitForFunction(()=>qa.saves.at(-1)?.carrier==='zhongtong')
        const submitted = await page.evaluate(()=>qa.saves.at(-1))
        assert.equal(submitted.net,'fixture-zto-net')
        assert.equal(submitted.exp_type,'中通标快')
        assert.equal(submitted.template_id,'fixture-zto-template')
        assert.equal(submitted.partner_secret,'')
        assert.equal(submitted.use_ack,0)
        assert.equal(submitted.enabled,0)
        await chooseCarrier('顺丰速运')
        assert.equal(await page.locator('input[aria-label="网点编码"]').count(),0,'ZTO station disappears for SF')
        assert.equal(await page.locator('input[aria-label="月结账号"]').inputValue(),'','ZTO account never becomes SF account')
        await chooseCarrier('中通快递')
        await page.waitForFunction(()=>document.querySelectorAll('.el-message').length===0)
        for(const width of [1280,1024,800,390]) {
            await page.setViewportSize({width,height:800})
            await page.waitForTimeout(150)
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'config viewport overflow '+width)
            if([1280,800].includes(width)) await page.screenshot({path:path.join(out,'config-'+width+'.png'),fullPage:true,animations:'disabled'})
        }
        await page.setViewportSize({width:1280,height:800})
        await page.getByTestId('qa-tasks').click()
        await page.getByText('手机商城 · ORDER-100',{exact:true}).first().waitFor()
        const unknown=page.locator('.el-table__row').filter({hasText:'ORDER-100'}).first()
        assert.equal(await unknown.getByRole('button',{name:'补打原单'}).count(),0)
        assert.equal(await unknown.getByRole('button',{name:'取消运单'}).count(),0)
        await unknown.getByRole('button',{name:'恢复原申请',exact:true}).click()
        await page.getByRole('button',{name:'先人工核实',exact:true}).click()
        assert.equal(await page.evaluate(()=>qa.recovers.length),0,'recovery cancelled without requesting')
        const unresolvedCancel=page.locator('.el-table__row').filter({hasText:'ORDER-102'}).first()
        assert.equal(await unresolvedCancel.getByRole('button',{name:'恢复原申请',exact:true}).count(),0)
        await unknown.getByRole('button',{name:'恢复原申请',exact:true}).click()
        await page.getByRole('button',{name:'确认恢复，知悉可能计费',exact:true}).click()
        await page.waitForFunction(()=>qa.recovers.length===1)
        await page.locator('.el-drawer').getByText('TEST-RECOVERED',{exact:true}).waitFor()
        await page.keyboard.press('Escape')
        await page.locator('.el-drawer').waitFor({state:'hidden'})
        const ready=page.locator('.el-table__row').filter({hasText:'ORDER-101'}).first()
        assert.ok((await ready.innerText()).includes('中通快递'),'carrier label comes from catalog, not a SF/JD-only switch')
        await ready.getByRole('button',{name:'补打原单'}).click()
        await page.getByRole('button',{name:'先检查打印机',exact:true}).click()
        assert.equal(await page.evaluate(()=>qa.reprints.length),0)
        await ready.getByRole('button',{name:'补打原单'}).click()
        await page.getByRole('button',{name:'已检查，确认补打',exact:true}).click()
        await page.waitForFunction(()=>qa.reprints.length===1)
        assert.deepEqual(await page.evaluate(()=>qa.reprints),[2])
        await page.locator('.el-drawer').getByText('ORDER-101',{exact:true}).waitFor()
        await page.locator('.el-drawer').getByText('中通快递 · 中通标快',{exact:true}).waitFor()
        assert.equal(await page.locator('.el-drawer a[href="https://api.kuaidi100.com/label/fixture"]').count(),1)
        await page.locator('.el-message-box').waitFor({state:'hidden'})
        await page.waitForTimeout(350)
        await page.screenshot({path:path.join(out,'task-detail-1280.png'),fullPage:true,animations:'disabled'})
        await page.keyboard.press('Escape')
        await page.locator('.el-drawer').waitFor({state:'hidden'})
        await page.getByTestId('qa-shop').click()
        await page.getByText('快递100（物流服务）',{exact:true}).click()
        await page.getByRole('button',{name:'打开物流服务配置',exact:true}).waitFor()
        assert.equal(await page.getByPlaceholder('请输入快递鸟用户ID').count(),0,'selecting external provider hides KdBird credentials')
        assert.equal(await page.getByRole('heading',{name:'打印机设置',exact:true}).count(),0,'external provider does not require KdBird Lodop ports')
        await page.getByRole('button',{name:'保存',exact:true}).click()
        await page.waitForFunction(()=>qa.shopSaves.length===1)
        assert.equal(await page.evaluate(()=>qa.shopSaves[0].interface_type),'hsx_express_kuaidi100','save chosen provider through mall API')
        await page.waitForFunction(()=>document.querySelectorAll('.el-message').length===0)
        await page.screenshot({path:path.join(out,'shop-kuaidi100-config.png'),fullPage:true,animations:'disabled'})
        await page.evaluate(()=>{qa.shopConfig.interface_type='kdbird';delete qa.shopConfig.providers})
        await page.getByTestId('qa-config').click();await page.getByTestId('qa-shop').click()
        await page.getByText('商城接口尚未完成升级',{exact:true}).waitFor()
        assert.equal(await page.getByRole('radio',{name:'快递100（物流服务）',exact:true}).count(),0,'old backend is not presented as usable integration')
        await page.screenshot({path:path.join(out,'shop-backend-outdated.png'),fullPage:true,animations:'disabled'})
        await page.evaluate(()=>{qa.shopConfig.providers=[{key:'kdbird',label:'快递鸟（原有方式）',external:false}]})
        await page.getByRole('button',{name:'重新检查服务商',exact:true}).click()
        await page.getByText('暂未发现可用的物流插件',{exact:true}).waitFor()
        await page.evaluate(()=>{qa.failShopLoad=true})
        await page.getByRole('button',{name:'重新检查服务商',exact:true}).click()
        await page.getByText('电子面单配置读取失败',{exact:true}).waitFor()
        assert.equal(await page.getByRole('button',{name:'保存',exact:true}).isEnabled(),false,'failed load cannot overwrite previous settings')
        await page.getByRole('button',{name:'重新读取配置',exact:true}).click()
        await page.getByText('暂未发现可用的物流插件',{exact:true}).waitFor()
        assert.equal(await page.getByRole('button',{name:'保存',exact:true}).isEnabled(),true,'failed load has a working retry path')
        assert.deepEqual(errors,[],'no Vue runtime or unhandled promise errors')
        console.log('PASS: real Vue/ElementPlus rendering; ZTO config, carrier isolation, tasks and responsive layout; mall Kuaidi100 selection/save, outdated backend warning, missing extension hint and read-failure retry. No external IO.')
        console.log('Screenshots: '+out)
    } finally { if(browser)await browser.close();await new Promise(resolve=>server.close(resolve)) }
}
main().catch(error=>{console.error(error);process.exitCode=1})
