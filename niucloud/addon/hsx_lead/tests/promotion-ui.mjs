import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
import { spawnSync } from 'node:child_process'

const plugin=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..'),root=path.resolve(plugin,'../../..'),require=createRequire(path.join(root,'uni-app/package.json'))
const Vue=require('vue'),ts=require('typescript'),compiler=require('@vue/compiler-sfc'),sass=require('sass'),postcss=require('postcss'),selectorParser=require('postcss-selector-parser')
let checks=0
const check=(value,label)=>{assert.ok(value,label);checks++}
function execute(source,deps){const module={exports:{}},js=ts.transpileModule(source,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2020}}).outputText;vm.runInNewContext(`(function(require,module,exports){${js}\n})`,{Date,Math,Number,String,Map,Promise,console})(key=>{if(!(key in deps))throw Error('Unexpected dependency '+key);return deps[key]},module,module.exports);return module.exports}
const read=relative=>fs.readFileSync(path.join(plugin,relative),'utf8'),apiCalls=[]
const request=Object.fromEntries(['get','post'].map(method=>[method,async(url,data)=>{apiCalls.push({method,url,data});return{data:{}}}]))
const api=execute(read('uni-app/api/promotion.ts'),{'@/utils/request':{default:request}})
await api.promotionOverview();await api.promotionTeam({page:2});await api.promotionRewards({status:'pending'});await api.promotionInvitation(19);await api.bindPromotionInviter(19)
check(apiCalls.filter(call=>call.method==='get').length===4,'all display APIs are read-only GET requests')
check(apiCalls[4].method==='post'&&apiCalls[4].url==='hsx_lead/promotion/bind'&&apiCalls[4].data.confirm===true,'binding is an explicit confirmed POST, never a read side effect')
const summary={enabled:true,member:{member_id:11,name:'合成测试会员'},provider:{enabled:true},upstream:null,stats:{team_count:3,pending:'1.98',settled:'8.00',held:'0.00'},account:{available:'20.00',framework_cash_out:true},rules:{enabled:true,sources:{}},share:{mid:11,path:'/addon/hsx_lead/pages/profile/index?mid=11&promotion_invite=1'}}
let identity='member-a',calls=[],overviewWork=async()=>({data:summary}),teamWork=async query=>({data:{data:query.page===1?[{member_id:20}]:[{member_id:20},{member_id:21}],total:2}}),rewardWork=async()=>({data:{data:[],total:0}}),bindingWork=async()=>({data:{member_id:19}}),invitationWork=async mid=>({data:{can_bind:true,inviter:{member_id:mid,name:'合成邀请人'}}})
const stateModule=execute(read('uni-app/composables/usePromotion.ts'),{vue:Vue,'@/utils/common':{getToken:()=>identity},'../api/cooperation':{coopError:(cause,fallback)=>cause?.msg||cause?.message||fallback},'../api/promotion':{
    promotionOverview:async()=>{calls.push('overview');return overviewWork()},promotionTeam:async query=>{calls.push({type:'team',...query});return teamWork(query)},promotionRewards:async query=>{calls.push({type:'rewards',...query});return rewardWork(query)},promotionInvitation:async mid=>{calls.push({type:'invitation',mid});return invitationWork(mid)},bindPromotionInviter:async mid=>{calls.push({type:'bind',mid});return bindingWork(mid)},
}})
const s=stateModule.usePromotion()
check(!s.ready.value&&!s.team.value.length&&!s.rewards.value.length,'unloaded promotion state contains no fabricated reward data')
identity='';check(await s.reload()===false&&calls.length===0,'anonymous users cannot request account or relationship APIs')
identity='member-a';check(await s.reload()&&s.overview.value.account.available==='20.00','overview trusts the server-owned shared account summary')
await s.loadTeam();await s.loadTeam(true)
check(s.team.value.length===2&&!s.teamMore.value&&calls.filter(call=>call?.type==='team').at(-1).page===2,'team pagination appends real pages without duplicate member IDs')
teamWork=async()=>{throw{msg:'网络测试失败'}};await s.loadTeam()
check(s.team.value.length===2&&s.teamError.value==='网络测试失败','failed reads preserve prior data with an explicit failure, not an empty success')
s.rewardStatus.value='held';await s.loadRewards();check(calls.filter(call=>call?.type==='rewards').at(-1).status==='held','reward status is sent to the backend filter')
check(await s.bind(19)===false&&!calls.some(call=>call?.type==='bind'),'binding cannot run before a valid invitation inspection')
await s.inspectInvitation(0);check(!!s.invitationError.value&&!calls.some(call=>call?.type==='invitation'),'invalid inviter identifiers are blocked before transport')
await s.inspectInvitation(19);check(s.invitation.value.can_bind&&s.invitation.value.inviter.member_id===19,'invitation shows the authoritative eligible inviter')
let release;bindingWork=()=>new Promise(resolve=>{release=resolve});const first=s.bind(19),second=await s.bind(19)
check(second===false&&s.saving.value&&calls.filter(call=>call?.type==='bind').length===1,'repeated binding taps produce only one mutation')
release({data:{member_id:19}});check(await first===false&&!s.confirmedUpstream.value&&s.invitationError.value.includes('绑定响应未返回'),'incomplete write response is not accepted as a verified binding')
check(!s.saving.value,'binding always releases its submission lock')
bindingWork=async()=>{throw{msg:'绑定结果待核对'}};await s.inspectInvitation(19);check(await s.bind(19)===false&&s.invitationError.value==='绑定结果待核对','unknown binding outcome is not reported as success')
let resolveOverview;overviewWork=()=>new Promise(resolve=>{resolveOverview=resolve});const pending=s.reload();identity='member-b';resolveOverview({data:{...summary,member:{member_id:999}}});await pending
check(s.overview.value.member.member_id!==999,'late response from another login identity cannot overwrite account facts')
s.resume();check(!s.ready.value&&!s.team.value.length&&!s.overview.value.member,'new identity clears the prior relationship and finance view')
overviewWork=async()=>({data:summary});await s.reload();let resolveTeam;teamWork=()=>new Promise(resolve=>{resolveTeam=resolve});const list=s.loadTeam();s.stop();resolveTeam({data:{data:[{member_id:999}],total:1}});await list
check(!s.team.value.some(row=>row.member_id===999)&&!s.teamLoading.value,'hidden/unmounted promotion panel rejects late reads')
overviewWork=async()=>({data:{member:{member_id:11}}});check(await s.reload()===false&&!!s.error.value,'incomplete backend contract does not become a fabricated zero-money summary')
overviewWork=async()=>({data:summary});invitationWork=async mid=>({data:{can_bind:true,inviter:{member_id:mid,name:'合成邀请人'}}})
const binding=stateModule.usePromotion();await binding.reload();await binding.inspectInvitation(19)
bindingWork=async()=>({data:{upstream:{member_id:19,name:'合成邀请人'},already_bound:false}})
overviewWork=async()=>{throw{msg:'绑定后读取失败'}}
check(await binding.bind(19)===false&&binding.confirmedUpstream.value.member_id===19&&binding.invitationError.value.includes('已保存'),'confirmed write plus failed refresh is explicitly kept as saved-but-not-refreshed, not overall success')
const bindCount=calls.filter(call=>call?.type==='bind').length;check(await binding.bind(19)===false&&calls.filter(call=>call?.type==='bind').length===bindCount,'a confirmed upstream prevents repeat mutation after refresh failure')
overviewWork=async()=>({data:summary});const complete=stateModule.usePromotion();await complete.reload();await complete.inspectInvitation(19)
bindingWork=async()=>{invitationWork=async()=>({data:{can_bind:false,current_upstream:{member_id:19},inviter:{member_id:19}}});overviewWork=async()=>({data:{...summary,upstream:{member_id:19,name:'合成邀请人'}}});return{data:{upstream:{member_id:19,name:'合成邀请人'},already_bound:false}}}
check(await complete.bind(19)===true&&complete.overview.value.upstream.member_id===19,'success requires matching persisted upstream in write response and subsequent relationship reads')
for(const relative of ['uni-app/components/cooperation/CoopPromotionPanel.vue','uni-app/components/cooperation/CoopPromotionEntryCard.vue','admin/views/promotion/index.vue']){
    const source=read(relative),parsed=compiler.parse(source,{filename:relative});check(parsed.errors.length===0,relative+' SFC parses')
    compiler.compileScript(parsed.descriptor,{id:'promotion-ui-test'})
    const template=compiler.compileTemplate({source:parsed.descriptor.template.content,filename:relative,id:'promotion-ui-test',compilerOptions:{expressionPlugins:['typescript']}});check(template.errors.length===0,relative+' template compiles')
    for(const style of parsed.descriptor.styles){const css=style.lang==='scss'?sass.compileString(style.content,{logger:{warn(){}}}).css:style.content;postcss.parse(css).walkRules(rule=>selectorParser(ast=>ast.walkUniversals(()=>{throw Error(relative+' contains unsupported universal selector')})).processSync(rule.selector))}
    check(!/\b(?:window|document|localStorage|navigator)\s*\.|@prototype|shared\/state|D\.member|D\.reward/.test(source),relative+' does not import browser fixtures or a synthetic ledger')
}
const panel=read('uni-app/components/cooperation/CoopPromotionPanel.vue')
const incomePanel=read('uni-app/components/cooperation/CoopIncomePanel.vue')
check(panel.includes('openIncome')&&incomePanel.includes('/app/pages/member/apply_cash_out')&&incomePanel.includes("'cashOutAccountType', 'commission'")&&!incomePanel.includes('requestPayment'),'unified income withdrawals reuse the framework commission UI, not a new payment channel')
check(panel.includes('useShare')&&panel.includes('isStop:true')&&panel.includes('open-type="share"')&&panel.includes('restoreShare'),'invitations use framework sharing and restore earlier share context')
check(panel.includes('position:sticky')&&panel.includes('bindConfirmed')&&panel.includes('ui.bind(inviterMid.value)'),'tabs remain sticky and invitation binding requires explicit local consent')
check(!/模拟提现|本地模拟消息|本地原型|DEMO-MID|演示身份/.test(panel),'production panel has no demo withdrawal, notifications or role controls')
const admin=read('admin/views/promotion/index.vue')
check(admin.includes('HsxTable')&&admin.includes('HsxDrawer')&&admin.includes('HsxPagination')&&admin.includes('PageShell'),'platform UI reuses supplied shared components and page access wrapper')
check(!admin.includes("value:'cancelled'")&&!panel.includes("cancelled:'已取消'"),'reward filters display only real backend states, not invented cancelled rewards')
check(admin.includes("canConfigRead=computed(()=>allows('hsx_lead_promotion_config'))")&&admin.includes("canConfigWrite=computed(()=>allows('hsx_lead_promotion_config_save'))"),'configuration view and write grants are separate')
check(admin.includes("canProviderRead=computed(()=>allows('hsx_lead_promotion_providers'))")&&admin.includes("canProviderWrite=computed(()=>allows('hsx_lead_promotion_providers_save'))"),'provider view and write grants are separate')
check(admin.includes("canHold=computed(()=>allows('hsx_lead_promotion_hold'))")&&admin.includes('v-if="canProviderRead"')&&admin.includes('v-if="canConfigRead"'),'read tabs do not grant write/hold actions')
check(admin.includes("canResume=computed(()=>allows('hsx_lead_promotion_resume'))")&&admin.includes("canResume && row.status==='held'"),'only held rewards expose the separately authorized resume action')
check(admin.includes('原金额、比例、上级和到期时间')&&admin.includes('可能在下一批处理中记入会员佣金')&&admin.includes("result.data?.status!=='pending'"),'resume explains consequences and checks the persisted pending state before success')
check(admin.includes('inputValue:resumeReasons.get(Number(row.id))')&&admin.includes('resumeReasons.set(Number(row.id),reason)'),'failed resume retains the human verification reason for the next attempt')
const adminApiCalls=[],adminRequest=Object.fromEntries(['get','post','put'].map(method=>[method,async(url,data)=>{adminApiCalls.push({method,url,data});return{data:{}}}]))
const adminApi=execute(read('admin/api/promotion.ts'),{'@/utils/request':{default:adminRequest}})
await adminApi.resumePromotionReward(7,'核实原支付未退款')
check(adminApiCalls[0].method==='post'&&adminApiCalls[0].url==='hsx_lead/promotion/rewards/7/resume'&&adminApiCalls[0].data.reason==='核实原支付未退款','resume uses the real plugin endpoint and sends the required human reason')
// Execute the actual administrator script with isolated dialogs/transport, not only source assertions.
const adminUser=Vue.reactive({rules:['hsx_lead_promotion_resume']}),dialogs=[],notices=[],resumeCalls=[]
let promptWork=async()=>({value:'已核实原支付未退款'}),resumeWork=async id=>({data:{id,status:'pending'}})
const adminDeps={vue:{...Vue,onMounted(){},onBeforeUnmount(){}},'element-plus':{ElMessage:{success:value=>notices.push({kind:'success',value}),warning:value=>notices.push({kind:'warning',value})},ElMessageBox:{prompt:async(...args)=>{dialogs.push(args);return promptWork(...args)},confirm:async()=>{}}},'@element-plus/icons-vue':{Refresh:{}},'@/stores/modules/user':{default:()=>adminUser},'../../composables/navigation':{hasLeadPagePermission:(user,key)=>user.rules.includes(key)},'../../composables/cooperation':{coopError:(cause,fallback)=>cause?.msg||cause?.message||fallback,coopMoney:value=>value,coopTime:value=>value},'../../api/promotion':{resumePromotionReward:async(id,reason)=>{resumeCalls.push({id,reason});return resumeWork(id,reason)},getPromotionRewards:async()=>({data:{data:[],total:0}})}}
for(const name of ['HsxTable','HsxPagination','HsxDrawer','HsxSearchInput'])adminDeps[`@/addon/hsx_components/components/${name}/index.vue`]={default:{}}
adminDeps['../components/PageShell.vue']={default:{}}
const adminScript=compiler.parse(admin).descriptor.scriptSetup.content+'\nexport {resume,holding,rewardError,resumeReasons};'
const adminState=execute(adminScript,adminDeps)
await adminState.resume({id:7,status:'settled'});check(!dialogs.length&&!resumeCalls.length,'already credited reward cannot enter the resume dialog or API')
let acceptPrompt;promptWork=()=>new Promise(resolve=>{acceptPrompt=resolve});const pendingResume=adminState.resume({id:7,status:'held'});await adminState.resume({id:7,status:'held'})
check(adminState.holding.value&&dialogs.length===1&&!resumeCalls.length,'resume locks repeated taps while the human verification dialog is open')
acceptPrompt({value:'已核实原支付未退款'});await pendingResume
check(resumeCalls.length===1&&resumeCalls[0].reason==='已核实原支付未退款'&&notices.at(-1).kind==='success'&&!adminState.holding.value,'verified pending response completes exactly one resume and releases the lock')
promptWork=async()=>({value:'保留的核实说明'});resumeWork=async()=>{throw{msg:'恢复结果待核实'}};await adminState.resume({id:8,status:'held'})
check(adminState.rewardError.value==='恢复结果待核实'&&adminState.resumeReasons.get(8)==='保留的核实说明','failed resume keeps its human reason and explicit unknown result')
resumeWork=async id=>({data:{id,status:'held'}});const successfulNotices=notices.length;await adminState.resume({id:8,status:'held'})
check(dialogs.at(-1)[2].inputValue==='保留的核实说明'&&notices.length===successfulNotices&&adminState.rewardError.value.includes('恢复结果未完整返回'),'retry restores the reason and cannot claim success while backend still says held')
adminUser.rules=[];const priorDialogCount=dialogs.length;await adminState.resume({id:9,status:'held'})
check(dialogs.length===priorDialogCount,'read or unrelated permissions cannot invoke resume')
const menuResult=spawnSync(process.env.PHP_BINARY||'php',['-n','-r','echo json_encode(require $argv[1]);',path.join(plugin,'app/dict/menu/site.php')],{encoding:'utf8'})
check(menuResult.status===0,'real PHP menu metadata loads without framework boot or database access')
const allMenus=[];function flatten(rows){for(const row of rows){allMenus.push(row);flatten(row.children||[])}}flatten(JSON.parse(menuResult.stdout))
const pageMenu=allMenus.find(row=>row.view_path==='promotion/index')
check(pageMenu?.menu_key==='hsx_lead_promotion_manage'&&admin.includes(`permission="${pageMenu.menu_key}"`),'actual menu entry and PageShell permission key match')
const expectedPermissions=[['hsx_lead_promotion_config','hsx_lead/promotion/config','get'],['hsx_lead_promotion_config_save','hsx_lead/promotion/config','put'],['hsx_lead_promotion_providers','hsx_lead/promotion/providers','get'],['hsx_lead_promotion_providers_save','hsx_lead/promotion/providers/<id>','put'],['hsx_lead_promotion_rewards','hsx_lead/promotion/rewards','get'],['hsx_lead_promotion_settle','hsx_lead/promotion/settle','post'],['hsx_lead_promotion_hold','hsx_lead/promotion/rewards/<id>/hold','post'],['hsx_lead_promotion_resume','hsx_lead/promotion/rewards/<id>/resume','post']]
for(const[key,url,method]of expectedPermissions)check(allMenus.some(row=>row.menu_key===key&&row.api_url===url&&row.methods===method),'real menu/API grant is distinct and matches '+key)
const service=read('app/service/cooperation/PromotionService.php')
for(const key of ['config_save','providers_save','hold','resume'])check(service.includes(`$this->admin('${key}')`),'service independently enforces '+key+' permission')
console.log(`PASS ${checks} live promotion frontend checks. Actual state/API modules and SFC/CSS compile; isolated transport doubles only, no real binding, payment or withdrawal.`)
