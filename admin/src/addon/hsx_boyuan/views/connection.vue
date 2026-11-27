<template>
    <DeskShell title="连接设置" subtitle="将本站连接到博远。保存和核验都不会发单。">
        <template #extra><el-button :loading="loading" :disabled="!!busy" @click="refreshWithConfirm">重新载入</el-button></template>
        <div v-if="loading && !state" class="desk-card" role="status"><el-skeleton :rows="4" animated /></div>
        <el-alert v-if="warning" type="error" :title="warning" :closable="false" show-icon class="banner" role="alert" />
        <template v-if="state">
            <el-alert v-if="!state.can_edit" title="你有查看权限。如需修改连接，请联系站点管理员。" type="info" :closable="false" show-icon class="banner" />
            <div class="desk-split">
                <section class="desk-card">
                    <div class="panel-head"><h2>开放平台凭据</h2><HsxTag tone="neutral">{{ state.mode === 'mock' ? '首次接入' : '当前：'+envName(state.mode) }}</HsxTag></div>
                    <el-form label-position="top" @submit.prevent="save" class="credentials">
                        <el-form-item label="连接环境">
                            <el-radio-group aria-label="连接环境" :model-value="mode" :disabled="locked || !state.can_edit || state.dispatching" @change="changeMode">
                                <el-radio-button label="test">测试环境（UAT）</el-radio-button><el-radio-button label="production">正式环境</el-radio-button>
                            </el-radio-group>
                        </el-form-item>
                        <el-alert v-if="legacy" title="此环境使用兼容网关，开放平台表单不会覆盖原连接。" type="warning" :closable="false" class="banner" />
                        <template v-else>
                            <el-form-item label="服务地址 URL" for="by-url" :error="errors.endpoint" required>
                                <el-input id="by-url" v-model="d.endpoint" :disabled="!editable" autocomplete="off" placeholder="https://…" />
                                <div class="helper"><span>填写博远提供的 API 基础地址。</span><el-button v-if="editable" link type="primary" @click="d.endpoint=profile?.suggested_url || d.endpoint">使用默认地址</el-button></div>
                            </el-form-item>
                            <div class="field-grid">
                                <el-form-item label="账套 ID（公司代码）" for="by-account" :error="errors.account_id" required><el-input id="by-account" v-model="d.account_id" :disabled="!editable" maxlength="128" placeholder="填写公司代码" autocomplete="off" /></el-form-item>
                                <el-form-item label="AppID（应用 ID）" for="by-id" :error="errors.app_id" required><el-input id="by-id" v-model="d.app_id" :disabled="!editable" maxlength="32" inputmode="numeric" placeholder="填写数字 AppID" autocomplete="off" /></el-form-item>
                            </div>
                            <el-form-item label="AppSecret（AppSec / 应用密钥）" for="by-secret" :error="errors.app_secret" :required="needsSecret">
                                <el-input id="by-secret" v-model="d.app_secret" type="password" :disabled="!editable" maxlength="32" autocomplete="new-password" :placeholder="needsSecret ? '填写 32 位应用密钥' : '已保存 · 留空保留原密钥'" />
                                <div class="helper">已保存的密钥不回显；更换目标地址、账套或 AppID 时须重新填写。</div>
                            </el-form-item>
                            <el-form-item label="客户供应商电话取自">
                                <el-select v-model="d.party_phone_field" :disabled="!editable" aria-label="客户供应商电话取自">
                                    <el-option label="联系电话（contact_phone）" value="contact_phone" />
                                    <el-option label="助记码（help_code）" value="help_code" />
                                </el-select>
                                <div class="helper">只对本账套生效。更改后重新读取「客户供应商」即可按新电话搜索。</div>
                            </el-form-item>
                            <el-checkbox v-model="d.contract_confirmed" :disabled="!editable">已向博远确认此账套开通采购入库接口</el-checkbox>
                        </template>
                        <div v-if="state.dispatching" class="gate"><span>同步正在运行，连接资料已锁定。</span><el-button v-if="state.can_edit" link type="primary" :disabled="locked" @click="pause">暂停同步以编辑</el-button></div>
                        <div v-if="!legacy" class="actions">
                            <el-button v-if="mayEdit" native-type="submit" :type="dirty || !saved ? 'primary' : 'default'" :disabled="locked || (saved && !dirty)" :loading="busy==='save'">保存连接</el-button>
                            <el-button v-if="state.can_probe && canCheck" :type="verified ? 'default' : 'primary'" :disabled="locked" :loading="busy==='probe'" @click="verify">{{ verified ? '重新核验账套' : '核验已保存的账套' }}</el-button>
                            <span v-if="dirty" class="muted">有未保存的修改</span>
                        </div>
                    </el-form>
                </section>
                <aside class="side">
                    <section class="desk-card"><div class="panel-head"><h2>当前进度</h2><HsxTag :tone="verified ? 'success' : saved || stale ? 'warning' : 'neutral'" dot>{{ stale ? '待核对' : verified ? '已核验' : saved ? '待核验' : '待配置' }}</HsxTag></div>
                        <p class="summary">{{ stale ? '请重新载入，确认当前账套和上次保存结果。' : verified ? '博远已确认账套 '+profile?.account_id : saved ? '连接信息已保存，尚未核验账套身份。' : state.can_edit ? '先填写左侧连接资料，再核验账套。' : '尚未配置，请联系站点管理员。' }}</p>
                        <p v-if="lastCheck" class="desk-caption">上次核验：{{ lastCheck }}</p>
                        <p v-if="dirty && saved" class="desk-caption">此处显示已保存的连接，不含本次编辑。</p>
                        <p v-if="saved && !verified && !state.can_probe" class="desk-caption">请有「核验账套」权限的管理员继续。</p>
                        <el-button v-if="verified && !dirty && $router.hasRoute(routes.opening)" type="primary" plain @click="$router.push({name:routes.opening})">下一步：准备基础资料</el-button>
                    </section>
                    <section v-if="mode==='production' && verified && !dirty && state.purchase_release" class="desk-card">
                        <div class="panel-head"><h2>正式采购同步</h2><HsxTag :tone="state.dispatching ? 'success' : 'neutral'">{{ state.dispatching ? '已启用' : '未启用' }}</HsxTag></div>
                        <p v-if="state.purchase_release.recorded_ref" class="summary">已登记的上线依据：{{ state.purchase_release.recorded_ref }}</p>
                        <p v-else-if="state.purchase_release.candidate" class="summary">已核验测试采购 {{ state.purchase_release.candidate.test_bill }}（测试账套 {{ state.purchase_release.candidate.test_account }}）。</p>
                        <p v-else class="summary">本站没有可用的测试采购回执。先在测试账套同步一张采购并核验串号，回到这里后由系统带出单号。</p>
                        <p class="desk-caption">测试单号只用来确认采购可以上线。正式单号在博远收单后显示在原采购单上。付款凭证不随此开关发送。</p>
                        <el-button v-if="state.purchase_release.eligible" :loading="busy==='release'" :disabled="locked" @click="releasePurchase">开启手动采购同步</el-button>
                        <div v-if="state.voucher_release?.ready" class="voucher-release">
                            <h3>付款凭证同步</h3><p class="desk-caption">仅登记财务已确认的原付款。每张凭证单独确认，不会自动打款。</p>
                            <el-button :disabled="locked" :loading="busy==='vouchers'" @click="toggleVouchers">{{ state.voucher_release.enabled ? '关闭手动凭证同步' : '开启手动凭证同步' }}</el-button>
                        </div>
                    </section>
                    <InterfaceTraceControl v-if="state.interface_trace" :status="state.interface_trace" :can-edit="state.can_edit"
                        :disabled="locked || dirty || !state.active_key || state.mode==='mock'" :busy="busy==='trace'"
                        :hint="state.mode==='mock' || !state.active_key ? '先保存一个真实连接。' : dirty ? '请先保存或放弃连接修改。' : ''" @toggle="toggleTrace" />
                    <details class="desk-card help"><summary>这四项资料从哪里拿？</summary>
                        <p>向博远对接人索取同一环境的「开放平台接入资料」：API 地址、公司代码（账套 ID）、AppID 和 AppSec。</p><p>这里不用填员工登录账号或密码。首次联调选测试环境；不要混用正式凭据。</p>
                    </details>
                </aside>
            </div>
            <p v-if="feedback" class="feedback" role="status">{{ feedback }}</p>
        </template>
    </DeskShell>
</template>
<script setup lang="ts">
import {computed, nextTick, onMounted, onActivated, onDeactivated, onBeforeUnmount, reactive, ref, watch} from 'vue'
import {onBeforeRouteLeave} from 'vue-router'
import {ElMessageBox} from 'element-plus'
import DeskShell from '../components/DeskShell.vue'
import InterfaceTraceControl from '../components/InterfaceTraceControl.vue'
import HsxTag from '@/addon/hsx_components/components/HsxTag/index.vue'
import {readConnection, saveConnection, pauseConnection, verifyConnection, toggleInterfaceTrace, enableManualPurchase, setManualVouchers, type ConnectionState, type Environment} from '../api/connection'
import {routes, complete, decodeState, envName, normalizeUrl, reason, validateConnection} from '../utils/connection'

const state=ref<ConnectionState|null>(null), mode=ref<Environment>('test'), loading=ref(false), busy=ref(''), stale=ref(false)
const warning=ref(''), feedback=ref(''), errors=ref<Record<string,string>>({}), d=reactive({endpoint:'',account_id:'',app_id:'',app_secret:'',contract_confirmed:false,party_phone_field:'contact_phone'})
const profile=computed(()=>state.value?.profiles[mode.value]), legacy=computed(()=>!!profile.value && profile.value.protocol!=='openapi_v153')
const locked=computed(()=>loading.value || !!busy.value || stale.value)
const mayEdit=computed(()=>!!state.value?.can_edit && !state.value.dispatching && !legacy.value)
const editable=computed(()=>mayEdit.value && !locked.value)
const sameIdentity=computed(()=>!!profile.value && normalizeUrl(d.endpoint)===profile.value.endpoint && d.account_id.trim()===profile.value.account_id && d.app_id.trim()===profile.value.app_id)
const needsSecret=computed(()=>!sameIdentity.value || !profile.value?.app_secret_configured)
const dirty=computed(()=>!!profile.value && (mode.value!==(state.value!.mode==='mock'?'test':state.value!.mode) || locallyEdited.value))
const locallyEdited=computed(()=>!!profile.value && (normalizeUrl(d.endpoint)!==(profile.value.endpoint || profile.value.suggested_url) || d.account_id.trim()!==profile.value.account_id || d.app_id.trim()!==profile.value.app_id || d.app_secret!=='' || d.contract_confirmed!==!!profile.value.contract_confirmed || d.party_phone_field!==(profile.value.party_phone_field||'contact_phone')))
const saved=computed(()=>state.value?.mode===mode.value && complete(profile.value))
const verified=computed(()=>!stale.value && saved.value && Number(profile.value?.probe_at)>0)
const lastCheck=computed(()=>verified.value ? new Date(Number(profile.value!.probe_at)*1000).toLocaleString('zh-CN',{hour12:false}) : '')
const canCheck=computed(()=>saved.value && !dirty.value && !legacy.value)
watch(()=>[d.account_id,d.app_id,d.endpoint],()=>{if(profile.value?.connection_key && !sameIdentity.value){d.contract_confirmed=false;d.party_phone_field='contact_phone'}},{flush:'sync'})
let epoch=0, inactive=false
function populate(m:Environment){mode.value=m;const p=state.value!.profiles[m];Object.assign(d,{endpoint:p.endpoint || p.suggested_url,account_id:p.account_id,app_id:p.app_id,app_secret:'',contract_confirmed:!!p.contract_confirmed,party_phone_field:p.party_phone_field||'contact_phone'});errors.value={}}
function install(s:ConnectionState){state.value=s;populate(s.mode==='mock'?'test':s.mode);stale.value=false}
async function load(){const n=++epoch;loading.value=true;stale.value=true;warning.value='';feedback.value='';d.app_secret=''
    try{const s=decodeState(await readConnection());if(n===epoch) install(s)}
    catch(e){if(n===epoch){state.value=null;warning.value=reason(e,'读取失败，请重新载入。')}}
    finally{if(n===epoch) loading.value=false}}
async function confirm(text:string,title:string){try{await ElMessageBox.confirm(text,title,{type:'warning',confirmButtonText:'确定',cancelButtonText:'取消',distinguishCancelAndClose:true});return true}catch{return false}}
async function refreshWithConfirm(){if(busy.value || loading.value) return;if(locallyEdited.value && !(await confirm('重新载入将放弃尚未保存的修改。','重新载入连接？'))) return;await load()}
async function changeMode(m:unknown){if((m!=='test' && m!=='production') || locked.value || !state.value?.can_edit || state.value.dispatching) return
    if(locallyEdited.value && !(await confirm('切换后将清空未保存的输入，不会自动保存或发单。','切换连接环境？'))) return
    populate(m);feedback.value='';warning.value=''}
function context(){return {mode:mode.value,revision:state.value!.revision,connection_key:profile.value!.connection_key}}
async function toggleVouchers(){
    const s=state.value
    if(!s?.voucher_release?.ready || locked.value || dirty.value || !verified.value || mode.value!=='production')return
    const n=epoch, expected=context(), account=s.profiles.production.account_id, enable=!s.voucher_release.enabled
    busy.value='vouchers'
    try{
        if(!await confirm((enable?'开启':'关闭')+'正式账套 '+account+' 的手动凭证同步？此操作不会发送凭证或打款。','付款凭证同步') || n!==epoch)return
        const r=(await setManualVouchers({...expected,confirm_account:account,enable}))?.data
        if(!await reconcile(n))return
        if(!r || r.documents_sent!==0 || r.enabled!==enable || r.account_id!==account || state.value?.voucher_release?.enabled!==enable)throw Error('结果未确认，请刷新查看，不要重复提交。')
        feedback.value=enable?'已开启。财务可在原采购上核对已有凭证，无需再次付款。':'手动凭证同步已关闭，历史回执仍可查看。'
    }catch(e){if(n===epoch)failed(e)}finally{if(n===epoch)busy.value=''}
}
async function releasePurchase(){
    const s=state.value, release=s?.purchase_release
    if(locked.value || dirty.value || !verified.value || mode.value!=='production' || !s?.can_edit || !release?.eligible) return
    const n=epoch, expected=context(), account=s.profiles.production.account_id, task=release.candidate?.task_id??0
    busy.value='release'
    try {
        if(!await confirm('将为正式账套 '+account+' 开启手动采购同步。每张采购仍需在原单确认；有未处理的发单任务会先提示核对。不会开启付款。','开启正式采购同步？') || n!==epoch)return
        const r=(await enableManualPurchase({...expected,confirm_account:account,candidate_task_id:task}))?.data
        if(!await reconcile(n)) return
        const current=state.value!
        if(!r || r.documents_sent!==0 || r.manual_purchase!==true || r.mode!=='production' || r.account_id!==account || r.revision!==expected.revision+1 ||
            !current.dispatching || current.mode!==expected.mode || current.active_key!==expected.connection_key || current.revision!==r.revision || current.finance_active)
            throw Error('开启结果尚未确认，请重新载入；不要重复提交单据。')
        feedback.value='正式手动采购同步已开启。请回到要处理的原采购单核对后提交。'
    }catch(e){if(n===epoch)failed(e)}finally{if(n===epoch)busy.value=''}
}
async function toggleTrace(intent:'start'|'stop',windowId:string){
    const s=state.value;if(locked.value || dirty.value || !s?.can_edit || !s.active_key || s.mode!==mode.value || !s.interface_trace)return
    const n=epoch;busy.value='trace';warning.value='';feedback.value=''
    try{
        const receipt=(await toggleInterfaceTrace({...context(),intent,window_id:windowId}))?.data
        if(!await reconcile(n))return
        const actual=state.value!.interface_trace
        if(!actual || state.value!.active_key!==s.active_key || state.value!.revision!==s.revision || !receipt || typeof receipt.window_id!=='string' ||
            receipt.window_id!==actual.window_id || (intent==='start'?!/^BW[a-f0-9]{16}$/.test(actual.window_id):actual.window_id!==''))
            throw Error('记录状态已变化，请重新载入。')
        feedback.value=intent==='start'?'已开始记录。现在可以核验账套或重现异常。':'已停止记录。已写入的日志仍可用同一命令查看。'
    }catch(e){if(n===epoch)failed(e)}finally{if(n===epoch)busy.value=''}
}
async function reconcile(n:number){const s=decodeState(await readConnection());if(n!==epoch) return false;install(s);return true}
function failed(e:unknown){warning.value=reason(e,'操作结果未确认，请重新载入当前账套。');stale.value=true;feedback.value='';d.app_secret=''}
async function save(){if(!editable.value || (saved.value && !dirty.value)) return
    errors.value=validateConnection(d,mode.value,needsSecret.value);const first=Object.keys(errors.value)[0];if(first){await nextTick();const ids:Record<string,string>={endpoint:'by-url',account_id:'by-account',app_id:'by-id',app_secret:'by-secret'};document.getElementById(ids[first])?.focus();return}
    const n=epoch, old=state.value!, switching=old.mode!==mode.value && (old.mode!=='mock' || mode.value==='production'), replacing=!!profile.value!.connection_key && !sameIdentity.value
    // Keep an explicit account confirmation. Never reuse a saved secret for a different target.
    if((switching || replacing) && !(await confirm('将使用'+envName(mode.value)+'的账套 '+d.account_id.trim()+'。不会发单，历史单据仍归原连接。','确认目标账套？')))return
    if(n!==epoch || !editable.value)return
    busy.value='save';warning.value='';feedback.value=''
    const data={revision:old.revision,mode:mode.value,endpoint:normalizeUrl(d.endpoint),account_id:d.account_id.trim(),app_id:d.app_id.trim(),contract_confirmed:d.contract_confirmed,party_phone_field:d.party_phone_field,confirm_target:switching || replacing?d.account_id.trim():'',...(d.app_secret?{app_secret:d.app_secret}:{})}
    try{await saveConnection(data); if(n!==epoch)return; d.app_secret='';if(!await reconcile(n))return
        if(mode.value!==data.mode || state.value!.revision!==data.revision+1 || profile.value!.account_id!==data.account_id || profile.value!.app_id!==data.app_id || profile.value!.endpoint!==data.endpoint || (profile.value!.party_phone_field || 'contact_phone')!==data.party_phone_field || !saved.value)throw Error('保存后账套状态已变化，请重新载入核对。')
        feedback.value='连接资料已保存。'+(verified.value?'':'下一步：核验已保存的账套。')}
    catch(e){if(n===epoch) failed(e)}finally{if(n===epoch)busy.value=''}}
async function verify(){if(locked.value || !state.value?.can_probe || !canCheck.value)return
    const n=epoch, target=context(), account=profile.value!.account_id;busy.value='probe';warning.value='';feedback.value=''
    try{const r=(await verifyConnection(target))?.data;if(r?.verified!==true || r.mode!==target.mode || r.account_id!==account || r.write_performed!==false)throw Error('未收到匹配账套的核验结果。')
        if(!await reconcile(n))return;if(!verified.value || profile.value!.connection_key!==target.connection_key || state.value!.revision!==target.revision)throw Error('核验期间连接已变更，请重新载入。')
        feedback.value='博远已确认账套 '+account+'，可以准备基础资料。'}
    catch(e){if(n===epoch) failed(e)}finally{if(n===epoch)busy.value=''}}
async function pause(){const s=state.value;if(locked.value || !s?.can_edit || !s.dispatching)return;const n=epoch
    if(!await confirm('暂停后不再发起新的单据同步。已发送的单据不会撤销，可继续查历史结果。','暂停本站单据同步？') || n!==epoch || locked.value)return
    busy.value='pause';warning.value='';feedback.value=''
    try{await pauseConnection({revision:s.revision,mode:s.mode,connection_key:s.active_key});if(!await reconcile(n))return
        if(state.value!.dispatching || state.value!.active_key!==s.active_key || state.value!.revision!==s.revision+1)throw Error('暂停结果未能确认，请重新载入。')
        feedback.value='已暂停单据同步。'}
    catch(e){if(n===epoch)failed(e)}finally{if(n===epoch)busy.value=''}}
function leave(){inactive=true;epoch++;d.app_secret='';busy.value='';state.value=null;feedback.value=''}
onBeforeRouteLeave(()=>busy.value ? false : !locallyEdited.value || confirm('离开将放弃本次修改，不会保存密钥。','放弃未保存的修改？'))
onMounted(load);onDeactivated(leave);onActivated(()=>{if(inactive){inactive=false;void load()}});onBeforeUnmount(leave)
</script>
<style scoped>
.banner{margin-bottom:16px}.panel-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.credentials{margin-top:20px;max-width:760px}.credentials :deep(.el-form-item){margin-bottom:19px}.credentials :deep(.el-form-item__label){font-weight:550;margin-bottom:5px}.field-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.helper,.muted{font-size:12px;color:var(--el-text-color-secondary);line-height:1.6}.helper{display:flex;align-items:center;gap:10px;margin-top:4px}.actions,.gate{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.actions{border-top:1px solid var(--el-border-color-lighter);margin-top:16px;padding-top:18px;min-height:30px}.actions .el-button+.el-button{margin:0}.gate{margin-top:12px;color:var(--el-text-color-secondary);font-size:13px}
.side{display:grid;gap:16px}.summary{font-size:14px;line-height:1.65;margin:16px 0 6px}.side .el-button{margin-top:14px}.help summary{font-size:14px;cursor:pointer;font-weight:600}.help p,.feedback{font-size:13px;line-height:1.7}.feedback{color:var(--el-color-success-dark-2);padding:10px 14px;border-radius:6px;background:var(--el-color-success-light-9);margin-top:16px}
@media(max-width:720px){.field-grid{grid-template-columns:1fr;gap:0}}
</style>
