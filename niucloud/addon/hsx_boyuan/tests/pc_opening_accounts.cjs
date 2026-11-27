// Exercise the actual PC component with deterministic, disconnected responses.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm')
const root=path.resolve(__dirname,'../../../..'),adm=path.join(root,'admin'),dep=x=>require(path.join(adm,'node_modules',x))
const vue=dep('vue'),sfc=dep('@vue/compiler-sfc'),ts=dep('typescript'),file='OpeningAccounts.vue'
const source=fs.readFileSync(path.join(root,'niucloud/addon/hsx_boyuan/admin/components',file),'utf8')
assert.equal(source,fs.readFileSync(path.join(adm,'src/addon/hsx_boyuan/components',file),'utf8'))
const parsed=sfc.parse(source,{filename:file}),desc=parsed.descriptor;assert.equal(parsed.errors.length,0)
const script=sfc.compileScript(desc,{id:'accounts'})
assert.equal(sfc.compileTemplate({source:desc.template.content,filename:file,id:'accounts',compilerOptions:{bindingMetadata:script.bindings}}).errors.length,0)
assert.equal(sfc.compileStyle({source:desc.styles[0].content,filename:file,id:'accounts',scoped:true}).errors.length,0)
const js=ts.transpileModule(script.content,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}}).outputText
const props=vue.reactive({connectionKey:'K',ready:false,canApply:true}), call=[],clone=x=>JSON.parse(JSON.stringify(x))
let broken=false,late=null,hold=false,waitWrite=null,unknown=false,unbind
const sources=[1,4,7].map((id,i)=>({id,name:'账户'+id,company:'门店',linked:i===2,suggested_type:i===0?'wechat':''}))
const plan=xs=>xs.map(c=>({...c,action:c.local_id?'link':'create',name:'账户'+c.id,local_name:c.local_id?'本店银行':'账户'+c.id,type:c.local_id?'bank':c.type}))
const api={
    async readAccountOptions(key,cursor){call.push(['read',key,cursor]);if(hold)await new Promise(res=>late=res)
        return {data:{rows:cursor?sources.slice(2):sources.slice(0,2),next_cursor:cursor?0:4,run:broken&&cursor?'changed':'R',locals:[{id:10,name:'本店银行',type:'bank'}],types:{wechat:'微信',bank:'银行卡',other:'其他'}}}},
    async previewAccounts(k,choices){call.push(['preview',k,clone(choices)]);return {data:{token:'signed',rows:plan(choices)}}},
    async applyAccounts(k,choices,token){call.push(['apply',k,clone(choices),token]);if(hold)await new Promise(res=>waitWrite=res);if(unknown)throw Error('回执未确认');return {data:{rows:plan(choices)}}}
}
const e={}
vm.runInNewContext(js,{exports:e,require:n=>n==='vue'?{...vue,onUnmounted:f=>{unbind=f}}:n==='../api/opening-post'?api:n.includes('HsxDialog')?{}:(()=>{throw Error(n)})()})
const ui=e.default.setup(props,{expose:()=>{}}), count=k=>call.filter(x=>x[0]===k).length
const select=()=>{ui.selected.value=[1,4];ui.setDefaults([1,4]);ui.draft.value[1].local_id=0;ui.draft.value[4].local_id=10}
async function run(){
    await ui.open();assert.equal(call.length,0)
    props.ready=true;await vue.nextTick();await ui.open()
    assert.equal(count('read'),2);assert.equal(ui.sources.value.length,3);assert.equal(ui.sources.value[2].linked,true)
    assert.equal(ui.selected.value.length,0);await ui.preview();assert.equal(count('preview'),0)
    select();assert.equal(ui.complete.value,true);assert.equal(ui.draft.value[1].type,'wechat')
    ui.draft.value[1].type='';assert.equal(ui.complete.value,false);ui.draft.value[1].type='wechat'
    await ui.preview();assert.equal(count('apply'),0);assert.equal(ui.newCount.value,1);assert.equal(ui.phase.value,'preview')
    ui.editAgain();await ui.save();assert.equal(count('apply'),0);await ui.preview()
    hold=true;const saving=ui.save();await ui.save();assert.equal(count('apply'),1);hold=false;waitWrite();await saving
    assert.equal(ui.visible.value,false);assert.match(ui.lastResult.value,/已新建 1 个，关联已有 1 个/);assert.equal(call.at(-1)[3],'signed')
    props.canApply=false;await vue.nextTick();await ui.open();select();await ui.preview();await ui.save();assert.equal(count('apply'),1)
    props.canApply=true;await vue.nextTick();await ui.open();select();await ui.preview();unknown=true;await ui.save();await ui.save()
    assert.equal(count('apply'),2);assert.equal(ui.phase.value,'reload');assert.match(ui.error.value,/不要重复提交/)
    broken=true;await ui.open();assert.equal(ui.phase.value,'reload');assert.equal(ui.sources.value.length,0);broken=false
    hold=true;const pending=ui.open();props.connectionKey='K2';await vue.nextTick();hold=false;late();await pending
    assert.equal(ui.visible.value,false);assert.equal(ui.sources.value.length,0);unbind()
    console.log('PASS PC account picker: empty selection, separate existing/new targets, valid preview, role, duplicate click, uncertain result, pagination, stale account.')
}
run().catch(e=>{console.error(e);process.exitCode=1})
