// Real Vue templates + isolated setup methods. No browser, database, workers, dispatch, or external requests.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm')
const root=path.resolve(__dirname,'../../../..'),pkg=s=>require(path.join(root,'admin/node_modules',s))
const ts=pkg('typescript'),sfc=pkg('@vue/compiler-sfc'),vue=pkg('vue')
function source(addon,f){
  const get=p=>fs.readFileSync(path.join(root,p,f),'utf8')
  const s=get('niucloud/addon/'+addon+'/admin')
  assert.equal(s,get('admin/src/addon/'+addon))
  return s
}
function script(addon,file){
  const s=source(addon,file),desc=sfc.parse(s,{filename:file})
  assert.deepEqual(desc.errors,[]);const compile=sfc.compileScript(desc.descriptor,{id:'qa'})
  assert.deepEqual(sfc.compileTemplate({id:'qa',filename:file,source:desc.descriptor.template.content,compilerOptions:{bindingMetadata:compile.bindings}}).errors,[])
  return compile.content
}
function load(source,mods={}){
  const exp={};vm.runInNewContext(ts.transpileModule(source,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}}).outputText,
    {exports:exp,require:id=>{if(id in mods)return mods[id];throw Error('unexpected dependency: '+id)}})
  return exp
}
const logic=load(source('hsx_boyuan','utils/recycle-wire.ts')),calls=[]
const host=script('hsx_recycle','components/BusinessOperationList.vue')
assert.match(host,/router\.hasRoute\(e.route\)/);assert.match(host,/Number\(props.orderId\)===Number\(r.order_id\)/)
assert.match(source('hsx_boyuan','extensions/recycle-operation-details.ts'),/hsx_boyuan_logs/);script('hsx_boyuan','components/ExchangePayload.vue')
const c=script('hsx_boyuan','components/RecycleOperationEvidence.vue')
const row={site_id:17,source:'boyuan',task_id:5,call_id:'1234567890abcdef'}
const q=logic.evidenceQuery(row);assert.equal(q.trace,'T17-5');assert.equal(q.call,row.call_id)
assert.equal(logic.evidenceQuery({...row,task_id:0}),null);assert.equal(logic.evidenceQuery({...row,source:'erp'}),null)
assert.equal(logic.evidenceQuery({...row,call_id:'bad'}),null)
const wire={call_id:row.call_id,trace_id:q.trace,stage:'request',payload:{preview:'sent'}}
let result={rows:[{...wire,trace_id:'T18-5'},{...wire,call_id:'aaaaaaaaaaaaaaaa'},wire],next:'older'},failure=false
const mod=load(c,{'vue':{...vue,watch(){},onBeforeUnmount(){}},'@/utils/request':{default:{get:async (path,req)=>{
    calls.push([path,req.params]);if(failure)throw Error('forbidden');return {data:result}
}}},'./ExchangePayload.vue':{},'../utils/connection':{reason:e=>e.message},'../utils/recycle-wire':logic})
const api=mod.default.setup({row},{expose(){}})
;(async()=>{
  await api.load();assert.equal(calls[0][0],'boyuan/logs');assert.equal(calls[0][1].keyword,'T17-5');assert.equal(calls[0][1].day,'history')
  assert.equal(api.rows.value.length,1);assert.equal(api.cursor.value,'older')
  result={rows:[{...wire,stage:'result',request_payload:wire.payload,payload:{external_no:'FIX'}}],next:'remaining'}
  await api.load(true);assert.equal(api.rows.value.length,1);assert.equal(api.rows.value[0].payload.external_no,'FIX')
  assert.equal(calls[1][1].cursor,'older');assert.equal(api.cursor.value,'')
  failure=true;await api.load();assert.equal(api.rows.value.length,0);assert.equal(api.error.value,'forbidden')
  console.log('PASS: original order and permission guards, exact task/call, read-only retrieval, paging, deduplication, error state, Vue compilation and mirrors.')
})().catch(e=>{console.error(e);process.exitCode=1})
