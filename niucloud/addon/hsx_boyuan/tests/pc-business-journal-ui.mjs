// Real business menu metadata + real mounted PC page and journal components. Backend responses are
// synthetic; no HTTP server is started, all browser requests are intercepted, no existing profile.
import assert from 'node:assert/strict'
import {execFileSync} from 'node:child_process'
import {readFileSync} from 'node:fs'
import {createRequire} from 'node:module'
import {resolve,dirname} from 'node:path'
import {fileURLToPath,pathToFileURL} from 'node:url'
const addon=resolve(dirname(fileURLToPath(import.meta.url)),'..'), root=resolve(addon,'../../../admin')
const req=createRequire(resolve(root,'package.json')), origin='http://boyuan-menu-fixture.invalid'
const menuTree=JSON.parse(execFileSync(process.env.PHP_BINARY || 'php',['-n','-r','echo json_encode(require $argv[1]);',resolve(addon,'app/dict/menu/site.php')],{encoding:'utf8'}))
const flatten=v=>Array.isArray(v)?v.flatMap(flatten):(v && typeof v==='object')?[v,...Object.values(v).filter(Array.isArray).flatMap(flatten)]:[]
const menus=flatten(menuTree).filter(v=>v.menu_key==='hsx_boyuan_logs')
assert.equal(menus.length,1)
const [menu]=menus
assert.deepEqual([menu.menu_name,menu.menu_type,menu.is_show,menu.status,menu.methods,menu.api_url,menu.router_path,menu.view_path],
    ['业务往来','1','1','1','get','boyuan/logs','hsx_boyuan/business','business'])
const path='/site/'+menu.router_path
const {build}=await import(pathToFileURL(req.resolve('vite/package.json').replace('package.json','dist/node/index.js')))
const {chromium}=req(process.env.PLAYWRIGHT_MODULE || 'playwright')
const modules={
    'fixture:entry':`import {createApp,h} from 'vue';import ep from 'element-plus';import {createRouter,createWebHistory,RouterView} from 'vue-router';
        import Page from '/src/addon/hsx_boyuan/views/${menu.view_path}.vue';
        const r=createRouter({history:createWebHistory(),routes:[{name:${JSON.stringify(menu.menu_key)},path:${JSON.stringify(path)},component:Page}]});
        createApp({render:()=>h(RouterView)}).use(r).use(ep).mount('#app');`,
    '@/utils/request':`export default {get:async(p,o)=>{const q=new URLSearchParams(o?.params||{}); const res=await fetch('/fixture/'+p+'?'+q);if(!res.ok)throw Error('Missing fixture');return {data:await res.json()}}}`
}
const bundle=await build({configFile:false,root,logLevel:'error',plugins:[{name:'journal-fixture',enforce:'pre',resolveId(k){if(k in modules)return '\0'+k},load(k){if(k.startsWith('\0') && k.slice(1) in modules)return modules[k.slice(1)]}},req('@vitejs/plugin-vue')()],
    resolve:{alias:[{find:/^@\/addon\/(.*)/,replacement:root+'/src/addon/$1'}],dedupe:['vue','vue-router','element-plus']},css:{postcss:{plugins:[]}},
    define:{'process.env.NODE_ENV':'"production"'},build:{write:false,minify:false,rollupOptions:{input:'fixture:entry',output:{format:'iife',inlineDynamicImports:true}}}})
const js=bundle.output.find(x=>x.type==='chunk').code,
    css=readFileSync(req.resolve('element-plus/dist/index.css'),'utf8')+bundle.output.filter(x=>x.fileName.endsWith('.css')).map(x=>x.source).join('\n')
const row={id:7,model:'隔离机',serial:'FIX-SERIAL-7',number:'FIX-PO-7',payable:{state:'settled',paid:'1.00',remaining:'0.00',amount:'1.00'},
    settlements:[{id:29,number:'FIX-ST-29',at:Date.parse('2026-11-10T10:22:00+08:00')/1000,amount:'1.00'}],
    purchase:[{key:'purchase',provider_label:'博远采购单',external_no:'FIX-BY-PO-7',status_label:'已入库',task_id:7,trace_ref:'T17-7',event_at:Date.parse('2026-11-09T11:11:00+08:00')/1000}],
    payments:[{key:'payment',provider_label:'博远付款单',external_no:'FIX-BY-PAY-29',status_label:'已登记',next_step:'已登记付款。博远原采购核销待核对，无需再次付款。',
        task_id:7,payment_task_id:29,trace_ref:'T17-29',settlement_id:29,event_at:Date.parse('2026-11-10T10:22:00+08:00')/1000}]}
const call={call_id:'FIX-CALL-29',journal_day:'2026-11-10',stage:'result',delivery:'response_received',service:'付款凭证接口样例',business_no:'FIX-ST-29',trace_id:'T17-29',
    sent_ms:Date.parse('2026-11-10T10:22:00+08:00'),finished_ms:Date.parse('2026-11-10T10:22:01+08:00'),
    request_payload:{request_preview:'REQ-FIX'},payload:{response_preview:'RESP-FIX'},request_detail:true,response_detail:true}
const older={...call,call_id:'FIX-CALL-28',journal_day:'2026-11-09',service:'上一日核对样例',sent_ms:call.sent_ms-86400000,finished_ms:call.finished_ms-86400000}
const browser=await chromium.launch({headless:true,chromiumSandbox:true,executablePath:process.env.BROWSER_PATH || undefined})
try {
    const ctx=await browser.newContext({viewport:{width:1440,height:980},timezoneId:'Asia/Shanghai',serviceWorkers:'block',reducedMotion:'reduce'})
    const page=await ctx.newPage(),errors=[],unexpected=[],calls=[]
    let access={available:true,views:['documents','exchanges']}
    page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(6000)
    await ctx.route('**/*',async r=>{
        const url=new URL(r.request().url()),q=Object.fromEntries(url.searchParams)
        if(url.origin===origin && r.request().method()==='GET'){
            if(url.pathname===path)return r.fulfill({contentType:'text/html; charset=utf-8',body:'<meta charset=utf-8><div id="app"></div><link rel=stylesheet href=/styles.css><script src=/app.js></script>'})
            if(url.pathname==='/app.js')return r.fulfill({contentType:'text/javascript; charset=utf-8',body:js})
            if(url.pathname==='/styles.css')return r.fulfill({contentType:'text/css',body:css})
            if(url.pathname==='/fixture/'+menu.api_url){
                calls.push(q)
                if(q.view==='access')return r.fulfill({json:access})
                if(q.view==='documents' && access.views.includes(q.view))return r.fulfill({json:{rows:q.keyword==='NOT-FOUND'?[]:[row],total:q.keyword==='NOT-FOUND'?0:1}})
                if(q.view==='exchanges' && access.views.includes(q.view)){
                    const match=q.day==='history' && q.keyword===call.trace_id
                    return r.fulfill({json:{rows:!match?[]:q.cursor==='earlier'?[older]:[call],next:match&&!q.cursor?'earlier':''}})
                }
                if(['purchases','payments'].includes(q.view) && access.views.includes(q.view)){
                    const payment=q.view==='payments'
                    return r.fulfill({json:{rows:[{id:payment?29:7,number:payment?'FIX-ST-29':'FIX-PO-7',at:row.settlements[0].at,amount:payment?'1.40':'2.00',device_count:2}],total:1}})
                }
                if(['payment_detail','purchase_detail'].includes(q.view)){
                    const payment=q.view==='payment_detail'
                    return r.fulfill({json:{id:payment?29:7,rows:[row,{...row,id:8,serial:'FIX-SERIAL-8',settlements:[{...row.settlements[0],amount:'0.40'}]}],total:2}})
                }
                const wire=[call,older].find(w=>q.call_id===w.call_id && q.day===w.journal_day)
                if(q.view==='detail' && access.views.includes('exchanges') && wire && ['request','response'].includes(q.side))
                    return r.fulfill({json:{payload:{detail:'FULL-'+q.side+'-'+wire.call_id}}})
            }
        }
        unexpected.push(r.request().method()+' '+url.origin+url.pathname+' '+JSON.stringify(q));return r.abort()
    })
    async function open(){calls.length=0;await page.goto(origin+path)}
    await open();await page.getByText(row.serial,{exact:true}).waitFor()
    assert.deepEqual(calls.map(q=>q.view),['access','documents'])
    // Radio/tab labels, not programmatic component calls: reproduce a clerk's navigation.
    await page.getByText('按时间查接口',{exact:true}).click()
    await page.getByText('没有留存可读的接口报文',{exact:false}).waitFor()
    await page.getByText('按设备查单据',{exact:true}).click()
    const field=page.getByPlaceholder(/串号.*单号/)
    await field.fill(row.serial);await page.getByRole('button',{name:'查询',exact:true}).click()
    const doc=page.locator('details.entry').filter({hasText:row.serial})
    await doc.locator('summary').click()
    await doc.locator('section').filter({hasText:'FIX-BY-PAY-29'}).getByRole('button',{name:'查这笔接口往来'}).click()
    await page.getByText(call.service,{exact:true}).waitFor()
    assert.equal(calls.at(-1).day,'history');assert.equal(calls.at(-1).keyword,'T17-29')
    await page.getByRole('button',{name:'查看更早记录'}).click()
    await page.getByText(older.service,{exact:true}).waitFor()
    assert.equal(calls.at(-1).cursor,'earlier')
    const exchange=page.locator('details.entry').filter({hasText:call.service})
    await exchange.locator('summary').click()
    for(const [index,side,text] of [[0,'request','FULL-request-FIX-CALL-29'],[1,'response','FULL-response-FIX-CALL-29']]){
        const panel=exchange.locator('.pair section').nth(index)
        await panel.getByRole('button',{name:'展开更多业务字段'}).click()
        await panel.getByText(text,{exact:false}).waitFor()
        assert.deepEqual(calls.at(-1),{view:'detail',day:'2026-11-10',call_id:'FIX-CALL-29',side})
    }
    const previous=page.locator('details.entry').filter({hasText:older.service})
    await previous.locator('summary').click()
    await previous.locator('.pair section').nth(1).getByRole('button',{name:'展开更多业务字段'}).click()
    await previous.getByText('FULL-response-FIX-CALL-28',{exact:false}).waitFor()
    assert.deepEqual(calls.at(-1),{view:'detail',day:'2026-11-09',call_id:'FIX-CALL-28',side:'response'})
    const back=page.getByRole('button',{name:/返回.*(设备|单据)|回到.*(设备|单据)/})
    await back.click();await doc.waitFor()
    assert.equal(await field.inputValue(),row.serial);assert.equal(await doc.getAttribute('open'),'')
    assert.equal(page.url(),origin+path)
    await field.fill('NOT-FOUND');await page.getByRole('button',{name:'查询',exact:true}).click()
    await page.getByText('没有匹配的设备',{exact:false}).waitFor()
    assert.equal(await page.getByText(row.serial,{exact:true}).count(),0)
    access={available:true,views:['documents']};await open();await page.getByText(row.serial,{exact:true}).waitFor()
    assert.equal(await page.getByText('按时间查接口',{exact:true}).count(),0)
    await page.locator('details.entry summary').click()
    assert.equal(await page.getByRole('button',{name:'查这笔接口往来'}).count(),0)
    access={available:true,views:[]};await open()
    await page.getByText('没有单据或接口往来的查看权限',{exact:false}).waitFor()
    assert.deepEqual(calls.map(x=>x.view),['access'])
    access={available:false,views:[]};await open()
    await page.getByText('本站没有可查看的博远连接记录。').waitFor()
    assert.deepEqual(calls.map(x=>x.view),['access'])
    access={available:true,views:['documents','exchanges','purchases','payments']};await open()
    await page.getByRole('button',{name:'FIX-PO-7'}).waitFor()
    assert.deepEqual(calls.map(x=>x.view),['access','purchases']);assert.equal(calls.at(-1).keyword,'')
    await page.getByText('付款凭证',{exact:true}).click()
    await page.getByRole('button',{name:'FIX-ST-29'}).click()
    const drawer=page.getByRole('dialog',{name:'付款凭证明细'})
    await drawer.getByText('本张凭证分摊 ¥0.40').waitFor()
    assert.equal(await drawer.locator('article').count(),2)
    assert.equal(await drawer.getByText('本站登记金额 ¥1.40').count(),1)
    await drawer.getByRole('button',{name:'查看接口收发'}).first().click()
    await page.getByText(call.service,{exact:true}).waitFor()
    await page.getByRole('button',{name:/返回单据明细/}).click()
    await drawer.getByText('本张凭证分摊 ¥0.40').waitFor()
    assert.deepEqual(unexpected,[]);assert.deepEqual(errors,[])
    console.log('PASS independent PHP menu → real Vue router → PC business journal: default GET documents, both tabs, original serial → historical payment trace and date → full request and response → return to same search. Empty and restricted states; no writes, gateway, real database or existing site.')
} finally {await browser.close()}
