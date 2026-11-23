// Mount the real generic original-document result and real optional addon drawer. No outgoing requests.
import assert from 'node:assert/strict'
import {createRequire} from 'node:module'
import {dirname,resolve} from 'node:path'
import {fileURLToPath,pathToFileURL} from 'node:url'
import {readFileSync} from 'node:fs'
const root=resolve(dirname(fileURLToPath(import.meta.url)),'../../../../admin'), req=createRequire(resolve(root,'package.json'))
const {build}=await import(pathToFileURL(req.resolve('vite/package.json').replace(/package\.json$/,'dist/node/index.js')))
const vue=req('@vitejs/plugin-vue'), {chromium}=req(process.env.PLAYWRIGHT_MODULE||'playwright')
const fixture={id:7,business_type:'payment',source_no:'PAY3',connection:{adapter_name:'隔离测试'},status:'awaiting_confirmation',manual:true,receipt:{},payload:{},can_prepare:false,can_retry:false}
const purchase={task:{id:25,business_no:'LOCAL-DEMO',status:'awaiting_confirmation',revision:1,mode:'test',connection_key:'fixture',external_no:''},source:{lines:[{source_line_id:'purchase_line:11',product_name:'测试机',imei:'TEST-SERIAL-001',amount:'1.00',quantity:1}]},selection:{company_id:'C',supplier_id:'S',creator_id:'U',lines:[{source_line_id:'purchase_line:11',product_id:'P',warehouse_id:'W'}]},active_connection:true,write_enabled:true,editable:true,can_operate:true}
const payment={task:{id:27,status:'registered_review',external_no:'FICT-PAY-27',mode:'test',connection_key:'fixture'},source:{settlement_id:9,source_task_id:25,settlement_no:'LOCAL-PAY',external_purchase_no:'FICT-PO-25',amount:'1.00'},parent_purchase_task_id:25,operator_complete:true,result:{payment_registered:true,allocation_applied_verified:false},labels:{}}
const ids={
    'fixture:entry':`import {createApp,h,ref} from 'vue'; import ep from 'element-plus';
    import {createRouter,createMemoryHistory} from 'vue-router'; import Result from '/src/addon/hsx_erp/components/ErpDocumentSyncResult.vue';
    const r=createRouter({history:createMemoryHistory(),routes:[{path:'/',component:{render:()=>null}}]});
    const rows=ref([]),operator=ref(); window.rows=(v,view)=>{rows.value=v;operator.value=view}; createApp({render:()=>h(Result,{results:rows.value,operatorView:operator.value})}).use(ep).use(r).mount('#app');`,
    '@/utils/request':`const req=async(method,path)=>{const r=await fetch('/fixture/'+path,{method});return {data:await r.json()}};export default {get:p=>req('GET',p),post:p=>req('POST',p)}`,
    '@/stores/modules/user':`export default ()=>({rules:['hsx_erp_integration_task_review']})`
}
const result=await build({configFile:false,root,logLevel:'error',css:{postcss:{plugins:[]}},plugins:[{name:'fixture',enforce:'pre',resolveId(k){if(k in ids)return '\0'+k},load(k){if(k[0]==='\0' && k.slice(1) in ids)return ids[k.slice(1)]}},vue()],
    resolve:{dedupe:['vue','element-plus','vue-router','pinia'],alias:[{find:/^@\/addon\/(.*)/,replacement:root+'/src/addon/$1'}]},
    define:{'process.env.NODE_ENV':'"production"'},build:{write:false,minify:false,rollupOptions:{input:'fixture:entry',output:{format:'iife',inlineDynamicImports:true}}}})
const code=result.output.find(x=>x.type==='chunk').code, css=readFileSync(req.resolve('element-plus/dist/index.css'),'utf8')
const browser=await chromium.launch({executablePath:process.env.BROWSER_PATH||undefined,headless:true,chromiumSandbox:true})
try {
    const errors=[], calls=[], page=await browser.newPage()
    page.setDefaultTimeout(6000)
    page.on('pageerror',e=>errors.push(e.message))
    await page.route('**/*',async route=>{
        const u=new URL(route.request().url()); assert.equal(u.origin,'http://erp-fixture.invalid')
        if(u.pathname==='/')return route.fulfill({contentType:'text/html; charset=utf-8',body:'<meta charset="utf-8"><div id="app"></div><link rel="stylesheet" href="/style.css"><script src="/app.js"></script>'})
        if(u.pathname==='/app.js')return route.fulfill({contentType:'text/javascript; charset=utf-8',body:code})
        if(u.pathname==='/style.css')return route.fulfill({contentType:'text/css',body:css})
        calls.push(route.request().method()+' '+u.pathname)
        if (route.request().method()==='GET' && u.pathname==='/fixture/boyuan/purchases/25') return route.fulfill({json:purchase})
        if (route.request().method()==='GET' && u.pathname==='/fixture/boyuan/payment-tasks/27') return route.fulfill({json:payment})
        if (route.request().method()==='POST') {
            assert.equal(u.pathname,'/fixture/integration/task/review/7','only an explicit local fixture review is allowed')
            Object.assign(fixture,{status:'succeeded',external_doc_no:'V-DEMO-7',receipt:{status:'confirmed',external_no:'V-DEMO-7',vendor_task_id:27,allocation:{verified:false,status:'pending'}}})
        }
        return route.fulfill({json:fixture})
    })
    await page.goto('http://erp-fixture.invalid')
    const original={key:'integration_manual_7',provider_label:'本单付款任务',status_label:'待人工关联',task_id:7,query:{detail_extension:'hsx_erp_integration'}}
    const sibling={key:'sibling',provider_label:'其他扩展',status_label:'可用',task_id:3,pc_route:'/other/workbench',query:{id:'3'}}
    await page.evaluate(rows=>window.rows(rows),[{...original,query:{detail_extension:'../../bad'}},sibling])
    await page.getByText('其他扩展',{exact:true}).waitFor()
    assert.equal(await page.getByText('在本单核对任务').count(),0)
    assert.equal(await page.getByText('查看核对记录').count(),1)
    await page.evaluate(rows=>window.rows(rows),[original,sibling])
    const open=page.getByRole('button',{name:'在本单核对任务'})
    await open.waitFor();assert.deepEqual(calls,[])
    await open.click()
    await page.getByText('隔离测试',{exact:true}).waitFor()
    assert.deepEqual(calls,['GET /fixture/integration/task/7']);assert.equal(page.url(),'http://erp-fixture.invalid/')
    await page.getByRole('button',{name:'关闭',exact:true}).last().click()
    await open.click();await page.getByText('隔离测试',{exact:true}).waitFor()
    assert.deepEqual(calls,['GET /fixture/integration/task/7','GET /fixture/integration/task/7'])
    await page.getByRole('button',{name:'核对原单回执'}).click()
    await page.locator('.document-sync__number').getByText('V-DEMO-7').waitFor()
    await page.locator('.document-sync__summary').getByText('付款单已登记').waitFor()
    await page.locator('.document-sync__row').getByText('尚无核销到原采购的证据',{exact:false}).waitFor()
    assert.deepEqual(calls.slice(2),['POST /fixture/integration/task/review/7','GET /fixture/integration/task/7'])
    await page.evaluate(()=>window.rows([]));await page.getByText('隔离测试',{exact:true}).waitFor({state:'hidden'})
    const native={key:'purchase25',provider_label:'博远采购单',status_label:'待确认',task_id:25,query:{detail_extension:'hsx_boyuan',kind:'purchase',purchase_task_id:'25'}}
    calls.length=0
    await page.evaluate(row=>window.rows([row]),native); await open.click()
    await page.getByRole('button',{name:'同步采购挂账',exact:true}).waitFor()
    await page.getByText('入库资料已齐全',{exact:false}).waitFor()
    await page.getByText('TEST-SERIAL-001',{exact:false}).first().waitFor()
    assert.equal(await page.getByRole('button',{name:'请选择博远供应商'}).count(),0)
    assert.deepEqual(calls,['GET /fixture/boyuan/purchases/25']);assert.equal(page.url(),'http://erp-fixture.invalid/')
    await page.evaluate(()=>window.rows([]))
    Object.assign(purchase,{editable:false,payment:{uat_context:true,task_id:27,status:'registered_review',operator_complete:true}});Object.assign(purchase.task,{status:'succeeded',external_no:'FICT-PO-25'})
    calls.length=0
    await page.evaluate(row=>window.rows([row]),{...native,key:'payment27',query:{detail_extension:'hsx_boyuan',kind:'payment',purchase_task_id:'25',payment_task_id:'27',settlement_id:'9'}}); await open.click()
    await page.getByText('FICT-PAY-27',{exact:true}).waitFor()
    await page.getByText('尚未取得这笔付款冲减原采购的明细。',{exact:false}).waitFor()
    assert.deepEqual(calls,['GET /fixture/boyuan/purchases/25','GET /fixture/boyuan/payment-tasks/27'])
    assert.equal(page.url(),'http://erp-fixture.invalid/')
    const official={...native,status:'succeeded',tone:'success',external_no:'FICT-PO-25'}
    const technical={...original,key:'bridge13',task_id:13,provider_label:'桥采购明细',status:'succeeded',tone:'success',external_no:'FICT-PO-25',
        query:{detail_extension:'hsx_erp_integration',kind:'purchase',receipt_adapter:'boyuan',receipt_vendor_task_id:'25'}}
    const officialPayment={...native,key:'nativePay',provider_label:'原付款已登记',status:'registered',tone:'success',external_no:'FICT-PAY-27',
        query:{detail_extension:'hsx_boyuan',kind:'payment',purchase_task_id:'25',payment_task_id:'27',settlement_id:'9'}}
    const techPay={...technical,key:'bridgePay',provider_label:'桥付款明细',external_no:'FICT-PAY-27',query:{...technical.query,kind:'payment',receipt_vendor_task_id:'27',settlement_id:'9'}}
    await page.evaluate(r=>window.rows(r),[official,technical,officialPayment,techPay,
        {...technical,key:'other',provider_label:'不同厂商任务',query:{...technical.query,receipt_vendor_task_id:'99'}},
        {...techPay,key:'otherVoucher',provider_label:'同金额不同凭证',query:{...techPay.query,settlement_id:'10'}},
        {...technical,key:'unknown',provider_label:'未知适配器',query:{...technical.query,receipt_adapter:'absent'}},
        {...technical,key:'review',status:'blocked',tone:'warning',provider_label:'需要处理'}])
    const fold=page.getByText('查看同步明细（2）',{exact:true}); await fold.waitFor()
    for(const label of ['桥采购明细','桥付款明细']) assert.equal(await page.getByText(label,{exact:true}).isVisible(),false)
    for(const label of ['原付款已登记','不同厂商任务','同金额不同凭证','未知适配器','需要处理']) assert.equal(await page.getByText(label,{exact:true}).isVisible(),true)
    await fold.click(); await page.getByText('桥付款明细',{exact:true}).waitFor()
    calls.length=0
    await page.evaluate(r=>window.rows(r,'payable'),[officialPayment,techPay])
    await page.getByRole('button',{name:'查看付款回执',exact:true}).click()
    await page.getByRole('dialog',{name:'付款回执'}).getByText('FICT-PAY-27',{exact:true}).waitFor()
    assert.equal(await page.getByRole('button',{name:'同步已有付款凭证',exact:true}).count(),0)
    assert.deepEqual(calls,['GET /fixture/boyuan/purchases/25','GET /fixture/boyuan/payment-tasks/27'])
    assert.deepEqual(errors,[])
    console.log('PASS: original record drawer opens in place with GET only; explicit fixture review refreshes the original row without claiming settlement, missing extension hidden, sibling works, stale drawer cleared.')
} finally { await browser.close() }
