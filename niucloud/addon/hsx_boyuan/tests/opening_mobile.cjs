// Workbench SFC and real opening state helper; no HTTP, database or external accounts.
const assert = require('node:assert/strict'), fs = require('node:fs'), path = require('node:path'), vm = require('node:vm')
const root = path.resolve(__dirname,'../../../..'), app = path.join(root,'site-uniapp')
const plug = path.join(root,'niucloud/addon/hsx_boyuan/site-uniapp'), installed = path.join(app,'src/addon/hsx_boyuan')
const ts = require(path.join(app,'node_modules/typescript')), vue = require(path.join(app,'node_modules/vue')), compiler = require(path.join(app,'node_modules/@vue/compiler-sfc'))
const read = p => fs.readFileSync(path.join(installed,p),'utf8')
for (const f of ['api/index.ts','pages/workbench/index.vue','utils/opening.ts']) assert.equal(read(f), fs.readFileSync(path.join(plug,f),'utf8'))
function moduleOf(source,env={}) {
    const module={exports:{}}
    const out=ts.transpileModule(source,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}})
    vm.runInNewContext(out.outputText,{module,exports:module.exports,...env});return module.exports
}
const open=moduleOf(read('utils/opening.ts')), purchase=moduleOf(read('utils/purchase-form.ts'))
const cfg={mode:'test',profiles:{test:{connection_key:'original',protocol:'openapi_v153'}},enabled:false,paused:true}
const perms={start:true,retry:true,search:true}
let rows, jobs, search, submit, receipt={queued:true}
const hints=[]
const seed=(state=null,key='original')=>open.openingResources.map((k,i)=>({resource:k,name:k,mode:'test',connection_key:key,ready:true,blocking_reason:'',capabilities:perms,count:0,last_success_at:0,job:state?{id:i+1,status:state,pages:0,processed:0,scope:{}}:null}))
function deferred(){let resolve;const promise=new Promise(r=>resolve=r);return {resolve,promise}}
const calls=[], api={
    getConfig:async()=>({data:cfg}),getReferenceJobs:async()=>({data:structuredClone(jobs?await jobs.promise:rows)}),
    syncReferences:async(r,key,intent)=>{calls.push(['start',Array.from(r),key,intent]);if(submit)await submit.promise;return {data:receipt}},
    retryReference:async id=>{calls.push(['retry',id]);return {data:{queued:true}}},
    getReferences:async p=>{calls.push(['search',p]);if(search)return await search.promise;return {data:{data:{items:[],next_cursor:null}}}},
    getTasks:async()=>({data:{data:[],total:0,counts:[]}})
}
const sfc=compiler.parse(read('pages/workbench/index.vue'),{filename:'index.vue'});assert.deepEqual(sfc.errors,[])
const script=compiler.compileScript(sfc.descriptor,{id:'opening'});assert.deepEqual(compiler.compileTemplate({source:sfc.descriptor.template.content,filename:'index.vue',id:'opening'}).errors,[])
const life={},requireFor=n=> n==='vue'?{...vue,onUnmounted:()=>{}}:n==='@dcloudio/uni-app'?{onLoad:fn=>life.load=fn,onShow:()=>{},onHide:()=>{}}:n==='../../api/index'?api:n==='../../utils/opening'?open:n==='../../utils/purchase-form'?purchase:n.endsWith('.vue')?{}:(()=>{throw Error(n)})()
const ui=moduleOf(script.content,{require:requireFor,setInterval:()=>1,clearInterval:()=>{},uni:{showToast:({title})=>hints.push(title)}}).default.setup({},{expose:()=>{}})
async function run() {
    const binding={kind:'product',local_ref:'recycle-sku:'+'c'.repeat(64)}
    assert.equal(ui.isRecycleBinding(binding),true);assert.equal(ui.isRecycleBinding({...binding,kind:'warehouse'}),false)
    ui.editMapping(binding);assert.equal(ui.mappingVisible.value,false)
    rows=seed();life.load({section:'opening'});await ui.refresh();assert.equal(ui.section.value,'archives')
    assert.equal(ui.openingPrimary.value,'')
    assert.deepEqual(Array.from(ui.visibleArchives.value,r=>r.resource),['companies','warehouses','parties','products','staff'])
    ui.archiveFilter.value='extra'
    assert.deepEqual(Array.from(ui.visibleArchives.value,r=>r.resource),['categories','brands','members','payment_methods','capital_accounts'])
    await ui.startSync();await ui.startSync('all');assert.equal(calls.length,0)
    ui.archiveFilter.value='';submit=deferred();const op=ui.startSync('warehouses');await ui.startSync('warehouses')
    assert.equal(calls.length,1);assert.deepEqual(calls[0],['start',['warehouses'],'original','opening'])
    rows=seed('completed');rows[0].job={id:1,status:'running',processed:4,pages:2};submit.resolve();await op;submit=null
    assert.equal(ui.opening.value.completed,9);assert.equal(ui.openingPrimary.value,'')
    await ui.startSync();await ui.startSync(rows[0].resource);assert.equal(calls.length,1)
    rows[0].job.status='failed';await ui.readArchiveJobs();assert.equal(ui.openingPrimary.value,'查看待处理资料')
    ui.openingAction();assert.equal(ui.visibleArchives.value.length,1);await ui.resumeSync(2);assert.equal(calls.length,1)
    await ui.resumeSync(1);assert.deepEqual(calls.at(-1),['retry',1])
    rows[0].job.status='needs_review';await ui.readArchiveJobs();await ui.resumeSync(1);assert.equal(calls.length,2)
    await ui.startSync(rows[0].resource);assert.deepEqual(calls.at(-1),['start',[rows[0].resource],'original','refresh'])
    rows=seed('completed');await ui.readArchiveJobs();assert.match(ui.openingHint.value,/电脑端/);assert.doesNotMatch(ui.openingHint.value,/建账已完成/)
    await ui.startSync('all');assert.deepEqual(calls.at(-1),['start',Array.from(open.openingResources),'original','refresh'])
    rows.find(r=>r.resource==='capital_accounts').job=null;await ui.readArchiveJobs()
    assert.equal(ui.opening.value.ready,false);assert.equal(ui.coreDone.value,5);assert.match(ui.openingHint.value,/无需等所有类别/)
    const old=calls.length; rows=seed().map(r=>({...r,capabilities:{start:false,retry:false,search:false}}))
    await ui.readArchiveJobs();assert.equal(ui.openingPrimary.value,'');assert.match(ui.openingHint.value,/仅可查看/)
    await ui.startSync();await ui.openArchive(rows[0]);assert.equal(calls.length,old)
    rows=seed().map(r=>({...r,ready:false,blocking_reason:'请核验'}));await ui.readArchiveJobs();assert.equal(ui.openingHint.value,'请核验');await ui.startSync()
    assert.equal(calls.length,old)
    rows=seed();await ui.readArchiveJobs();search=deferred();const oldDetail=ui.openArchive(rows[0])
    assert.equal(calls.at(-1)[1].expected_connection_key,'original')
    ui.config.value={...cfg,profiles:{test:{connection_key:'new'}}};await vue.nextTick()
    search.resolve({data:{data:{items:[{name:'previous-account'}],next_cursor:null}}});await oldDetail
    assert.equal(ui.archiveRows.value.length,0);assert.equal(ui.archiveVisible.value,false)
    jobs=deferred();const outdated=ui.readArchiveJobs();const older=jobs;jobs=null;rows=seed(null,'new')
    await ui.readArchiveJobs();older.resolve(seed());await outdated;assert.equal(ui.archiveJobsScope.value,'test:new')
    rows=seed();await ui.readArchiveJobs();assert.match(ui.error.value,/账套信息不一致/);assert.equal(ui.openingPrimary.value,'')
    await ui.startSync();assert.equal(calls.length,old+1)
    rows=seed(null,'new').slice(1);await ui.readArchiveJobs();assert.match(ui.error.value,/不完整/)
    rows=seed(null,'new');await ui.readArchiveJobs();await ui.startSync('warehouses')
    assert.deepEqual(calls.at(-1),['start',['warehouses'],'new','opening'],'单类首次同步也应防重复')
    assert.match(hints.at(-1),/已排队/);receipt={queued:false,skipped:{warehouses:'queued'}};await ui.startSync('warehouses')
    assert.match(hints.at(-1),/未新建/);receipt={};await ui.startSync('warehouses');assert.match(hints.at(-1),/未确认/)
    console.log('PASS: real mobile opening screen, ten groups, scoped commands, read-only permission, refresh vs resume, ERP handoff, no duplicates and no stale account responses')
}
run().catch(e=>{console.error(e);process.exitCode=1})
