// Isolated PC acceptance: builds real SFCs with the host plugins. All HTTP is fulfilled in memory.
import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { dirname, resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'
const here=dirname(fileURLToPath(import.meta.url)), host=resolve(here,'../../../../admin')
const req=createRequire(resolve(host,'package.json'))
const {build}=await import(pathToFileURL(req.resolve('vite/package.json').replace(/package\.json$/,'dist/node/index.js')))
const vue=req('@vitejs/plugin-vue'), {chromium}=process.env.PLAYWRIGHT_MODULE ? req(process.env.PLAYWRIGHT_MODULE) : req('playwright')
const dir=resolve(here,'../admin/views/integration'), pages=['adapter','connection','mapping','task','failure']
const fixtures={
    'adapter/list':{list:[{code:'sample',name:'示例 ERP',version:'1',supported_business_types:['purchase'],fields:[
        {name:'app_id',label:'AppID',required:true},{name:'app_secret',label:'AppSecret',type:'password',required:true}],mapping_requirements:{warehouse:true}}]},
    'connection/lists':{count:1,list:[{id:1,adapter_name:'示例连接',adapter_code:'sample',business_types:['purchase'],status:'disabled'}]},
    'connection/1':{id:1,adapter_name:'示例连接',adapter_code:'sample',business_types:['purchase'],config:{app_id:'demo'},saved_secrets:{app_secret:true}},
    'mapping/lists':{count:1,list:[{id:2,local_name:'测试仓',local_code:'W1',external_name:'外部测试仓',external_code:'R1',connection_id:1,resource_type:'warehouse'}]},
    'mapping/connections':{list:[{id:1,adapter_name:'示例连接',resource_types:['warehouse']}]},
    'mapping/resources':{list:[{id:5,code:'W5',name:'备用仓'}]},
    'task/lists':{count:1,list:[{id:7,source_no:'DEMO-7',external_doc_no:'',business_type:'purchase',status:'failed'}]},
    'task/statistics':{today:{total:1,success:0,pending:0,failed:1,unverified:0}},
    'task/7':{id:7,source_no:'DEMO-7',status:'failed',error_message:'等待外部核对',error_code:'TIMEOUT',can_retry:false,connection:{adapter_name:'示例连接'},payload:{sample:'测试数据'}},
    'failure/lists':{count:1,list:[{id:3,task_id:7,business_type:'purchase',source_no:'DEMO-7',failure_type:'timeout',resolved:0}]},
    'failure/3':{id:3,task_id:7,source_no:'DEMO-7',failure_type:'timeout',error_detail:'等待外部核对',resolved:0,task:{id:7,status:'failed'}}
}
fixtures['connection/adapters']=fixtures['adapter/list']
const entry='import {createApp,h} from "vue"; import {createRouter,createMemoryHistory,RouterView} from "vue-router"; import ep from "element-plus";'
    +pages.map((p,i)=>'import Pg'+i+' from '+JSON.stringify(dir+'/'+p+'.vue')+';').join('')
    +'const r=createRouter({history:createMemoryHistory(),routes:['+pages.map((p,i)=>'{name:"hsx_erp_integration_'+p+'",path:"/'+p+'",component:Pg'+i+'}').join(',')+']});window.visit=p=>r.push("/"+p);r.push("/connection");r.isReady().then(()=>createApp({render:()=>h(RouterView)}).use(r).use(ep).mount("#app"))'
const mock='async function go(method,path,data){const r=await fetch("/fixture/"+path,{method,body:method==="GET"?undefined:JSON.stringify(data)});const v=await r.json();if(!r.ok)throw v;return {data:v}};export default {get:(p,d)=>go("GET",p,d),post:(p,d)=>go("POST",p,d),delete:(p,d)=>go("DELETE",p,d)}'
const ids={'acceptance:entry':entry,'@/utils/request':mock,'@/stores/modules/user':'export default ()=>({rules:{includes:()=>true}})'}
const compiled=await build({configFile:false,root:host,logLevel:'warn',css:{postcss:{plugins:[]}},plugins:[
    {name:'isolated-fixtures',enforce:'pre',resolveId(id){if(id in ids)return '\0'+id}, load(id){if(id[0]==='\0' && id.slice(1) in ids)return ids[id.slice(1)]}},
    vue()],resolve:{dedupe:['vue','element-plus','vue-router','pinia'],alias:[{find:/^@\/addon\/(.*)/,replacement:host+'/src/addon/$1'}]},
    define:{'process.env.NODE_ENV':'"production"'},build:{write:false,minify:false,rollupOptions:{input:'acceptance:entry',output:{format:'iife',inlineDynamicImports:true}}}})
const code=compiled.output.find(x=>x.type==='chunk').code
const css=compiled.output.filter(x=>x.type==='asset'&&x.fileName.endsWith('.css')).map(x=>x.source).join('\n')
const {readFileSync}=await import('node:fs')
const epCss=readFileSync(req.resolve('element-plus/dist/index.css'),'utf8')
const browser=await chromium.launch({executablePath:process.env.BROWSER_PATH||undefined,headless:true})
const page=await browser.newPage({viewport:{width:1366,height:900}})
try{
    page.setDefaultTimeout(6500)
    const errors=[],writes=[];page.on('pageerror',e=>errors.push(e.message))
    let broken=false
    await page.route('**/*', async route=>{
        const url=new URL(route.request().url())
        assert.equal(url.origin,'http://ui-fixture.invalid','unexpected external request')
        if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:'<meta charset="utf-8"><div id="app"></div><link rel="stylesheet" href="/style.css"><script src="/main.js"></script>'})
        if(url.pathname==='/main.js')return route.fulfill({contentType:'text/javascript',body:code})
        if(url.pathname==='/style.css')return route.fulfill({contentType:'text/css',body:epCss+'\n'+css})
        const k=url.pathname.replace('/fixture/integration/',''), method=route.request().method()
        if(method!=='GET'){writes.push([k,route.request().postDataJSON()]);if(k==='connection/save/1')return route.fulfill({json:{id:1,status:'disabled'}});if(k==='failure/resolve/3'){fixtures['failure/3'].resolved=1;return route.fulfill({json:{}})}}
        if(k==='task/lists' && broken)return route.fulfill({status:503,json:{msg:'模拟读取失败'}})
        if(!(k in fixtures))return route.fulfill({status:404,json:{msg:'未配置模拟接口 '+k}})
        return route.fulfill({json:fixtures[k]})
    })
    await page.goto('http://ui-fixture.invalid')
    await page.getByText('示例连接',{exact:true}).waitFor()
    await page.getByRole('button',{name:'编辑',exact:true}).click()
    const secret=page.getByPlaceholder('已设置，留空保留');await secret.waitFor();assert.equal(await secret.inputValue(),'')
    await page.getByRole('button',{name:'保存连接'}).click();await page.getByText('连接已保存，当前未启用').waitFor()
    assert.equal(writes[0][1].config.app_secret,'')
    const visit=async p=>{await page.evaluate(p=>window.visit(p),p)}
    await visit('adapter');await page.getByRole('button',{name:'配置连接'}).click()
    await page.getByText('新建 ERP 连接',{exact:true}).waitFor();await page.getByRole('button',{name:'取消',exact:true}).click()
    await visit('mapping');await page.getByText('外部测试仓').waitFor()
    await page.getByRole('button',{name:'新建映射',exact:true}).click();await page.getByText('输入名称或编码搜索',{exact:true}).click()
    await page.getByText('备用仓 · W5').waitFor();await page.keyboard.press('Escape');await page.getByRole('button',{name:'取消',exact:true}).click()
    await visit('task');await page.getByRole('button',{name:'查看详情'}).click()
    await page.getByText('尚无外部单号回执',{exact:true}).waitFor()
    assert.equal(await page.getByRole('button',{name:'重试原任务'}).count(),0)
    await page.getByRole('button',{name:'关闭',exact:true}).last().click()
    broken=true;await page.getByRole('button',{name:'刷新',exact:true}).click();await page.getByText('模拟读取失败',{exact:true}).waitFor()
    broken=false;await page.getByRole('button',{name:'重新加载'}).click();await page.getByText('DEMO-7',{exact:true}).first().waitFor()
    await visit('failure');await page.getByRole('button',{name:'查看并处理'}).click()
    const resolveButton=page.getByRole('button',{name:'登记为已处理'});assert.equal(await resolveButton.isDisabled(),true)
    await page.getByPlaceholder('记录核对了什么、如何处理，方便下一位同事查阅').fill('模拟：已人工核对原单')
    await resolveButton.click();await page.getByText('已登记处理结果，未重发业务单').waitFor()
    assert.deepEqual(writes.map(x=>x[0]),['connection/save/1','failure/resolve/3']);assert.deepEqual(errors,[])
    console.log('PASS: five PC pages in Chromium, retained password, adapter handoff, resource search, timeout guard, read retry, manual resolution; no live network.')
} catch (error) { console.error('Fixture screen:', (await page.locator('body').innerText()).slice(-1800)); throw error }
finally { await browser.close() }
