import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { loadMobileUtility } from './mobile-utils.mjs'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../../../..'),require=createRequire(path.join(root,'admin/package.json'))
const Vue=require('vue'),compiler=require('@vue/compiler-sfc'),ts=require('typescript')
const file=path.join(root,'niucloud/addon/hsx_lead/uni-app/components/cooperation/CoopConnectionPanel.vue'),source=fs.readFileSync(file,'utf8')
let checks=0;const check=(value,label)=>{assert.ok(value,label);checks++}
const {descriptor,errors}=compiler.parse(source,{filename:file});check(!errors.length,'mobile SFC parses')
const script=compiler.compileScript(descriptor,{id:'merchant-renewal'})
const template=compiler.compileTemplate({source:descriptor.template.content,filename:file,id:'merchant-renewal',compilerOptions:{bindingMetadata:script.bindings,expressionPlugins:['typescript']}})
check(!template.errors.length,'mobile template compiles')
const js=ts.transpileModule(script.content,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}}).outputText
const providers=[{key:'synthetic',name:'合成 ERP',auth_mode:'password',fields:[],auth_modes:[{key:'password',fields:[{key:'username',label:'登录账号',required:true},{key:'password',label:'登录密码',required:true,secret:true}],scope_fields:[],renewal:{automatic:true}},{key:'token',fields:[{key:'access_token',secret:true}],renewal:{automatic:false}}]}]
const toasts=[]
function fixture(){
    const calls=[];let saved={connected:true,provider:'synthetic',enabled:false,active_run_id:0,credentials:{auth_mode:'token'},auth_renewal:{status:'manual_token',ready:false,message:'Token 不能自动续登'}}
    let settingsFailure=false,syncLost=false,activateLost=false,hold=null,advanceHold=null,pageCount=0,timerId=0
    const timers=new Map()
    const progress=()=>({id:66,run_id:66,status:pageCount>=3?'completed':pageCount?'running':'pending',page_count:pageCount,record_count:Math.min(pageCount*100,205),total_records:205,progress_known:true,complete:pageCount>=3,published:pageCount>=3,can_advance:pageCount<3})
    const api={coopDate:String,coopError:(e,f)=>e?.message||f,
        cooperationConnection:async()=>{calls.push(['read']);return{data:{...saved}}},
        cooperationActivate:async data=>{calls.push(['activate',data]);if(hold)await hold;if(activateLost)throw Error('结果未知');saved={...saved,credentials:{auth_mode:'password',username:'synthetic-user'},auth_renewal:{automatic:true,ready:true,status:'automatic',message:'账号密码已加密保存，可自动续登'}};return{data:{...saved}}},
        cooperationConnectionSettings:async data=>{calls.push(['settings',data]);if(settingsFailure){settingsFailure=false;throw Error('网络错误')}saved={...saved,enabled:data.enabled};return{data:{...saved}}},
        cooperationSync:async()=>{calls.push(['sync']);saved.active_run_id=66;if(syncLost){syncLost=false;throw Error('响应丢失')}return{data:{id:66,status:'pending',page_count:0,record_count:0}}},
        cooperationSyncInfo:async id=>{calls.push(['info',id]);return{data:{...progress(),id}}},
        cooperationSyncAdvance:async()=>{calls.push(['advance']);if(advanceHold)await advanceHold;pageCount++;saved.sync_progress=progress();saved.last_sync_status=progress().status;if(pageCount>=3){saved.active_run_id=0;saved.published_run_id=66}return{data:progress()}},
        cooperationProbe:async data=>{calls.push(['probe',data]);return{data:{probe_token:'synthetic-proof',preview:{can_activate:true,first_page_count:1}}}},
        cooperationChallenge:async()=>{throw Error('Not a captcha fixture')}
    }
    const module={exports:{}}
    vm.runInNewContext('(function(require,module,exports){'+js+'})',{Date,Number,String,JSON,console,setTimeout:callback=>{timers.set(++timerId,callback);return timerId},clearTimeout:id=>timers.delete(id),uni:{showToast:data=>toasts.push(data)}})(key=>key==='vue'?{...Vue,onBeforeUnmount(){}}:key==='../../api/cooperation'?api:key==='../../utils/mobile-ui'?loadMobileUtility('mobile-ui'):key==='../../utils/connection-actions'?loadMobileUtility('connection-actions'):key.endsWith('.vue')?{default:{}}:(()=>{throw Error('Unexpected dependency')})(),module,module.exports)
    const props=Vue.reactive({show:true,profile:{},providers}),emitted=[]
    const state=module.exports.default.setup(props,{expose(){},emit:(...args)=>{emitted.push(args);if(args[0]==='close')props.show=false}})
    state.connection.value={...saved};state.provider.value='synthetic'
    return{state,calls,props,emitted,timers,setAdvanceHold:value=>advanceHold=value,setSettingsFailure:()=>settingsFailure=true,setSyncLost:()=>syncLost=true,setActivateLost:()=>activateLost=true,setHold:value=>hold=value,markPublished:()=>{pageCount=3;saved.active_run_id=0;saved.published_run_id=66;saved.last_sync_status='completed';saved.sync_progress=progress()}}
}
const initial=fixture();check(initial.state.credentialFields.value.map(f=>f.key).join(',')==='username,password','merchant sees account/password, not the token test field')
check(initial.state.tokenOnly.value===true,'legacy token connection has an actionable warning')
initial.state.editAuthorization();check(initial.state.editing.value&&!('access_token'in initial.state.credentials)&&!('auth_mode'in initial.state.credentials),'merchant enters own password form without copying a token')
check(initial.state.renewalNote.value.includes('服务器加密保存')&&initial.state.renewalNote.value.includes('自动重新登录'),'password mode explains encrypted storage and future renewal')
await initial.state.probe();check(initial.calls.length===0&&initial.state.error.value.includes('登录账号'),'required account prevents request')
initial.state.credentials.username='synthetic-user';initial.state.credentials.password='synthetic-password'
await initial.state.probe();const payload=initial.calls.at(-1)[1]
check(payload.credentials.auth_mode==='password'&&payload.credentials.password==='synthetic-password','merchant own password is passed to normal verification')
check(!('merchant_id'in payload)&&!('member_id'in payload),'client cannot select another merchant through this form payload')
check(initial.state.preview.value&&initial.state.confirmed.value===false,'successful preview still requires explicit confirmation')
await initial.state.activate();check(!initial.calls.some(c=>c[0]==='activate'),'no activation without consent')
initial.state.confirmed.value=true;await initial.state.activate()
check(initial.calls.map(c=>c[0]).join(',')==='probe,activate,settings,read,sync,advance,advance,advance,read','explicit consent creates authorization, enables synchronization and advances the complete three-page inventory')
check(initial.state.connection.value.enabled===true&&initial.state.run.value.id===66,'only persisted enabled state and created task are displayed')
check(initial.state.run.value.record_count===205&&initial.state.publishedRun.value===true&&initial.state.currentSyncStatus.value==='completed'&&initial.state.syncStateLabel.value==='货盘已更新'&&initial.state.progressText.value==='本轮整理 205 条库存资料'&&!initial.state.error.value,'three pages and all 205 inventory records are published before successful terminal status is displayed')
initial.state.run.value={id:66,status:'running',record_count:100};await initial.state.refresh()
check(initial.state.syncStateLabel.value==='货盘已更新'&&initial.state.run.value.record_count===205&&initial.state.publishedRun.value===true,'authoritative connection refresh replaces a stale local one-page running cache')
check(initial.state.probeToken.value===''&&!initial.state.credentials.password&&initial.state.setupStage.value==='','consumed proof and password are cleared')
check(source.includes('同意保存授权并开启自动库存同步')&&source.includes('connectionActionState')&&loadMobileUtility('connection-actions').connectionActionState({hasPreview:true}).primaryLabel==='确认并启用同步','consent and button explain the full action')

async function verified(f){f.state.credentials.username='synthetic-user';f.state.credentials.password='synthetic-password';await f.state.probe();f.state.confirmed.value=true}
const partial=fixture();await verified(partial);partial.setSettingsFailure();await partial.state.activate()
check(partial.state.connection.value.connected&&partial.state.setupStage.value==='enable_sync'&&partial.state.error.value.includes('授权已保存'),'settings failure preserves successful authorization and distinct pending stage')
check(!partial.state.probeToken.value&&!partial.state.credentials.password,'partial success never keeps a consumed proof or form password')
await partial.state.resumeSetup();check(partial.calls.filter(c=>c[0]==='activate').length===1&&partial.state.run.value.id===66,'resume does not activate or verify again')
const lost=fixture();await verified(lost);lost.setSyncLost();await lost.state.activate()
check(lost.state.connection.value.enabled&&lost.state.setupStage.value==='start_sync'&&lost.state.error.value.includes('结果待确认'),'ambiguous task start is not called a failed authorization')
await lost.state.resumeSetup();check(lost.calls.filter(c=>c[0]==='sync').length===1&&lost.calls.some(c=>c[0]==='info'&&c[1]===66),'retry reads the existing active task instead of starting another')
const finished=fixture();await verified(finished);finished.setSyncLost();await finished.state.activate();finished.markPublished();await finished.state.resumeSetup()
check(finished.calls.filter(c=>c[0]==='sync').length===1&&finished.calls.some(c=>c[0]==='info'&&c[1]===66),'response-loss recovery also reuses a task already published by the scheduler')
const unknown=fixture();await verified(unknown);unknown.setActivateLost();await unknown.state.activate();await unknown.state.activate()
check(unknown.calls.filter(c=>c[0]==='activate').length===1&&unknown.state.error.value.includes('未确认'),'ambiguous activation never reuses probe proof')
check(unknown.state.editing.value&&!unknown.state.credentials.password,'ambiguous activation restores fresh authorization entry without retaining password')
const duplicate=fixture();await verified(duplicate);let resolve;duplicate.setHold(new Promise(done=>resolve=done));const waiting=duplicate.state.activate();await duplicate.state.activate();check(duplicate.calls.filter(c=>c[0]==='activate').length===1&&duplicate.state.busy.value,'busy state prevents duplicate activation');resolve();await waiting
const closable=fixture();await verified(closable);let finishPage;closable.setAdvanceHold(new Promise(done=>finishPage=done));const synchronizing=closable.state.activate()
for(let step=0;step<15&&!closable.state.syncing.value;step++)await Promise.resolve()
check(closable.state.syncing.value&&closable.state.busy.value,'persistent inventory reading has a distinct closable syncing phase')
closable.state.close();await Vue.nextTick();finishPage();await synchronizing
check(!closable.props.show&&closable.emitted.some(row=>row[0]==='close'),'user may leave the persistent sync task without waiting for all pages')
check(closable.calls.filter(row=>row[0]==='advance').length===1&&closable.timers.size===0,'closing ignores the in-flight page response and stops all further client advances and polling')
check(!closable.calls.some(row=>row[0]==='cancel'),'leaving the sync panel never cancels the persistent server task')
const apiSource=fs.readFileSync(path.join(root,'niucloud/addon/hsx_lead/uni-app/api/cooperation.ts'),'utf8')
check(apiSource.includes("const root = 'hsx_lead/cooperation'")&&apiSource.includes('cooperationConnection = () =>')&&!/cooperationConnection\s*=.*merchant_id/.test(apiSource),'mobile stays on authenticated own-merchant API, not manager-selected merchant routes')
console.log('PASS '+checks+' merchant renewal onboarding UI checks; real mobile setup with synthetic APIs only, no ERP/payment/database access.')
