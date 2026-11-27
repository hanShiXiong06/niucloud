// No browser, database or network. Compile the mounted admin source and drive the real setup handlers.
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict')
const root=path.resolve(__dirname,'../../../..'), adm=path.join(root,'admin'), from=n=>require(path.join(adm,'node_modules',n))
const sfc=from('@vue/compiler-sfc'),ts=from('typescript'),vue=from('vue')
const read=p=>fs.readFileSync(path.join(root,p),'utf8'), plug='niucloud/addon/hsx_boyuan/admin/', runtime='admin/src/addon/hsx_boyuan/'
for (const file of ['views/opening.vue','api/opening-archives.ts','components/ManualDelivery.vue']) assert.equal(read(plug+file),read(runtime+file))
const source=read(plug+'components/ManualDelivery.vue'), filename='ManualDelivery.vue', d=sfc.parse(source,{filename}).descriptor
const script=sfc.compileScript(d,{id:'delivery'})
assert.deepEqual(sfc.compileTemplate({filename,id:'delivery',source:d.template.content,compilerOptions:{bindingMetadata:script.bindings}}).errors,[])
assert.deepEqual(sfc.compileStyle({filename,id:'delivery',source:d.styles[0].content,scoped:true}).errors,[])
const readApi=read(plug+'api/opening-archives.ts')
assert.match(readApi,/params: \{ operation: 'uat_manual_delivery' \}/)
assert.match(read(plug+'views/opening.vue'),/capabilities\?\.manual_delivery === true/)
const reqs=[], notices=[], unmount=[], clone=o=>JSON.parse(JSON.stringify(o)),readonly={eligible:true, reason:'',account_id:'A123',connection_key:'key',enabled:false,purchase_enabled:false,paused:true,purchase_mode:'manual',revision:10}
let data=clone(readonly), reads=0, cancel=false, failure=false
const imports={'vue':{...vue,onBeforeUnmount:f=>unmount.push(f)},'element-plus':{ElMessage:{success:s=>notices.push(s)},ElMessageBox:{confirm:async()=>{if(cancel)throw Error('cancel')}}},'../api/opening-archives':{
  readManualDelivery:async()=>{reads++;return {data:clone(data)}},
  setManualDelivery:async p=>{reqs.push(clone(p));if(failure)throw Error('配置已改变');const on=p.intent==='enable';data={...data,enabled:on,purchase_enabled:on,paused:!on,revision:data.revision+1};return {data:clone(data)}}
}}
const exportsObject={}
vm.runInNewContext(ts.transpileModule(script.content,{compilerOptions:{target:ts.ScriptTarget.ES2020,module:ts.ModuleKind.CommonJS}}).outputText,{exports:exportsObject,require:k=>imports[k]})
const h=exportsObject.default.setup({connectionKey:'key'},{expose:()=>{}})
;(async()=>{
  assert.equal(reads,0);await h.load();assert.equal(h.confirmAccount.value,'');assert.equal(h.canEnable.value,false)
  h.confirmAccount.value='wrong';await h.change('enable');assert.equal(reqs.length,0)
  h.confirmAccount.value='A123';await h.change('enable');assert.deepEqual(reqs,[{intent:'enable',revision:10,connection_key:'key',confirm_account:'A123'}]);assert.equal(h.active.value,true)
  cancel=true;await h.change('pause');assert.equal(reqs.length,1);cancel=false
  await h.change('pause');assert.equal(h.state.value.paused,true);assert.equal(h.confirmAccount.value,'')
  failure=true;h.confirmAccount.value='A123';await h.change('enable');assert.equal(h.state.value,null);assert.match(h.error.value,/配置已改变/)
  await h.load();data.connection_key='another';await h.load();assert.equal(h.state.value,null);assert.match(h.error.value,/账套已变更/)
  unmount[0]();assert.equal(notices.length,2);console.log('PASS: PC UAT switch compiles, collapsed no read, explicit account, pause/reload, stale/failure and wrong account guards; zero network')
})().catch(e=>{console.error(e);process.exitCode=1})
