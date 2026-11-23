// Exercise the shipped desktop component. No HTTP calls, queue writes or remote transfers.
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), pkg = path.join(root, 'admin/node_modules')
const sfc = require(path.join(pkg, '@vue/compiler-sfc')), ts = require(path.join(pkg, 'typescript')), vue = require(path.join(pkg,'vue'))
const source = file => fs.readFileSync(path.join(root,'niucloud/addon/hsx_boyuan/admin',file),'utf8')
for (const file of ['views/wholesale.vue','api/wholesale.ts','utils/wholesale-trace.ts']) assert.equal(source(file), fs.readFileSync(path.join(root,'admin/src/addon/hsx_boyuan',file),'utf8'))
const filename = 'wholesale.vue', parsed = sfc.parse(source('views/'+filename), {filename}), descriptor = parsed.descriptor
assert.equal(parsed.errors.length, 0)
const ss = sfc.compileScript(descriptor, {id:'wholesale'})
assert.deepEqual(sfc.compileTemplate({source:descriptor.template.content,id:'wholesale',filename,compilerOptions:{bindingMetadata:ss.bindings}}).errors, [])
assert.deepEqual(sfc.compileStyle({source:descriptor.styles[0].content,id:'wholesale',filename,scoped:true}).errors, [])
const compiled = ts.transpileModule(ss.content,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}}).outputText
const clone = obj => JSON.parse(JSON.stringify(obj))
let state = { write_enabled:true, id:1, kind:'wholesale_outbound', status:'awaiting_confirmation', side_effect:'none', revision:1, active_connection:true, mode:'test', connection_key:'K',
    business_no:'SAL-TEST', snapshot:{party:{name:'本地客户'}, lines:[]}, result:{} }, cancel=false, fail=false, invalid=false
const mounted=[], calls=[], notices=[], timers=new Map(), user=vue.reactive({rules:[]})
const route=vue.reactive({path:'/hsx_boyuan/wholesale',query:{sale_no:'SAL-FROM-LINK'}}), nav=[]
const router={replace:async loc=>{nav.push(clone(loc));route.query=clone(loc.query);await vue.nextTick()}}
let timer=0
const api={
    listOutbound:async (page,keyword,status)=>{ calls.push(['list',page,keyword,status]); return {data:{review_mode:state.mode,total:1,data:[{...state,kind:invalid?'purchase':state.kind}]}} },
    getOutbound:async id=>{calls.push(['detail',id]);return {data:clone(state)}},
    prepareOutbound:async(id,no)=>{calls.push(['prepare',id,no]);return {data:{task_id:state.id,sale_no:no}}},
    confirmOutbound:async(id,body)=>{ calls.push(['submit',id,clone(body)]); if(fail) throw Error(typeof fail==='string'?fail:'未确认，先刷新'); state.status='pending'; state.revision++;return {data:clone(state)} },
    verifyOutbound:async(id,body)=>{ calls.push(['verify',id,clone(body)]); state.status='confirming'; state.revision++;return {data:clone(state)} },
    searchCustomers:async(key,keyword)=>{ calls.push(['customer',key,keyword]); return {data:{code:'OK',data:{items:[{id:'A',enabled:true,stale:false},{id:'S',enabled:true,stale:true}]}}} },
    searchCreators:async(key,company,keyword)=>{ calls.push(['staff',key,company,keyword]); return {data:{code:'OK',data:{items:[{id:'OP',enabled:true,stale:false,extra:{company_id:'C'}},{id:'WRONG',enabled:true,stale:false,extra:{company_id:'D'}}]}}} }
}
const m={exports:{}}
const traceLib={exports:{}};vm.runInNewContext(ts.transpileModule(source('utils/wholesale-trace.ts'),{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}}).outputText,{exports:traceLib.exports,module:traceLib})
const t=traceLib.exports.wholesaleTrace
const unknown={mode:'test',kind:'wholesale_outbound',side_effect:'unknown',status:'attention',request:{biz:{head:{manual_number:'sample-original',business_date:'2026-11-18'}}}}
const testTrace=lookup=>t({...unknown,result:{lookup}})
assert.equal(t(unknown).manual,'sample-original');assert.equal(t(unknown).businessDate,'2026-11-18');assert.match(t(unknown).label,/尚无/);assert.equal(t(unknown).found,null)
assert.match(testTrace({candidates:[]}).label,/旧核对/);assert.equal(testTrace({candidates:[]}).found,null)
assert.equal(testTrace({outcome:'failed',candidate_count:0}).found,null);assert.match(testTrace({outcome:'failed'}).label,/查询失败/)
assert.equal(testTrace({outcome:'no_match',candidate_count:0}).found,0)
const ignored=testTrace({outcome:'unidentifiable',candidate_count:0,missing_manual_number_rows:3});assert.equal(ignored.missing,3);assert.match(ignored.label,/无法辨认/)
const found=testTrace({outcome:'matched_candidates',candidate_count:1,queried_at:1794960000,candidates:[{external_no:'unknown-bill'}]});assert.match(found.label,/尚未核实/);assert.equal(found.found,1);assert.equal(found.candidates[0].no,'unknown-bill');assert.ok(found.date)
for(const other of [{mode:'mock'},{side_effect:'none'},{status:'succeeded'},{result:{receipt:{created:true}}}]) assert.equal(t({...unknown,...other}),null)
assert.match(descriptor.template.content,/未查到不等于未出库/);assert.match(descriptor.template.content,/{{ detail.reason }}/)
const receipt={exports:{}};vm.runInNewContext(ts.transpileModule(source('utils/manual-task-receipt.ts'),{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}}).outputText,{exports:receipt.exports,module:receipt})
vm.runInNewContext(compiled,{exports:m.exports,module:m,require:name=>({
    'vue':{...vue,onMounted:fn=>mounted.push(fn),onBeforeUnmount:()=>{}},'vue-router':{useRoute:()=>route,useRouter:()=>router},'@/stores/modules/user':{default:()=>user},
    'element-plus':{ElMessage:{success:s=>notices.push(s),warning:s=>notices.push(s),info:s=>notices.push(s)},ElMessageBox:{confirm:async()=>{if(cancel)throw Error('cancel')}}},'../api/wholesale':api,'../utils/manual-task-receipt':receipt.exports,'../utils/wholesale-trace':traceLib.exports
}[name]),setTimeout:fn=>{ timers.set(++timer,fn);return timer },clearTimeout:id=>timers.delete(id)})
const ui=m.exports.default.setup({}, {expose:()=>{}})
const flush=async()=>{for(let i=0;i<20;i++)await Promise.resolve()}
;(async()=>{
    await mounted[0](); assert.deepEqual(calls[0],['list',1,'SAL-FROM-LINK','']); ui.open(1); await flush()
    ui.detail.value={...unknown};let selected=0;ui.manualInput.value={focus:()=>{},select:()=>{selected++}};await ui.copyTraceNumber();assert.equal(selected,1);assert.match(notices.at(-1),/手动复制/);await ui.refreshDetail()
    assert.equal(ui.canSubmit.value,false);await ui.act('submit');assert.equal(calls.some(c=>c[0]==='submit'),false)
    user.rules.push('hsx_boyuan_wholesale_submit','hsx_boyuan_wholesale_verify','hsx_boyuan_reference_search')
    state.status='blocked';state.resolution='source_changed';await ui.refreshDetail();assert.equal(ui.canSubmit.value,false);await ui.act('submit');assert.equal(calls.some(c=>c[0]==='submit'),false)
    state.status='awaiting_confirmation';state.resolution='';await ui.refreshDetail()
    assert.equal(ui.canSubmit.value,true);await ui.findCustomer('客');assert.deepEqual(clone(ui.customers.value.map(c=>c.id)),['A'])
    await ui.act('submit');assert.match(ui.correction.value,/制单人/);assert.equal(calls.some(c=>c[0]==='submit'),false)
    state.creator_draft={company_id:'C',mapped:null};await ui.refreshDetail();await ui.findCreator('员工')
    assert.deepEqual(clone(ui.creators.value.map(c=>c.id)),['OP']);ui.creator.value='OP'
    state.creator_draft={company_id:'C',mapped:{id:'OP',name:'本单经办'},customer:{id:'A',name:'当前同行',code:'A'}}
    ui.clearDetail();ui.open(1);await flush()
    assert.equal(ui.customer.value,'A');assert.equal(ui.customerName.value,'当前同行');assert.equal(ui.creatorName.value,'本单经办')
    assert.equal(calls.some(c=>c[0]==='submit'),false,'opening and matching should not submit')
    state.write_enabled=false; await ui.refreshDetail(); assert.equal(ui.canSubmit.value,false)
    state.write_enabled=true; await ui.refreshDetail()
    cancel=true;await ui.act('submit');assert.equal(calls.some(c=>c[0]==='submit'),false);cancel=false
    fail='[SALE_CUSTOMER_REQUIRED] 请选择对应的博远客户';await ui.act('submit');assert.equal(ui.canSubmit.value,true);assert.equal(ui.correction.value,'请选择对应的博远客户')
    ui.customer.value='A'; fail=true;await ui.act('submit');assert.equal(ui.canSubmit.value,false)
    fail=false;await ui.refreshDetail();assert.equal(ui.canSubmit.value,true);await ui.act('submit')
    assert.deepEqual(calls.filter(c=>c[0]==='submit').at(-1),['submit',1,{revision:1,mode:'test',connection_key:'K',customer_id:'A',creator_id:'OP'}])
    assert.equal(ui.canSubmit.value,false);assert.equal(ui.canVerify.value,false);assert.equal(timers.size,1)
    state.status='created_review';state.side_effect='unknown';await ui.refreshDetail()
    assert.equal(ui.canSubmit.value,false);assert.equal(ui.canVerify.value,true);assert.equal(timers.size,0)
    await ui.act('verify');assert.deepEqual(calls.filter(c=>c[0]==='verify').at(-1),['verify',1,{revision:2,mode:'test',connection_key:'K'}])
    state.status='needs_review';await ui.refreshDetail();assert.equal(ui.canVerify.value,true);assert.equal(ui.canSubmit.value,false)
    state.active_connection=false;await ui.refreshDetail();assert.equal(ui.canVerify.value,false)
    state.active_connection=true;state.status='succeeded';await ui.refreshDetail();assert.equal(ui.canVerify.value,false);assert.equal(ui.label('succeeded'),'已核实出库')
    state.can_process_current=true;state.status='pending';state.side_effect='unknown';await ui.refreshDetail();assert.equal(ui.currentAction.value,'')
    state.side_effect='none';await ui.refreshDetail();assert.equal(ui.currentAction.value,'submit');assert.equal(ui.canSubmit.value,false)
    const rev=state.revision
    api.confirmOutbound=async(id,body)=>{calls.push(['submit',id,clone(body)]);state.status='created_review';state.external_no='BY-64';state.side_effect='unknown';state.revision++;return {data:clone(state)}}
    await ui.act('submit',true);assert.deepEqual(calls.filter(c=>c[0]==='submit').at(-1),['submit',1,{revision:rev,mode:'test',connection_key:'K',intent:'process_current'}]);assert.match(notices.at(-1),/博远已建单.*BY-64/);assert.equal(ui.currentAction.value,'')
    state.status='confirming';await ui.refreshDetail();assert.equal(ui.currentAction.value,'verify')
    api.verifyOutbound=async(id,body)=>{calls.push(['verify',id,clone(body)]);state.status='succeeded';state.revision++;return {data:clone(state)}}
    await ui.act('verify',true);assert.match(notices.at(-1),/已核实出库.*BY-64/);assert.equal(ui.currentAction.value,'');assert.equal(timers.size,0)
    state.external_no=''
    ui.keyword.value=' SAL-1 ';ui.search();await flush();assert.deepEqual(calls.at(-1),['list',1,'SAL-1',''])
    state.mode='mock';state.kind='sale';state.snapshot.sale_type='wholesale';state.snapshot.payment_method_ref='method:挂账';state.status='created_review';state.side_effect='unknown'
    await ui.loadList();ui.open(1);await flush();assert.equal(ui.mock(ui.detail.value),true);assert.equal(ui.canVerify.value,false)
    state.status='awaiting_confirmation';state.side_effect='none';await ui.refreshDetail();assert.equal(ui.canSubmit.value,false)
    let writes=calls.filter(c=>['submit','verify'].includes(c[0])).length;await ui.act('submit');await ui.act('verify');assert.equal(calls.filter(c=>['submit','verify'].includes(c[0])).length,writes)
    state.snapshot.payment_method_ref='method:现结';await ui.refreshDetail();assert.equal(ui.detail.value,null);assert.ok(ui.detailError.value)
    invalid=true;await ui.loadList();assert.equal(ui.rows.value.length,0);assert.ok(ui.listError.value)
    ui.clearDetail();assert.equal(ui.detail.value,null);assert.equal(timers.size,0)
    state.mode='test';state.kind='wholesale_outbound';state.status='awaiting_confirmation';state.side_effect='none';state.active_connection=true
    state.snapshot={source_document_id:'sale:37',source_document_no:state.business_no,party:{},lines:[]};invalid=false
    const originStart=calls.length
    route.query={sale_id:'37',sale_no:state.business_no};await flush()
    assert.equal(ui.canPrepare.value,true);assert.deepEqual(calls.slice(originStart),[],'opening sale without task must neither POST nor read unrelated orders')
    user.rules=user.rules.filter(x=>x!=='hsx_boyuan_wholesale_submit');await ui.prepare();assert.equal(calls.length,originStart)
    user.rules.push('hsx_boyuan_wholesale_submit')
    const p=ui.prepare();await ui.prepare();await p;await flush()
    assert.deepEqual(calls.slice(originStart),[['prepare',37,state.business_no],['detail',1]],'one explicit local preparation, direct detail only')
    assert.deepEqual(nav.at(-1).query,{sale_id:'37',sale_no:state.business_no,task_id:'1'});assert.equal(ui.detail.value.id,1)
    route.query={sale_id:'38',sale_no:state.business_no,task_id:'1'};await flush();assert.equal(ui.detail.value,null);assert.ok(ui.detailError.value)
    route.query={sale_no:'WRONG',task_id:'1'};await flush();assert.equal(ui.detail.value,null)
    state.active_connection=false;route.query={sale_no:state.business_no,task_id:'1'};await flush();assert.equal(ui.detail.value,null)
    state.active_connection=true;await ui.refreshDetail();assert.equal(ui.detail.value.id,1)
    route.query={sale_id:'bad',sale_no:state.business_no};await flush();assert.equal(ui.canPrepare.value,false);assert.ok(ui.sourceError.value)
    const writesAfter=calls.filter(c=>['submit','verify'].includes(c[0])).length;await ui.prepare()
    assert.equal(calls.filter(c=>['submit','verify'].includes(c[0])).length,writesAfter)
    assert.equal(calls.slice(originStart).filter(c=>c[0]==='list').length,0,'an employee does not need all-tasks list')
    console.log('PASS wholesale PC component, mirrors/compile, permission gating, confirmation, revision, unknown/retry, verification, polling, filters and scope; no HTTP')
})().catch(e=>{console.error(e);process.exitCode=1})
