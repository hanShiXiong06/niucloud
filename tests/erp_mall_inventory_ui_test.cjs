'use strict'
// 编译真实插件抽屉和通用组件，以隔离接口验证交互；不访问任何生产接口。
const fs = require('node:fs'), path = require('node:path'), http = require('node:http'), assert = require('node:assert/strict'), crypto = require('node:crypto')
const root = path.resolve(__dirname, '..'), deps = path.join(root, 'admin/node_modules')
const esbuild = require(path.join(deps, 'esbuild')), sfc = require(path.join(deps, '@vue/compiler-sfc'))
const { chromium } = require(process.env.PLAYWRIGHT_PACKAGE || '/Users/a123/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright')
const component = path.join(root, 'niucloud/addon/hsx_erp/admin/components/ErpMallInventoryDrawer.vue')
const shared = path.join(root, 'niucloud/addon/hsx_components/admin'), out = '/tmp/hsx-erp-mall-ui'
const api = `
const base={sku_name:'256G',quantity:1,stock:1,imei:'357465822199406',identity_source:'当前商城商品',cost_price:6000,erp_cost:6100,preview_token:'preview-test'};
const t=window.__mallTest;
let stock=[{...base,sku_id:1,goods_name:'可关联测试设备',state:'match',message:'复用ERP原资产和原付款'}, {...base,sku_id:2,goods_name:'期初测试设备',state:'opening',erp_cost:null,message:'确认自有期初，不生成采购应付'}, {...base,sku_id:3,goods_name:'重复串号测试',state:'conflict',message:'串号有多条ERP记录，请先核对'}, {...base,sku_id:4,goods_name:'已有映射测试',state:'linked',message:'已关联'}];
let sold=[{...base,sku_id:5,sale_item_id:55,sale_order_id:66,source_no:'MALL-ONLINE-001',goods_name:'线上成交待核对',state:'match',message:'已收款，只补库存关联'}];
export async function previewErpMallInventory(params) { t.previews.push(params);if(t.failLoad){t.failLoad=false;throw {msg:'隔离测试：预览连接失败'}};const rows=params.scope==='sold'?sold:stock;return {data:{data:rows,total:rows.length,summary:rows.reduce((a,r)=>(a[r.state]=(a[r.state]||0)+1,a),{})}} }
export async function confirmErpMallInventory(params) { t.posts.push(params);await new Promise(r=>setTimeout(r,100));if(t.failSave){t.failSave=false;throw {msg:'隔离测试：连接中断'}};const ids=params.items.map(i=>i.sku_id);stock=stock.map(r=>ids.includes(r.sku_id)?{...r,state:'linked',message:'已关联，不重复建账'}:r);sold=sold.filter(r=>!ids.includes(r.sku_id));return {data:{success:params.items.length,failed:0,results:params.items.map(i=>({sku_id:i.sku_id,state:'completed',message:'已关联，原收付款记录未改变'}))}} }
export async function getErpWarehouseOptions() {if(t.failWarehouse){t.failWarehouse=false;throw {msg:'隔离测试：仓库权限不足'}};return {data:[{id:10,status:1,warehouse_name:'测试仓库',allow_direct_sale:1,locations:[{id:11,location_name:'测试库位'}]}]}}
`
const bootstrap = `
import {createApp,h,ref} from 'vue';import ElementPlus from 'element-plus';import 'element-plus/dist/index.css';
import Drawer from ${JSON.stringify(component)};
createApp({setup(){const visible=ref(false);return()=>h('main',[h('button',{id:'open',onClick:()=>visible.value=true},'打开库存对账'),h(Drawer,{modelValue:visible.value,'onUpdate:modelValue':v=>visible.value=v})])}}).use(ElementPlus).mount('#app');
`
async function main() {
    fs.mkdirSync(out, { recursive: true })
    await esbuild.build({stdin:{contents:bootstrap,resolveDir:path.join(root,'admin'),loader:'js'},bundle:true,outfile:path.join(out,'test.js'),platform:'browser',nodePaths:[deps],define:{'process.env.NODE_ENV':'"production"',__VUE_OPTIONS_API__:'true',__VUE_PROD_DEVTOOLS__:'false'},plugins:[{name:'real-plugin-components',setup(build){
        build.onResolve({filter:/@\/addon\/hsx_components\/core$/},()=>({path:'core',namespace:'fixture'}))
        build.onResolve({filter:/@\/addon\/hsx_erp\/api\//},()=>({path:'api',namespace:'fixture'}))
        build.onLoad({filter:/.*/,namespace:'fixture'},args=>({contents:args.path==='api'?api:`export {default as HsxDrawer} from ${JSON.stringify(path.join(shared,'components/HsxDrawer/index.vue'))};export {default as HsxNotice} from ${JSON.stringify(path.join(shared,'components/HsxNotice/index.vue'))};export {useFeedback} from ${JSON.stringify(path.join(shared,'hooks/useFeedback.ts'))}`,loader:'ts',resolveDir:shared}))
        build.onLoad({filter:/\.vue$/},args=>{
            const {descriptor:d,errors}=sfc.parse(fs.readFileSync(args.path,'utf8'),{filename:args.path});assert.deepEqual(errors,[])
            const id=crypto.createHash('sha256').update(args.path).digest('hex').slice(0,8)
            const script=sfc.compileScript(d,{id}),template=sfc.compileTemplate({source:d.template.content,filename:args.path,id,compilerOptions:{bindingMetadata:script.bindings,expressionPlugins:['typescript']}});assert.deepEqual(template.errors,[])
            const styles=d.styles.map(s=>sfc.compileStyle({source:s.content,id:'data-v-'+id,scoped:s.scoped}).code).join('\n')
            return {contents:script.content.replace('export default','const __component =')+'\n'+template.code+'\n__component.render=render;__component.__scopeId='+JSON.stringify('data-v-'+id)+';export default __component;\nconst style=document.createElement("style");style.textContent='+JSON.stringify(styles)+';document.head.appendChild(style);',loader:'ts',resolveDir:path.dirname(args.path)}
        })
    }}]})
    const server=http.createServer((req,res)=>{
        if(['/test.js','/test.css'].includes(req.url)){res.setHeader('Content-Type',req.url.endsWith('.css')?'text/css':'application/javascript');fs.createReadStream(path.join(out,req.url)).pipe(res);return}
        res.setHeader('Content-Type','text/html;charset=utf-8')
        res.end('<html lang="zh"><meta charset="utf-8"><link rel="stylesheet" href="/test.css"><style>:root{--hsx-text-primary:#172033;--hsx-text-regular:#475569;--hsx-text-secondary:#64748b;--hsx-bg-surface:#fff;--hsx-bg-muted:#f8fafc;--hsx-border-color:#e2e8f0;--hsx-radius-sm:8px;--hsx-color-primary:#2563eb;--hsx-color-success:#16a34a;--hsx-color-warning:#d97706;--hsx-color-danger:#dc2626}body{margin:0;background:#f1f5f9}</style><body><div id="app"></div><script>window.__mallTest={posts:[],previews:[],failLoad:false,failSave:false}</script><script src="/test.js"></script></body></html>')
    })
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve))
    let browser
    try {
        browser=await chromium.launch({headless:true,channel:process.env.TEST_BROWSER_CHANNEL || 'chrome'})
        const page=await browser.newPage({viewport:{width:1280,height:720}}), errors=[]
        page.on('pageerror',error=>errors.push(error.message))
        await page.route('**/*',route=>new URL(route.request().url()).hostname==='127.0.0.1'?route.continue():route.abort())
        await page.goto('http://127.0.0.1:'+server.address().port)
        await page.evaluate(()=>{window.__mallTest.failWarehouse=true})
        await page.locator('#open').click();await page.getByText('可关联测试设备 256G',{exact:true}).waitFor()
        const checks=page.locator('.el-table__body input[type=checkbox]')
        assert.equal(await checks.count(),4)
        await page.waitForTimeout(350)
        await page.screenshot({path:path.join(out,'inventory-1280.png'),fullPage:true})
        assert.ok(await checks.nth(2).isDisabled(),'冲突不可选择');assert.ok(await checks.nth(3).isDisabled(),'已关联不可重复操作')
        await page.locator('.el-table__body label.el-checkbox').first().click();await page.getByRole('button',{name:'确认处理 1 台',exact:true}).click()
        await page.getByRole('button',{name:'再核对一下',exact:true}).click()
        assert.equal(await page.evaluate(()=>window.__mallTest.posts.length),0,'取消确认绝不发送写入')
        await page.getByRole('button',{name:'确认处理 1 台',exact:true}).click();await page.getByRole('button',{name:'确认处理',exact:true}).click()
        await page.getByText('本次成功 1 台，失败 0 台',{exact:true}).waitFor()
        assert.equal(await page.evaluate(()=>window.__mallTest.posts[0].items[0].action),'link')
        await page.locator('.el-table__body label.el-checkbox').nth(1).click();await page.getByRole('button',{name:'确认处理 1 台',exact:true}).click()
        await page.getByText('期初建账请先选择仓库、库位和期初日期',{exact:true}).waitFor()
        assert.equal(await page.evaluate(()=>window.__mallTest.posts.length),1,'缺少期初信息不提交')
        await page.getByText('期初仓库暂不可用',{exact:true}).waitFor()
        assert.ok(await page.getByText(/仓库权限不足/).isVisible(),'仓库查询失败不能静默变成空下拉')
        await page.getByRole('button',{name:'重新获取仓库',exact:true}).click()
        await page.getByText('期初仓库暂不可用',{exact:true}).waitFor({state:'hidden'})
        const form=page.locator('.opening-form')
        await form.locator('.el-select').first().click();await page.getByRole('option',{name:'测试仓库',exact:true}).click()
        await form.locator('.el-select').nth(1).click();await page.getByRole('option',{name:'测试库位',exact:true}).click()
        await form.locator('.el-date-editor input').fill('2020-01-01');await form.locator('.el-date-editor input').press('Enter')
        await page.evaluate(()=>{window.__mallTest.failSave=true})
        await page.getByRole('button',{name:'确认处理 1 台',exact:true}).click();await page.getByRole('button',{name:'确认处理',exact:true}).click()
        await page.getByText('暂未获取处理结果',{exact:true}).waitFor()
        assert.ok(await page.getByText(/已成功关联的设备不会重复建账/).isVisible(),'网络失败说明如何安全核对')
        await page.getByRole('button',{name:'确认处理 1 台',exact:true}).click();await page.getByRole('button',{name:'确认处理',exact:true}).click()
        await page.waitForFunction(()=>window.__mallTest.posts.length===3)
        await page.waitForFunction(()=>document.querySelector('.el-drawer__footer button:last-child').textContent.includes('确认处理 0 台'))
        const sent=await page.evaluate(()=>window.__mallTest.posts[2]);assert.equal(sent.items[0].action,'opening');assert.equal(sent.warehouse_id,10);assert.equal(sent.location_id,11);assert.ok(sent.opening_at>0)
        await page.getByText('已成交待核对',{exact:true}).click();await page.getByText('线上成交待核对 256G',{exact:true}).waitFor()
        await page.locator('.el-table__body label.el-checkbox').first().click()
        await page.getByRole('button',{name:'确认处理 1 台',exact:true}).click();await page.getByRole('button',{name:'确认处理',exact:true}).click()
        await page.waitForFunction(()=>window.__mallTest.posts.length===4)
        assert.equal(await page.evaluate(()=>window.__mallTest.posts[3].items[0].sale_order_id),66,'无应收也能按销售单核对')
        await page.waitForFunction(()=>document.querySelector('.el-drawer__footer button:last-child').textContent.includes('确认处理 0 台'))
        await page.getByText('有库存待关联',{exact:true}).click()
        await page.getByText('可关联测试设备 256G',{exact:true}).waitFor()
        await page.waitForTimeout(350)
        for(const width of [1280,960,640]) {await page.setViewportSize({width,height:720});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'页面不得横向溢出 '+width)}
        await page.setViewportSize({width:1280,height:720})
        await page.evaluate(()=>{window.__mallTest.failLoad=true});await page.getByRole('button',{name:'查询 / 重新预览',exact:true}).click();await page.getByText('预览未完成',{exact:true}).waitFor()
        assert.deepEqual(errors,[])
        console.log('PASS UI: 取消不写入、重复/冲突禁选、期初必填、仓库失败提示及重试、确认载荷、网络中断提示、安全重试、线上成交入口、预览失败提示；1280/960/640 无页面横向溢出。')
        console.log('Screenshot: '+path.join(out,'inventory-1280.png'))
    } finally {if(browser)await browser.close();await new Promise(resolve=>server.close(resolve))}
}
main().catch(error=>{console.error(error);process.exitCode=1})
