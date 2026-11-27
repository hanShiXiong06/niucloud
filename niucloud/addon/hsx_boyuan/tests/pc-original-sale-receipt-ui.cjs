// Real Vue setup with isolated in-memory ports: no browser, database, network or credentials.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm')
const root=path.resolve(__dirname,'../../../..'),modules=path.join(root,'admin/node_modules')
const sfc=require(path.join(modules,'@vue/compiler-sfc')),ts=require(path.join(modules,'typescript')),vue=require(path.join(modules,'vue'))
let scripts={}
for(const [addon,file] of [['hsx_boyuan','components/OriginalSaleReceiptPanel.vue'],['hsx_boyuan','extensions/erp-document-result.vue'],
    ['hsx_erp','components/ErpDocumentSyncResult.vue'],['hsx_erp','views/erp/sale/list.vue']]){
    const src=fs.readFileSync(path.join(root,'niucloud/addon',addon,'admin',file),'utf8')
    assert.equal(src,fs.readFileSync(path.join(root,'admin/src/addon',addon,file),'utf8'))
    const {descriptor,errors}=sfc.parse(src,{filename:file});assert.deepEqual(errors,[])
    const script=sfc.compileScript(descriptor,{id:'fixture'});scripts[file]=script.content
    const t=sfc.compileTemplate({source:descriptor.template.content,filename:file,id:'fixture',compilerOptions:{bindingMetadata:script.bindings}})
    assert.deepEqual(t.errors,[])
}
let calls=[],cancel=false,fail=false
const task={id:8,status:'awaiting_confirmation',mode:'test',revision:1,connection_key:'test-80004'}
let doc
function reset(){calls=[];cancel=false;fail=false;doc={task:{...task},source:{sale_id:3,settlement_id:7,settlement_no:'R7',amount:'2.00'},
    missing:['account_id'],selection:{staff_id:'Test staff',account_id:''},can_submit:true,allocation_status:'待核对',external_no:''}}
const mocks={vue:{...vue,onUnmounted(){}},'element-plus':{ElMessageBox:{confirm:async()=>{calls.push('confirm');if(cancel)throw Error('cancel')}}},
    '../utils/purchase-form':{purchaseRouteId:k=>/^[1-9]\d*$/.test(String(k))?Number(k):0},'../api/opening-archives':{readOpeningRows:async()=>({data:{code:'OK',data:{source:'local_archive',items:[]}}})},
    '../api/receipt':{prepareOriginalReceipt:async(sid,sale)=>{calls.push('prepare:'+sid+':'+sale);return {data:{vendor_task_id:8}}},
        readOriginalReceipt:async id=>{calls.push('read:'+id);return {data:JSON.parse(JSON.stringify(doc))}},
        submitOriginalReceipt:async(id,p)=>{assert.equal(id,8);assert.equal(p.sale_id,3);assert.equal(p.settlement_id,7);assert.equal(p.mode,'test');assert.equal(p.confirm_registration,true);assert.equal(p.selection.account_id,'a');calls.push('submit');if(fail)throw Error('timeout');doc.task.status='registered_review';doc.can_submit=false;doc.external_no='R-442'}}}
const m={exports:{}},js=ts.transpileModule(scripts['components/OriginalSaleReceiptPanel.vue'],{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}}).outputText
vm.runInNewContext(js,{module:m,exports:m.exports,require:k=>{if(!mocks[k])throw Error('Unknown: '+k);return mocks[k]}})
function open(extra={}){return m.exports.default.setup(vue.reactive({saleId:3,settlementId:7,taskId:0,readOnly:false,...extra}),{expose(){}})}
const tick=()=>new Promise(resolve=>setImmediate(resolve))
// The operator layout preserves all records. Expanding details does not infer that
// two providers reported the same task merely because their invoice numbers match.
function foldCases(){
    const script=scripts['components/ErpDocumentSyncResult.vue'].replace(/import\.meta\.glob(?:<[^\n]*?>)?\('[^']*'\)/g,'({})')
    const js=ts.transpileModule(script,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}}).outputText
    const m={exports:{}},ctx={vue:{...vue,onErrorCaptured(){}},'vue-router':{useRouter:()=>({})},'element-plus':{},'../hooks/useSourceDocument':{resultTarget:()=>null}}
    vm.runInNewContext(js,{module:m,exports:m.exports,require:k=>ctx[k]})
    const basic={provider_label:'供应商',status:'succeeded',status_label:'已确认',tone:'success',external_no:'S42'}
    const bridge={...basic,key:'bridge',task_id:4,query:{kind:'sale',original_sale_id:'7',sale_record_role:'bridge_delivery'}}
    const device={...basic,key:'device',task_id:5,query:{original_sale_id:'7',sale_record_role:'displayed_serial_receipt',displayed_task_id:'5'}}
    const receipt={...basic,key:'receipt',task_id:6,status:'registered',external_no:'R1',query:{kind:'receipt'}}
    const other={...device,key:'other',task_id:99},warning={...bridge,key:'warning',tone:'warning'}
    const read=(props)=>{const x=m.exports.default.setup(props,{emit(){},expose(){}});return JSON.parse(JSON.stringify(x.groups.value.map(g=>[g.folded,g.items.map(x=>x.key)])))}
    const base={results:[bridge,device,other,warning,receipt],operatorView:'sale',originalSaleId:7,hasSerialEvidence:true}
    assert.deepEqual(read(base),[[false,['receipt','other','warning']],[true,['bridge','device']]])
    assert.deepEqual(read({...base,originalSaleId:11}),[[false,['receipt','bridge','device','other','warning']]])
    assert.deepEqual(read({...base,hasSerialEvidence:false}),[[false,['receipt','device','other','warning']],[true,['bridge']]])
    for(const operatorView of ['purchase','payable'])assert.deepEqual(read({...base,operatorView}),[[false,['bridge','device','other','warning','receipt']]])
}
;(async()=>{
    foldCases()
    reset();let vm=open();assert.deepEqual(calls,[]);await vm.prepare();assert.deepEqual(calls,['prepare:7:3','read:8']);assert.equal(vm.ready.value,false)
    await vm.send();assert.equal(calls.length,2);vm.chosen.account_id='a';assert.equal(vm.ready.value,true)
    cancel=true;await vm.send();assert.equal(calls.at(-1),'confirm');cancel=false;await Promise.all([vm.send(),vm.send()])
    assert.equal(calls.filter(k=>k==='submit').length,1);assert.equal(vm.doc.value.external_no,'R-442');assert.equal(vm.editable.value,false)
    reset();vm=open({taskId:8,readOnly:true});await tick();await vm.send();assert.deepEqual(calls,['read:8'])
    reset();doc.source.settlement_id=42;vm=open({taskId:8});await tick();assert.match(vm.error.value,/未能匹配/);assert.equal(vm.editable.value,false)
    reset();doc.task.mode='production';vm=open({taskId:8});await tick();assert.equal(vm.editable.value,false);assert.deepEqual(calls,['read:8'])
    reset();doc.can_submit=false;doc.task.status='attention';vm=open({taskId:8});await tick();assert.equal(vm.editable.value,false)
    reset();vm=open({taskId:8});await tick();vm.chosen.account_id='a';fail=true;await vm.send();await vm.send();assert.equal(calls.filter(k=>k==='submit').length,1)
    console.log('PASS: four PC SFCs/mirrors, explicit prepare, missing maps, confirm/cancel, read-only and mismatch guards, no duplicate submit after timeout.')
})().catch(e=>{console.error(e);process.exitCode=1})
