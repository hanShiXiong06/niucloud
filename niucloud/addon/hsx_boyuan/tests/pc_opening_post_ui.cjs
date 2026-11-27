// SFC behavior with an isolated, deterministic API. Never touches the database or a remote ERP.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm')
const root=path.resolve(__dirname,'../../../..'),adm=path.join(root,'admin'),vue=require(path.join(adm,'node_modules/vue')),ts=require(path.join(adm,'node_modules/typescript')),sfc=require(path.join(adm,'node_modules/@vue/compiler-sfc'))
const plugin='niucloud/addon/hsx_boyuan/admin',dev='admin/src/addon/hsx_boyuan'
const read=f=>fs.readFileSync(path.join(root,f),'utf8'),loc='/components/OpeningPost.vue'
for(const f of [loc,'/api/opening-post.ts'])assert.equal(read(plugin+f),read(dev+f))
const filename='OpeningPost.vue',p=sfc.parse(read(dev+loc),{filename}),script=sfc.compileScript(p.descriptor,{id:'post'})
assert.equal(sfc.compileTemplate({source:p.descriptor.template.content,filename,id:'post',compilerOptions:{bindingMetadata:script.bindings}}).errors.length,0)
assert.equal(sfc.compileStyle({filename,id:'post',source:p.descriptor.styles[0].content,scoped:true}).errors.length,0)
const js=ts.transpileModule(script.content,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}}).outputText
const calls=[],deferred=[],unmount=[],rules={ready:true,account:'demo123',can_preview:true,can_apply:true,receivable_count:0,payable_count:0,resources:['products','warehouses','parties'].map((r,i)=>({resource:r,name:'类别'+i,ready:i<2}))}
const props=vue.reactive({connectionKey:'A',rows:['products','warehouses','parties'].map(r=>({resource:r,mode:'test',ready:true,job:{id:1,status:'completed'},capabilities:{can_check_opening:false}}))})
let malformed=false, unknown=false, holdRead=false,cancel=false,expired=false
const clone=x=>JSON.parse(JSON.stringify(x))
const output=(resource,cursor)=>{
    const rows=resource==='warehouses'?[{id:110,name:'仓',action:'existing'}]:cursor===0?[{id:1,name:'A',action:'create'},{id:2,name:'B',action:'conflict',reason:'本站同名'}]:[{id:51,name:'C',action:'create'},{id:52,name:'D',action:'skip'}]
    return {token:'t'+cursor,rows,next_cursor:resource==='products'&&cursor===0?2:0}
}
const api={
 readOpeningCheck:async k=>{calls.push(['check',k]);return {data:clone(rules)}},
 previewOpening:async (k,r,c)=>{calls.push(['preview',k,r,c]);if(holdRead)await new Promise(res=>deferred.push(res));const data=output(r,c);if(malformed&&c)data.next_cursor=c;return {data}},
 applyOpening:async(k,r,c,t)=>{calls.push(['apply',k,r,c,t]);if(expired||unknown&&c>0)throw Error('回执超时');return {data:output(r,c)}}
}
const e={}
vm.runInNewContext(js,{exports:e,require:n=>n==='vue'?{...vue,onUnmounted:f=>unmount.push(f)}:n==='element-plus'?{ElMessageBox:{confirm:async()=>{if(cancel)throw Error('取消')}}}:n==='../api/opening-post'?api:n==='./OpeningAccounts.vue'?{}:(()=>{throw Error(n)})()})
const ui=e.default.setup(props,{expose:()=>{}})
const flush=async()=>{await vue.nextTick();for(let i=0;i<12;i++)await Promise.resolve()}
const count=a=>calls.filter(c=>c[0]===a).length
async function run(){
 await flush();assert.equal(calls.length,0,'no role: cannot read financial gate')
 rules.ready=false;rules.payable_count=1;props.rows[0].capabilities.can_check_opening=true;await flush()
 assert.equal(count('check'),1);assert.equal(ui.gate.value.ready,false);await ui.preview();await ui.post();assert.equal(count('preview'),0);assert.equal(count('apply'),0)
 rules.ready=true;rules.payable_count=0;await ui.inspect();assert.deepEqual(clone(ui.chosen.value),['products','warehouses'])
 malformed=true;await ui.preview();assert.equal(ui.phase.value,'idle');assert.equal(ui.batches.value.length,0);malformed=false
 await ui.preview();assert.deepEqual(clone(ui.summary.value),{create:2,existing:1,skip:1,conflict:1});assert.equal(ui.findings.value.length,2)
 cancel=true;await ui.post();assert.equal(count('apply'),0);assert.equal(ui.phase.value,'preview');cancel=false
 unknown=true;const writing=ui.post();await ui.post();await writing
 assert.equal(ui.phase.value,'interrupted');assert.equal(ui.posted.value,1);assert.equal(count('apply'),2);await ui.post();assert.equal(count('apply'),2)
 unknown=false;await ui.preview();await ui.post();assert.deepEqual(clone(ui.finalCounts.value),{create:2,existing:1,skip:1,conflict:1});assert.equal(ui.phase.value,'done')
 assert.ok(!calls.some(x=>x[0]==='apply'&&x[2]==='warehouses'),'do not submit existing-only pages');assert.match(ui.findings.value[0].reason,/同名/)
 rules.can_apply=false;await ui.inspect();await ui.preview();const prev=count('apply');await ui.post();assert.equal(count('apply'),prev)
 holdRead=true;const inFlight=ui.preview();await flush();props.connectionKey='B';await flush();holdRead=false;deferred.shift()();await inFlight;assert.equal(ui.phase.value,'idle');assert.equal(ui.batches.value.length,0,'old account preview discarded')
 props.rows[0].mode='mock';await flush();assert.equal(ui.allowed.value,false);assert.equal(ui.gate.value,null)
 unmount[0]();console.log('PASS: real PC ERP opening SFC; per-role gate, open balance block, eligibility, all pages, conflicts, confirmation, no duplicate after unknown, stale preview and account changes')
}
run().catch(e=>{console.error(e);process.exitCode=1})
