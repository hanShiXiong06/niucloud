// Compile the real desktop SFCs; test form transitions with dummy credentials and no network.
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict')
const root=path.resolve(__dirname,'../../../..'),cp='niucloud/addon/hsx_boyuan/admin/',mount='admin/src/addon/hsx_boyuan/'
const get=s=>fs.readFileSync(path.join(root,s),'utf8'),pkg=s=>require(path.join(root,'admin/node_modules',s))
const sfc=pkg('@vue/compiler-sfc'),ts=pkg('typescript'),vue=pkg('vue')
const read=s=>{const v=get(cp+s);assert.equal(v,get(mount+s));return v}
function compile(code,imports={}){const out={};vm.runInNewContext(ts.transpileModule(code,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}}).outputText,{
    exports:out,URL,document:{getElementById:()=>({focus(){}})},require:k=>{if(k in imports)return imports[k];throw Error(k)}
});return out}
function component(f){const d=sfc.parse(read(f),{filename:f}).descriptor, script=sfc.compileScript(d,{id:'qa'});assert.deepEqual(sfc.compileTemplate({id:'qa',filename:f,source:d.template.content,compilerOptions:{bindingMetadata:script.bindings}}).errors,[]);return script.content}
const connectionSource=component('views/connection.vue');component('views/workbench.vue');component('components/DeskShell.vue');component('components/InterfaceTraceControl.vue')
component('components/ExchangeCenter.vue');const exchangePayload=component('components/ExchangePayload.vue')
read('api/connection.ts');const util=compile(read('utils/connection.ts')),meta=compile(read('extensions/erp-opening.ts')).default
const menu=get('niucloud/addon/hsx_boyuan/app/dict/menu/site.php')
const menuKeys=Array.from(menu.matchAll(/'menu_key'\s*=>\s*'([^']+)'/g),m=>m[1])
for(const route of [meta.desk.name,util.routes.connection])assert.ok(menuKeys.includes(route),'missing menu: '+route)
const erp='niucloud/addon/hsx_erp/admin/',mounted='admin/src/addon/hsx_erp/'
for(const file of ['views/erp/opening/index.vue','views/erp/workbench/index.vue','components/IntegrationDeskLinks.vue']){
    const s=get(erp+file);assert.equal(s,get(mounted+file));assert.doesNotMatch(s,/hsx_boyuan|博远/)
    if(file.startsWith('views'))assert.match(s,/<IntegrationDeskLinks/)
}
const cpState=x=>JSON.parse(JSON.stringify(x))
let confirm=true,guard,depart,focus,rotate=false,refuse=false,remote
const basic=(url)=>({suggested_url:url,protocol:'openapi_v153',app_id:'',account_id:'',app_secret_configured:false,endpoint:'',connection_key:'',probe_at:0,contract_confirmed:false})
function fresh(){return {mode:'mock',revision:0,profiles:{test:basic('https://open-api-uat.139erp.com/api'),production:basic('https://open-api.139erp.com/api')},can_edit:true,can_probe:true,dispatching:false,active_key:'',finance_active:false,interface_trace:{window_id:'',last_window_id:'',expires_at:0,server_time:100}}}
remote=fresh();const calls=[]
const api={
    readConnection:async()=>({data:cpState(remote)}),
    saveConnection:async d=>{calls.push('save');const p=remote.profiles[d.mode];remote.mode=d.mode;remote.revision++;Object.assign(p,{app_id:d.app_id,account_id:d.account_id,endpoint:d.endpoint,app_secret_configured:true,connection_key:'fixture',probe_at:0,contract_confirmed:d.contract_confirmed});remote.active_key='fixture'},
    verifyConnection:async ctx=>{calls.push('probe');if(refuse)throw Error('未获得匹配的授权账套');const p=remote.profiles[ctx.mode];p.probe_at=2000000;if(rotate)remote.revision++;return {data:{verified:true,mode:ctx.mode,account_id:p.account_id,write_performed:false}}},
    pauseConnection:async()=>{calls.push('pause');remote.dispatching=false;remote.revision++},
    toggleInterfaceTrace:async data=>{calls.push('trace:'+data.intent);assert.equal(data.connection_key,remote.active_key);assert.equal(data.window_id,remote.interface_trace.window_id)
        const id='BW0123456789abcdef';remote.interface_trace={window_id:data.intent==='start'?id:'',last_window_id:id,expires_at:data.intent==='start'?1000:0,server_time:100};return {data:cpState(remote.interface_trace)}}
}
const imp={'vue':{...vue,onMounted(){},onActivated(){},onDeactivated:fn=>{depart=fn},onBeforeUnmount(){}},'vue-router':{onBeforeRouteLeave:fn=>{guard=fn}},'element-plus':{ElMessageBox:{confirm:async()=>{if(!confirm)throw Error('cancel')}}},'../api/connection':api,'../utils/connection':util,'../components/DeskShell.vue':{},'@/addon/hsx_components/components/HsxTag/index.vue':{}}
imp['../components/InterfaceTraceControl.vue']={}
const h=compile(connectionSource,imp).default.setup({},{expose(){}})
;(async()=>{
    const detailCalls=[];let failDetail=true;
    const payload=compile(exchangePayload,{'vue':{...vue,onBeforeUnmount(){}},'../utils/connection':util,'@/utils/request':{default:{get:async(path,{params})=>{detailCalls.push([path,params]);if(failDetail)throw Error('脱敏明细暂不可用');return {data:{payload:{last_device:18}}}}}}}).default.setup(
        {title:'响应',brief:{preview:2},expandable:true,callId:'abcdef0123456789',day:'2026-11-19',side:'response'},{expose(){}})
    assert.equal(detailCalls.length,0);await payload.toggle();assert.equal(payload.expanded.value,false);assert.equal(payload.value.value.preview,2);assert.ok(payload.error.value)
    failDetail=false;await payload.toggle();assert.equal(payload.value.value.last_device,18);await payload.toggle();await payload.toggle()
    assert.equal(detailCalls.length,2);assert.equal(detailCalls[1][0],'boyuan/logs');assert.equal(detailCalls[1][1].view,'detail');assert.equal(detailCalls[1][1].side,'response')
    await h.load();assert.equal(h.mode.value,'test');assert.equal(h.d.endpoint,remote.profiles.test.suggested_url)
    await h.save();assert.deepEqual(Object.keys(h.errors.value),['account_id','app_id','app_secret']);assert.deepEqual(calls,[])
    Object.assign(h.d,{account_id:'0007',app_id:'0042',app_secret:'A'.repeat(32)});confirm=false;assert.equal(await guard(),false);confirm=true
    await h.save();assert.deepEqual(calls,['save']);assert.equal(h.saved.value,true);assert.equal(h.verified.value,false);assert.equal(h.d.app_secret,'')
    await h.verify();assert.deepEqual(calls,['save','probe']);assert.equal(h.verified.value,true)
    h.d.contract_confirmed=true;h.d.account_id='0091';assert.equal(h.d.contract_confirmed,false);assert.equal(h.needsSecret.value,true);assert.equal(h.canCheck.value,false)
    await h.save();assert.deepEqual(calls,['save','probe']);await h.load();assert.equal(h.needsSecret.value,false)
    refuse=true;await h.verify();assert.equal(h.verified.value,false);assert.equal(h.stale.value,true);assert.match(h.warning.value,/未获得/);refuse=false
    await h.load();rotate=true;await h.verify();assert.equal(h.stale.value,true);assert.equal(h.verified.value,false);rotate=false
    remote.dispatching=true;await h.load();assert.equal(h.editable.value,false);confirm=false;await h.pause();assert.equal(remote.dispatching,true);confirm=true;await h.pause();assert.equal(remote.dispatching,false)
    const revision=remote.revision;remote.dispatching=true;await h.load();await h.toggleTrace('start','')
    assert.equal(h.state.value.interface_trace.window_id,'BW0123456789abcdef');await h.toggleTrace('stop','BW0123456789abcdef')
    assert.equal(h.state.value.interface_trace.window_id,'');assert.equal(remote.revision,revision);assert.equal(remote.dispatching,true)
    remote.can_edit=false;remote.can_probe=false;await h.load();const n=calls.length;await h.save();await h.verify();await h.pause();assert.equal(calls.length,n)
    await h.toggleTrace('start','');assert.equal(calls.length,n)
    remote.mode='production';remote.can_edit=true;remote.can_probe=true;remote.dispatching=false;remote.active_key='prod';remote.finance_active=false
    Object.assign(remote.profiles.production,{endpoint:remote.profiles.production.suggested_url,contract_confirmed:true,connection_key:'prod',account_id:'p1',app_id:'42',app_secret_configured:true,probe_at:1})
    const candidate={task_id:17,test_bill:'Q17',test_account:'T1'}
    remote.purchase_release={eligible:false,recorded_ref:'',candidate:null};await h.load()
    let released=0
    api.enableManualPurchase=async input=>{released++;assert.equal(input.confirm_account,'p1');assert.equal(input.candidate_task_id,17);assert.equal(input.connection_key,'prod')
        remote.dispatching=true;remote.revision++;remote.purchase_release={eligible:false,recorded_ref:'Q17',candidate:null}
        return {data:{mode:'production',account_id:'p1',manual_purchase:true,revision:remote.revision,documents_sent:0}}}
    await h.releasePurchase();assert.equal(released,0)
    remote.purchase_release={eligible:true,recorded_ref:'',candidate};await h.load();confirm=false
    await h.releasePurchase();assert.equal(released,0);confirm=true
    await Promise.all([h.releasePurchase(),h.releasePurchase()]);assert.equal(released,1);assert.equal(h.state.value.dispatching,true);assert.ok(h.feedback.value.includes('手动采购同步已开启'))
    depart();assert.equal(h.state.value,null);assert.equal(h.d.app_secret,'')
    console.log('PASS: two desktop menu entries, shared ERP shortcuts, empty form, leading zero IDs, unsaved edits, save vs verify, failed/stale receipts, pause cancel, read-only roles and secret removal.')
})().catch(e=>{console.error(e);process.exitCode=1})
