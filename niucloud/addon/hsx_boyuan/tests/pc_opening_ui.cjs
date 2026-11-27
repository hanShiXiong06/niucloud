// Run: node niucloud/addon/hsx_boyuan/tests/pc_opening_ui.cjs ; no network or database.
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict')
const root=path.resolve(__dirname,'../../../..'), admin=path.join(root,'admin')
const fromAdmin=n=>require(path.join(admin,'node_modules',n))
const sfc=fromAdmin('@vue/compiler-sfc'),ts=fromAdmin('typescript'),vue=fromAdmin('vue')
const read=s=>fs.readFileSync(path.join(root,s),'utf8')
const convert=s=>ts.transpileModule(s,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}}).outputText
function mod(source,imports,globals={}) {
    const e={};vm.runInNewContext(convert(source),{exports:e,require:k=>{if(!(k in imports))throw Error(k);return imports[k]},...globals});return e
}
const plug='niucloud/addon/hsx_boyuan/admin', pc='admin/src/addon/hsx_boyuan'
for(const s of ['views/opening.vue','utils/opening.ts','utils/archive-fields.ts','components/ArchiveDetail.vue','api/opening-archives.ts','extensions/erp-opening.ts'])assert.equal(read(plug+'/'+s),read(pc+'/'+s),s)
const source=read(pc+'/views/opening.vue')
const filename='opening.vue',d=sfc.parse(source,{filename}).descriptor
const script=sfc.compileScript(d,{id:'opening'}),compiled=sfc.compileTemplate({filename,id:'opening',source:d.template.content,compilerOptions:{bindingMetadata:script.bindings}})
assert.deepEqual(compiled.errors,[])
assert.match(d.template.content,/group\.key === 'core' \|\| extraOpen/)
assert.match(d.template.content,/canStart && state\(row\) === 'unstarted'.*?@click="startOne\(row\)"/)
assert.ok(d.template.content.indexOf('<ManualDelivery') < d.template.content.indexOf('id="archive-title"'))
assert.match(d.template.content,/mode === 'test' && rows\[0\]\?\.capabilities\?\.manual_delivery === true/)
assert.equal(sfc.compileStyle({filename,id:'opening',source:d.styles[0].content,scoped:true}).errors.length,0)
const helper=mod(read(pc+'/utils/opening.ts'),{}),base=helper.openingResources.map(resource=>({resource,name:resource,connection_key:'A',mode:'test',count:12,job:null,ready:true,capabilities:{start:true,retry:true,search:true}}))
const erp='admin/src/addon/hsx_erp/views/erp/opening/index.vue', host=read(erp)
assert.equal(host,read('niucloud/addon/hsx_erp/admin/views/erp/opening/index.vue'))
assert.doesNotMatch(host,/hsx_boyuan|博远/)
const hostD=sfc.parse(host,{filename:'index.vue'}).descriptor
assert.equal(sfc.compileTemplate({filename:'index.vue',id:'erp',source:hostD.template.content}).errors.length,0)
const hostScript=hostD.scriptSetup.content
const entrySnippet=hostScript.slice(hostScript.indexOf('type OpeningEntry'),hostScript.indexOf('const mallInventoryVisible'))
const meta=mod(read(pc+'/extensions/erp-opening.ts'),{}).default
const menuSource=read('niucloud/addon/hsx_boyuan/app/dict/menu/site.php')
assert.ok(menuSource.includes("menu_key'=>'"+meta.routeName+"'"));assert.ok(menuSource.includes("'router_path'=>'hsx_boyuan/opening'"))
const testHost=(mods,routes)=>{
    const pushed=[]
    const src=entrySnippet.replace(/import\.meta\.glob<OpeningEntry>\([^\n]+?\)\)/,'modules)')+'\nexport { openIntegration, openingEntries }'
    const m=mod(src,{}, { modules:mods, computed:vue.computed,useRouter:()=>({hasRoute:r=>routes.includes(r),push:v=>pushed.push(v.name)}) })
    m.openIntegration(meta.routeName);return [m.openingEntries.value.length,pushed]
}
assert.deepEqual(testHost({},[]),[0,[]]);assert.deepEqual(testHost({a:meta},[]),[0,[]]);assert.deepEqual(testHost({a:meta,b:meta},[meta.routeName]),[1,[meta.routeName]])
const clone=v=>JSON.parse(JSON.stringify(v)),calls=[],notices=[],mounted=[],unmounted=[],timers=new Map();let timerId=0,views=clone(base),fail=false,hold,resolve,confirm=true,queueResult={ids:[99],queued:true,skipped:[]}
const api={
    readOpeningJobs:async()=>{if(fail) throw Error('读取失败'); return {data:clone(views)}},
    queueOpening:async(res,key,intent)=>{calls.push(['queue',clone(res),key,intent]);if(hold) await new Promise(r=>{resolve=r});return {data:queueResult}},
    resumeOpening:async(id,key)=>{calls.push(['resume',id,key]);return {data:{queued:true}}},
    readOpeningRows:async(resource,keyword,page,key)=>{calls.push(['read',resource,keyword,page,key]);if(hold)await new Promise(r=>{resolve=r});return {data:{code:'OK',data:{source:'local_archive',items:[{id:'record-'+key}],total:24}}}}
}
const pushed=[];const test=mod(script.content,{'vue-router':{useRouter:()=>({hasRoute:()=>true,push:route=>pushed.push(JSON.parse(JSON.stringify(route)))})},'vue':{...vue,onActivated:()=>{},onDeactivated:()=>{},onMounted:f=>mounted.push(f),onUnmounted:f=>unmounted.push(f)},'element-plus':{ElMessage:{success:text=>notices.push(['success',text]),info:text=>notices.push(['info',text])},ElMessageBox:{confirm:async()=>{if(!confirm)throw Error('cancel')}}},'../api/opening-archives':api,'../utils/opening':helper,'../components/ArchiveDetail.vue':{},'../components/OpeningPost.vue':{},'../components/ManualDelivery.vue':{}},{setTimeout:f=>{timers.set(++timerId,f);return timerId},clearTimeout:id=>timers.delete(id)})
const ui=test.default.setup({},{expose:()=>{}})
async function run(){
    await mounted[0]();assert.equal(calls.length,0);assert.equal(ui.extraOpen.value,false)
    const [core,extra]=clone(ui.groups.value)
    assert.deepEqual(core.items.map(i=>i.resource),['companies','warehouses','parties','products','staff'])
    assert.deepEqual(extra.items.map(i=>i.resource),['capital_accounts','categories','brands','members','payment_methods'])
    assert.equal(core.done,0);assert.ok([...core.items,...extra.items].every(it=>ui.usage[it.resource]))
    views[0].job={id:4,status:'completed'};views[1].job={id:5,status:'needs_review'};views[2].job={id:6,status:'failed'};views[3].job={id:7,status:'running'}
    await ui.refresh();assert.equal(ui.extraOpen.value,false);assert.equal(ui.groups.value[1].attention,2);assert.equal(ui.progress.value.completed,1);assert.equal(timers.size,1)
    const ware=ui.rows.value.find(x=>x.resource==='warehouses')
    hold=true;const pending=ui.startOne(ware);await ui.startOne(ware);hold=false;resolve();await pending
    assert.deepEqual(calls,[['queue',['warehouses'],'A','opening']])
    await ui.startOne(ware);await ui.startOne(ui.rows.value[1]);assert.equal(calls.length,1,'old row and review rows cannot start')
    await ui.resume(ui.rows.value[2]);assert.deepEqual(calls.at(-1),['resume',6,'A'])
    confirm=false;await ui.restart(ui.rows.value[1]);assert.equal(calls.length,2)
    confirm=true;await ui.restart(ui.rows.value[1]);assert.deepEqual(calls.at(-1),['queue',['categories'],'A','refresh'])
    await ui.openRows(ui.rows.value[0]);assert.deepEqual(pushed.at(-1),{name:'hsx_boyuan_catalog_page',query:{resource:'companies'}})
    await ui.openCatalog();assert.deepEqual(pushed.at(-1),{name:'hsx_boyuan_catalog_page',query:{resource:'parties'}})
    ui.extraOpen.value=true;views.forEach(r=>r.connection_key='B');await ui.refresh();assert.equal(ui.extraOpen.value,false)
    hold=true;const beforeNotice=notices.length,oldWrite=ui.startOne(ui.rows.value.find(x=>x.resource==='staff'));views.forEach(r=>r.connection_key='C');await ui.refresh();hold=false;resolve();await oldWrite
    assert.equal(notices.length,beforeNotice,'no old-account success toast');assert.equal(ui.contextKey.value,'C')
    views.forEach(r=>{r.capabilities={start:false,retry:false,search:false}});await ui.refresh();const n=calls.length
    await ui.startOne(ui.rows.value[4]);await ui.resume(ui.rows.value[2]);await ui.openRows(ui.rows.value[2]);assert.equal(calls.length,n);assert.equal(pushed.length,2)
    views[1].connection_key='FOREIGN';await ui.refresh();assert.equal(ui.contextKey.value,'')
    views=clone(base);views[0].ready=false;views[0].blocking_reason='请先验证';await ui.refresh();assert.equal(ui.canStart.value,false);assert.match(ui.statusText.value,/验证/)
    views=clone(base);views.pop();await ui.refresh();assert.equal(ui.contextKey.value,'');await ui.startOne(ui.rows.value[4]);assert.equal(calls.length,n)
    views=clone(base);views[1].mode='production';await ui.refresh();assert.equal(ui.groups.value.length,0);assert.equal(ui.canStart.value,false)
    views=clone(base);views.filter(r=>core.items.some(k=>r.resource===k.resource)).forEach(r=>{r.job={id:9,status:'completed'};r.count=0});await ui.refresh()
    assert.equal(ui.groups.value[0].done,5);assert.equal(ui.groups.value[1].done,0);assert.equal(ui.progress.value.ready,false);assert.match(ui.statusText.value,/不等于已建入/)
    queueResult={queued:false,ids:[],skipped:{members:'completed'}};await ui.startOne(ui.rows.value.find(x=>x.resource==='members'));assert.equal(notices.at(-1)[0],'info')
    queueResult={queued:false,ids:[],skipped:{}};await ui.startOne(ui.rows.value.find(x=>x.resource==='members'));assert.equal(ui.stale.value,true);assert.equal(ui.groups.value.length,0)
    views=clone(base);await ui.refresh();fail=true;await ui.refresh();assert.equal(ui.stale.value,true);assert.equal(ui.canStart.value,false);assert.match(ui.error.value,/失败/)
    fail=false;views=clone(base);views.forEach(r=>{r.mode='production';r.capabilities={start:true,read_now:true,search:true}});const row=views.find(r=>r.resource==='warehouses')
    row.job={id:37,status:'queued',cursor:'1',pages:0,processed:0};await ui.refresh()
    let pages=0
    api.readJobPage=async(id,kind,key,mode,cursor)=>{assert.deepEqual([id,kind,key,mode,cursor],[37,'warehouses','A','production','1']);pages++
        row.job={...row.job,status:'completed',pages:1,processed:4};row.count=4;return {data:{...row.job,resource:kind,mode,connection_key:key,wait_seconds:0}}}
    await ui.readQueued(ui.rows.value.find(r=>r.resource==='warehouses'));assert.equal(pages,1);assert.match(notices.at(-1)[1],/4 条/)
    await ui.readQueued(ui.rows.value.find(r=>r.resource==='warehouses'));assert.equal(pages,1)
    row.job.status='queued';views.forEach(r=>{r.capabilities.start=false});await ui.refresh()
    await ui.readQueued(ui.rows.value.find(r=>r.resource==='warehouses'));assert.equal(pages,1)
    const filename='ArchiveDetail.vue',file=sfc.parse(read(pc+'/components/'+filename),{filename}).descriptor,sc=sfc.compileScript(file,{id:'archive'})
    assert.deepEqual(sfc.compileTemplate({filename,id:'archive',source:file.template.content,compilerOptions:{bindingMetadata:sc.bindings}}).errors,[])
    assert.deepEqual(sfc.compileStyle({filename,id:'archive',source:file.styles[0].content,scoped:true}).errors,[])
    const labels=mod(read(pc+'/utils/archive-fields.ts'),{}),props={resource:'parties',connectionKey:'A',record:{id:'P',name:'测试',phone_field:'help_code'},categoryName:'客户供应商'}
    let loadDetail,aliveStop,resp={id:'P',resource:'parties',connection_key:'A',source:'snapshot',fields:{name:'测试',help_code:'13800000007',is_cust:0,x_future:0,note:null,extra:{flag:false}}},reads=0
    const component=mod(sc.content,{'./PartyPhone.vue':{},'@/addon/hsx_components/components/HsxDrawer/index.vue':{},vue:{...vue,onMounted:f=>{loadDetail=f},onBeforeUnmount:f=>{aliveStop=f}},'../api/opening-archives':{readArchiveDetail:async()=>{reads++;return {data:resp}}},'../utils/archive-fields':labels})
    const detail=component.default.setup(props,{expose:()=>{},emit:()=>{}});await loadDetail();assert.equal(reads,1);assert.equal(detail.visible.value.length,6)
    detail.query.value='客户约定电话';assert.equal(detail.visible.value[0].key,'help_code');detail.query.value='x_future';assert.equal(detail.visible.value[0].display,'0')
    detail.query.value='is_cust';assert.equal(detail.visible.value[0].display,'否');detail.query.value='';detail.hideEmpty.value=true;assert.equal(detail.visible.value.length,5)
    resp={...resp,id:'OTHER'};await detail.load();assert.ok(detail.error.value);assert.equal(detail.detail.value,null);aliveStop()
    unmounted[0]();assert.equal(timers.size,0)
    console.log('PASS: PC opening Vue/route, five common + five optional archives, single-resource submission, no false receipt, no duplicate, role and account isolation, independent ERP posting and delivery')
}
run().catch(err=>{console.error(err);process.exitCode=1})
