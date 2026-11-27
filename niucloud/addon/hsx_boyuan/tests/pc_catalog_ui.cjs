// Local contract test for the real PC catalog component (no DB, browser or outbound network).
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict')
const root=path.resolve(__dirname,'../../../..'),app=path.join(root,'admin'),req=k=>require(path.join(app,'node_modules',k)),sfc=req('@vue/compiler-sfc'),ts=req('typescript'),vue=req('vue')
const read=p=>fs.readFileSync(path.join(root,p),'utf8'),src=k=>read('admin/src/addon/'+k),clone=x=>JSON.parse(JSON.stringify(x))
function js(source,imports={}){const exports={};vm.runInNewContext(ts.transpileModule(source,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}}).outputText,{exports,require:k=>{if(k in imports)return imports[k];if(k.endsWith('.vue'))return {};throw Error(k)}});return exports}
function compile(k){const text=src(k);assert.equal(text,read('niucloud/addon/'+k.replace('/','/admin/')))
    const d=sfc.parse(text,{filename:k}).descriptor,sc=sfc.compileScript(d,{id:'catalog'})
    assert.deepEqual(sfc.compileTemplate({filename:k,id:'catalog',source:d.template.content,compilerOptions:{bindingMetadata:sc.bindings}}).errors,[])
    for(const st of d.styles)assert.deepEqual(sfc.compileStyle({filename:k,id:'catalog',source:st.content,scoped:st.scoped}).errors,[])
    return {d,sc}}
compile('hsx_components/components/HsxCatalogLayout/index.vue')
const {d,sc}=compile('hsx_boyuan/views/catalog.vue'),detail=compile('hsx_boyuan/components/ArchiveDetail.vue')
assert.doesNotMatch(d.template.content+detail.d.template.content,/<el-dialog/);assert.match(detail.d.template.content,/<HsxDrawer/)
const defs=js(src('hsx_boyuan/utils/catalog.ts')),opening=js(src('hsx_boyuan/utils/opening.ts')),names=defs.directory.flatMap(g=>g.items.map(i=>i[0]))
assert.deepEqual([...names].sort(),[...opening.openingResources].sort())
for(const k of names){assert.ok(defs.titleFor(k));assert.ok(defs.catalogColumns(k).length>=4)}
assert.equal(defs.roleText({extra:{is_cust:1,is_supplier:'1'}}),'客户 / 供应商');assert.equal(defs.archiveStatus({enabled:false,extra:{status_known:false}}),'未提供')
let meta=names.map(k=>({resource:k,count:21,job:null,connection_key:'A',mode:'test',ready:true,capabilities:{search:true}})),next=null,serverError=false,all=21;const calls=[]
const apis={readOpeningJobs:async()=>({data:clone(meta)}),readOpeningRows:async(...args)=>{calls.push(clone(args));const [kind,q,p,key]=args;if(serverError)throw Error('读取异常')
    if(next){const fn=next;next=null;await new Promise(fn)}
    return {data:{code:'OK',data:{source:'local_archive',total:all,items:all?[{id:kind+key,name:'测试记录'}]:[]}}}}}
const route=vue.reactive({query:{resource:'parties'}}),router={replace:async r=>{route.query=r.query},hasRoute:()=>true},stops=[],activated=[],starts=[]
const hooks={...vue,onMounted:f=>starts.push(f),onBeforeUnmount:f=>stops.push(f),onDeactivated:f=>stops.push(f),onActivated:f=>activated.push(f)}
const screen=js(sc.content,{'vue':hooks,'vue-router':{useRouter:()=>router,useRoute:()=>route},'../utils/catalog':defs,'../utils/opening':opening,'../utils/connection':{routes:{catalog:'catalog'},reason:(e,f)=>e.message||f},'../api/opening-archives':apis})
const ui=screen.default.setup({},{expose:()=>{},emit:()=>{}});const tick=()=>new Promise(r=>setImmediate(r))
async function categoryCases(){
    const tree=js(src('hsx_boyuan/utils/category-directory.ts')),component=compile('hsx_boyuan/components/CategoryDirectory.vue')
    assert.equal(src('hsx_boyuan/utils/category-directory.ts'),read('niucloud/addon/hsx_boyuan/admin/utils/category-directory.ts'))
    const row=(id,name,parent_id=null,code=id,status='enabled',parent_name=null)=>({id,name,code,parent_id,parent_name,status})
    const rows=[row('L','末级礼品','M','01030101','disabled'),row('M','礼品','P','010301'),row('P','话费1','R','0103'),row('R','手机.123',null,'01'),
        row('X','手机.123',null,'30'),row('N','另一个末级礼品','X','99'),row('missing','找不到上级','NOT_HERE'),row('childMissing','遗失分支下级','missing'),
        row('cyc1','循环一','cyc2'),row('cyc2','循环二','cyc1'),row('self','自己指向自己','self'),row('label','只有上级同名',null,'88','unknown','手机.123')]
    const idx=tree.indexCategories(rows)
    assert.equal(idx.roots.length,2);assert.equal(idx.issues.length,6);assert.equal(idx.ordered.length,rows.length)
    assert.equal(idx.byId.get('L').depth,4);assert.equal(tree.levelName(4),'四级')
    assert.deepEqual(clone(tree.lineage(idx,idx.byId.get('L')).map(r=>r.name)),['手机.123','话费1','礼品','末级礼品'])
    assert.deepEqual(clone(tree.matchCategories(idx,'话费1/礼品','4','disabled').map(r=>r.id)),['L'])
    assert.deepEqual(clone(tree.matchCategories(idx,'','0','unknown').map(r=>r.id)),['label'])
    assert.throws(()=>tree.indexCategories([rows[0],rows[0]]),/重复/);assert.equal(tree.matchCategories(idx,'01030101','','')[0].id,'L')
    // A root with an actual ID "0" is not a sentinel for its children; ambiguous names never merge.
    assert.equal(tree.indexCategories([row('0','根'),row('Z','子','0')]).byId.get('Z').depth,2)
    const roots=Array.from({length:25},(_,i)=>row('B'+i,'根'+i,null,String(i+1)))
    const branch=[row('last','叶','mid','7001'),row('mid','分支','B24','70'),...roots]
    const props=vue.reactive({connectionKey:'A',emptyMessage:'尚未同步'}),events=[],calls=[],befores=[]
    let deferred=null,fail=false,tooBig=false,source='local_archive',readBackKey=''
    const api={readCategoryDirectory:async key=>{
        calls.push(key);if(deferred){const hold=deferred;deferred=null;await new Promise(hold)}
        if(fail)throw Error('档案服务暂不可用')
        return {data:{source,resource:'categories',connection_key:readBackKey||key,list_only:tooBig,items:tooBig?[]:branch}}
    }}
    const local=js(component.sc.content,{'vue':{...vue,onBeforeUnmount:f=>befores.push(f)},'../api/opening-archives':api,'../utils/connection':{reason:(e,f)=>e.message||f},'../utils/category-directory':tree})
    const c=local.default.setup(props,{expose:()=>{},emit:(...e)=>events.push(e)})
    await tick();assert.equal(catalogRow('last').depth,3);assert.equal(c.present.value.length,20);assert.equal(c.pageTotal.value,25)
    function catalogRow(id){return c.catalog.value.byId.get(id)}
    c.applySearch('叶');assert.equal(c.present.value[0].id,'last');assert.equal(c.pathOf(c.present.value[0]),'根24 / 分支 / 叶')
    await c.locate(c.present.value[0]);assert.equal(c.page.value,2);assert.equal(c.filtered.value,false);assert.equal(c.located.value,'last')
    assert.deepEqual(clone(c.present.value.slice(-3).map(r=>r.id)),['B24','mid','last'])
    c.detail(catalogRow('last'));assert.deepEqual(clone(events.at(-1)),['detail',{id:'last',name:'叶',categoryPath:'根24 / 分支 / 叶'}])
    c.togglePage();assert.equal(c.present.value.some(r=>r.id==='last'),false);c.togglePage();assert.equal(c.present.value.some(r=>r.id==='last'),true)
    c.level.value='3';assert.equal(c.matches.value.length,1);c.status.value='disabled';assert.equal(c.matches.value.length,0);c.clearFilters()
    c.applySearch('已清除的字','debounce');assert.equal(c.filtered.value,false,'a late debounce cannot undo reset/locate')
    assert.deepEqual(calls,['A'],'all branch/search/filter/locate actions use the same local snapshot')
    let release;deferred=r=>release=r;const old=c.load();props.connectionKey='B';await tick();release();await old;assert.equal(c.error.value,'');assert.equal(catalogRow('last').depth,3)
    readBackKey='not-B';await c.load();assert.match(c.error.value,/账套/);assert.equal(c.catalog.value.byId.size,0)
    readBackKey='';tooBig=true;await c.load();assert.equal(events.at(-1)[0],'list-only');tooBig=false
    fail=true;await c.load();assert.match(c.error.value,/暂不可用/);fail=false;await c.load();assert.equal(c.catalog.value.roots.length,25)
    befores.forEach(f=>f())
}
async function purchaseDrawerCases(){
    const {d}=compile('hsx_erp/views/erp/purchase/list.vue')
    assert.match(d.template.content,/label="商品分类 \/ 目录型号">\s*<ErpPurchaseDeviceCatalogSelect\b[\s\S]*?:selection="itemExtra\.item\.catalog_reference"/)
    assert.match(d.template.content,/<ErpCatalogProductSelect v-model="search.catalog_product_id"/) // historical query is separate
    const {sc}=compile('hsx_erp/components/ErpPurchaseDeviceCatalogSelect.vue')
    const mounted=[],props=vue.reactive({selection:null}),calls=[],events=[]
    let mode='managed',delay,refuse=false
    const sku={id:'remote-sku',category_id:'C',label:'博远设备',code:'SKU-01'}
    const server=async p=>{
        calls.push({...p});assert.equal(p.scene,'purchase_device')
        if(mode==='old')return {data:{list:[{label:'旧的演示型号'}]}}
        if(mode==='local')return {data:{managed:false}}
        if(p.node_type==='root')return {data:{managed:true,source_label:'博远 ERP',scope:'current',list:[{id:'C',label:'手机',category_path:'手机',parent_id:''}],maintenance:{hint:'在来源维护',links:[{label:'去同步',name:'catalog',query:{resource:'products'}},{label:'无权限',name:'forbidden'}]}}}
        assert.equal(p.expected_scope,'current')
        if(p.node_type==='products'){if(p.keyword==='慢')await new Promise(r=>delay=r);return {data:{managed:true,list:[{...sku,label:p.keyword||sku.label}],more:false}}}
        assert.equal(p.node_type,'selection');if(refuse)throw Error('资料已停用')
        return {data:{managed:true,choice:{...sku,node_type:'product',catalog_reference:{scope:'current',category_id:'C',product_id:sku.id,revision:'basis'}}}}
    }
    const screen=js(sc.content,{vue:{...vue,onMounted:f=>mounted.push(f),onBeforeUnmount:()=>{}},'vue-router':{useRouter:()=>({hasRoute:n=>n==='catalog',resolve:r=>({href:'/site/catalog?resource='+r.query.resource})})},'@/addon/hsx_erp/api/erp':{getErpGoodsCatalogHierarchy:server}})
    const u=screen.default.setup(props,{expose:()=>{},emit:(ev,p)=>{events.push([ev,p]);props.selection=p?.catalog_reference||null}})
    await mounted[0]();assert.equal(u.state.value,'managed');assert.equal(u.tree.value[0].id,'C');assert.deepEqual(clone(u.links.value),[{label:'去同步',href:'/site/catalog?resource=products'}])
    u.chooseCategory('C');assert.equal(u.categoryId.value,'C');const slow=u.search('慢');await u.search('SKU-01');delay();await slow
    assert.equal(u.rows.value[0].label,'SKU-01');assert.equal(calls.at(-1).category_id,'C')
    await u.chooseProduct(sku.id);assert.equal(u.productId.value,'remote-sku')
    refuse=true;await u.chooseProduct(sku.id);assert.equal(u.productId.value,'');assert.equal(u.categoryId.value,'C');assert.match(u.productError.value,/停用/)
    mode='old';await u.init();assert.equal(u.state.value,'error');assert.match(u.error.value,/更新 ERP 后端/)
    mode='local';await u.init();assert.equal(u.state.value,'local');assert.equal(props.selection,null)
}
async function go(){
    await purchaseDrawerCases()
    await categoryCases()
    await starts[0]();assert.equal(ui.navigation.value.length,4);assert.equal(ui.records.value[0].id,'partiesA')
    ui.role.value='supplier';ui.status.value='enabled';await ui.applySearch(' 甲 ');await ui.paginate({page:2,limit:20})
    assert.deepEqual(calls.at(-1),['parties','甲',2,'A',{limit:20,role:'supplier',archive_status:'enabled'}])
    ui.openDetail(ui.records.value[0]);assert.equal(ui.detailRow.value.id,'partiesA');ui.detailRow.value=null;assert.equal(ui.page.value,2)
    let release;next=r=>{release=r};const old=ui.search(2);ui.navigate('warehouses');await tick();release();await old
    assert.equal(ui.records.value[0].id,'warehousesA');assert.equal(ui.role.value,'');assert.equal(ui.page.value,1)
    all=0;await ui.applySearch('不存在');assert.match(ui.empty.value,/筛选/);await ui.reset();assert.equal(ui.filtered.value,false)
    all=21;serverError=true;await ui.search(1);assert.match(ui.listError.value,/异常/);assert.equal(ui.records.value.length,0);serverError=false
    next=r=>{release=r};const oldAccount=ui.search(1);meta.forEach(m=>m.connection_key='B');await ui.loadIndex();release();await oldAccount;assert.equal(ui.records.value[0].id,'warehousesB')
    meta.forEach(m=>m.capabilities.search=false);await ui.loadIndex();assert.equal(ui.canBrowse.value,false);assert.equal(ui.records.value.length,0)
    meta[0].connection_key='FOREIGN';await ui.loadIndex();assert.match(ui.indexError.value,/账套/);assert.equal(ui.contextKey.value,'')
    stops[0]();await ui.search(1);assert.equal(ui.detailRow.value,null)
    // ERP embedded mode is pinned to categories. Simply opening it must not start a remote read.
    compile('hsx_boyuan/extensions/erp-catalog.vue')
    let queued=0,pages=0
    meta.forEach(r=>{r.connection_key='K';r.job=null;r.capabilities={start:true,read_now:true,search:true}})
    const category=meta.find(r=>r.resource==='categories')
    apis.queueOpening=async (rs,key,intent)=>{assert.deepEqual(clone([rs,key,intent]),[['categories'],'K','refresh'])
        queued++;category.job={id:41,status:'queued',cursor:'1',next_at:0,pages:0,processed:0};return {data:{ids:[41],queued:true}}}
    apis.readJobPage=async(id,resource,key,mode,cursor)=>{assert.deepEqual([id,resource,key,mode,cursor],[41,'categories','K','test','1'])
        pages++;category.job={id,status:pages===1?'completed':'needs_review',cursor,pages:1,processed:59,reason:pages===1?'':'声明 69，实际收到 59'}
        return {data:{...category.job,resource,connection_key:key,mode,wait_seconds:0}}}
    const embedded=screen.default.setup({embeddedCategories:true},{expose:()=>{},emit:()=>{}})
    await embedded.loadIndex();assert.equal(embedded.kind.value,'categories');assert.equal(queued,0);assert.equal(embedded.syncLabel.value,'同步最新分类')
    await Promise.all([embedded.syncCurrent(),embedded.syncCurrent()]);assert.equal(queued,1);assert.equal(pages,1)
    assert.equal(embedded.syncFeedback.value.type,'success');assert.match(embedded.syncFeedback.value.text,/59 条/);assert.equal(embedded.directoryVersion.value,1)
    await embedded.syncCurrent();assert.equal(embedded.syncFeedback.value.type,'warning');assert.match(embedded.syncFeedback.value.text,/69.*59/)
    category.capabilities.start=false;await embedded.loadIndex();await embedded.syncCurrent();assert.equal(queued,2)
    const phoneCell=compile('hsx_boyuan/components/PartyPhone.vue')
    assert.match(phoneCell.d.template.content,/v-copy="value"/);assert.match(phoneCell.d.template.content,/\{\{ value \}\}/)
    assert.match(d.template.content,/:value="row.phone_number"/);assert.match(detail.d.template.content,/:value="detail.fields.help_code"/)
    assert.doesNotMatch(detail.d.template.content,/查看原值|inspectPhone/);assert.equal(defs.catalogColumns('parties').find(c=>c.label==='联系电话').slot,'partyPhone')
    let wire,stopDetail,detailReads=0;const detailHooks={...vue,onMounted:f=>wire=f,onBeforeUnmount:f=>stopDetail=f},props={resource:'parties',record:{id:'P',name:'样本',phone_field:'help_code'},categoryName:'客户与供应商',connectionKey:'A'}
    const phone=js(detail.sc.content,{'vue':detailHooks,'../utils/archive-fields':js(src('hsx_boyuan/utils/archive-fields.ts')),'../api/opening-archives':{readArchiveDetail:async()=>{detailReads++;return {data:{id:'P',resource:'parties',connection_key:'A',source:'snapshot',fields:{help_code:'0138-0000-0007',spell_code:'CUS1'}}}}}}).default.setup(props,{emit:()=>{},expose:()=>{}})
    await wire();assert.equal(phone.fields.value.find(f=>f.key==='help_code').display,'0138-0000-0007');assert.equal(detailReads,1)
    assert.match(phone.fields.value.find(f=>f.key==='help_code').label,/助记码/);assert.equal(phone.fields.value.find(f=>f.key==='spell_code').label,'编码')
    stopDetail();assert.equal(phone.detail.value,null)
    console.log('PASS: PC category hierarchy, levels, search, locate across root pages, anomaly isolation, stale replies and large-list fallback; other catalog views, agreed phone, pagination and permissions.')
}
go().catch(e=>{console.error(e);process.exitCode=1})
