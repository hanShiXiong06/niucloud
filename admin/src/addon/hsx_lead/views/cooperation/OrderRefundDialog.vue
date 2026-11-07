<template>
    <el-dialog v-model="visible" title="订单退款" width="min(720px, 94vw)" :close-on-click-modal="false" :close-on-press-escape="!busy" :show-close="!busy" :before-close="beforeClose" @closed="clear">
        <el-alert v-if="readError" :title="readError" type="error" :closable="false" show-icon class="refund-notice"><template #default><el-button link :disabled="busy || reading" @click="readDetail">重新读取</el-button></template></el-alert>
        <div v-loading="reading" class="refund-content">
            <template v-if="detail.order_id">
                <div class="refund-summary"><div><span>原支付订单</span><strong>{{ detail.order_no }}</strong></div><div><span>{{ detail.status==='refunded' ? '本次退款金额' : '本次拟退金额' }}</span><strong class="refund-amount">{{ coopMoney(detail.amount) }}</strong></div><div><span>处理进度</span><el-tag :type="statusTone(detail.status)" effect="plain">{{ detail.status_name || statusLabel(detail.status) }}</el-tag></div></div>
                <el-alert v-if="actionError" :title="actionError" type="warning" :closable="false" show-icon class="refund-notice"><template #default><el-button link :disabled="busy || reading" @click="readDetail">核对最新进度</el-button></template></el-alert>
                <el-alert v-if="detail.block_reason || detail.message" :title="detail.block_reason || detail.message" type="warning" :closable="false" show-icon class="refund-notice" />
                <el-descriptions :column="2" border size="small" class="refund-facts">
                    <el-descriptions-item label="退款方式">原支付渠道退回</el-descriptions-item><el-descriptions-item label="原退款单号">{{ detail.refund_no || '尚未创建' }}</el-descriptions-item>
                    <el-descriptions-item v-if="detail.requested_at" label="申请时间">{{ coopTime(detail.requested_at) }}</el-descriptions-item><el-descriptions-item v-if="detail.review_at" label="审核时间">{{ coopTime(detail.review_at) }}</el-descriptions-item>
                    <el-descriptions-item v-if="detail.reason" label="申请原因" :span="2">{{ detail.reason }}</el-descriptions-item><el-descriptions-item v-if="detail.review_reason" label="审核说明" :span="2">{{ detail.review_reason }}</el-descriptions-item>
                    <el-descriptions-item v-if="detail.purpose==='membership' && detail.components" label="退款构成" :span="2">服务费 {{ coopMoney(detail.components.service) }}<template v-if="detail.components.deposit!==undefined"> · 押金 {{ coopMoney(detail.components.deposit) }}</template><template v-if="detail.components.experience!==undefined"> · 经验费 {{ coopMoney(detail.components.experience) }}</template></el-descriptions-item>
                    <el-descriptions-item v-else-if="detail.purpose==='commission'" label="退款构成" :span="2">成交协作费 {{ coopMoney(detail.amount) }}</el-descriptions-item>
                </el-descriptions>
                <el-form v-if="showRequestForm || canReview && detail.can_review===true" label-position="top" :disabled="busy || reading || readRequired" class="refund-form"><el-form-item :label="showRequestForm ? '退款原因' : '审核说明'" required><el-input v-model="reason" type="textarea" :rows="3" maxlength="300" show-word-limit :placeholder="showRequestForm ? '请说明退款原因，至少4个字符' : '请填写审核说明，至少4个字符'" /></el-form-item></el-form>
                <el-collapse class="refund-help"><el-collapse-item title="退款范围与处理规则" name="rules"><p>本次按原订单剩余可退金额退回，不修改原收款记录。会员退款会同步核对权益、经验及推广奖励；协作费已分出的收益须先核对可追回金额。</p><p>提交不代表退款到账。支付渠道结果不明确时，只核对原退款单，不重新申请。退出只退押金，请使用“押金退还”。</p></el-collapse-item></el-collapse>
                <div v-if="logs.length" class="refund-log"><h3>处理记录</h3><el-timeline><el-timeline-item v-for="(log,index) in logs" :key="log.id || index" :timestamp="coopTime(log.create_time || log.at)"><strong>{{ log.action_label || log.label || log.action || '退款处理' }}</strong><span v-if="log.operator_name"> · {{ log.operator_name }}</span><p v-if="log.detail?.reason || log.reason || log.detail?.message || log.message">{{ log.detail?.reason || log.reason || log.detail?.message || log.message }}</p></el-timeline-item></el-timeline></div>
            </template>
            <el-empty v-else-if="!reading && !readError" description="尚未读取订单退款资料" :image-size="72" />
        </div>
        <template #footer><div class="refund-footer"><el-button :disabled="busy" @click="close">关闭</el-button><el-button v-if="readRequired" type="primary" :loading="reading" :disabled="busy" @click="readDetail">重新核对原单</el-button><template v-else-if="detail.order_id && !reading"><template v-if="canReview && detail.can_review===true"><el-button :disabled="busy" @click="review('reject')">拒绝申请</el-button><el-button type="primary" :loading="busy" @click="review('approve')">审核并申请退款</el-button></template><el-button v-else-if="canContinue && detail.can_continue===true" type="primary" :loading="busy" @click="continueRefund">继续原退款</el-button><el-button v-else-if="showRequestForm" type="primary" :loading="busy" @click="requestRefund">确认申请原路退款</el-button><el-button v-else-if="canRefresh && detail.can_refresh===true" type="primary" :loading="busy" @click="refresh">核对原退款进度</el-button></template></div></template>
    </el-dialog>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import useUserStore from '@/stores/modules/user'
import { getOrderRefund, requestOrderRefund, reviewOrderRefund, refreshOrderRefund, continueOrderRefund } from '../../api/cooperation'
import { hasLeadPagePermission } from '../../composables/navigation'
import { coopError, coopMoney, coopTime, type CoopRow } from '../../composables/cooperation'

const emit=defineEmits<{(event:'changed'):void}>(),user=useUserStore()
const allowed=(action:string)=>hasLeadPagePermission(user,'hsx_lead_cooperation_finance')&&hasLeadPagePermission(user,'hsx_lead_cooperation_order_refund_'+action)
const canDetail=computed(()=>allowed('detail')),canReview=computed(()=>canDetail.value&&allowed('review')),canCreate=computed(()=>canReview.value&&hasLeadPagePermission(user,'hsx_lead_cooperation_order_refund')),canRefresh=computed(()=>canDetail.value&&allowed('refresh')),canContinue=computed(()=>canReview.value&&allowed('continue'))
const visible=ref(false),detail=ref<CoopRow>({}),reading=ref(false),busy=ref(false),readError=ref(''),actionError=ref(''),readRequired=ref(false),reason=ref('')
const statuses:Record<string,string>={none:'未申请退款',pending:'待平台审核',approved:'审核通过，待提交',refunding:'退款处理中',refunded:'退款已确认',rejected:'申请已拒绝',cancelled:'申请已撤回',attention:'原退款待核对',failed:'渠道退款失败'}
const statusLabel=(value:string)=>statuses[value]||'状态待核对',statusTone=(value:string):'success'|'danger'|'warning'|'info'=>value==='refunded'?'success':value==='attention'||value==='failed'?'danger':value==='pending'||value==='refunding'?'warning':'info'
const logs=computed<CoopRow[]>(()=>Array.isArray(detail.value.logs)?detail.value.logs:[]),showRequestForm=computed(()=>canCreate.value&&detail.value.can_request===true&&!readRequired.value)
let selectedId=0,generation=0,disposed=false,requestId=''
function identity(){const site=Number(user.siteInfo?.site_id||0),token=String(user.token||'');return site>0&&Number.isSafeInteger(site)&&token?site+':'+token:''}
function current(actor:string,id:number,action:'detail'|'create'|'review'|'refresh'='detail'){return !disposed&&!!actor&&identity()===actor&&visible.value&&id===selectedId&&(action==='create'?canCreate.value:action==='review'?canReview.value:action==='refresh'?canRefresh.value:canDetail.value)}
function validate(value:CoopRow,id:number){if(Number(value?.order_id)!==id||!Object.prototype.hasOwnProperty.call(statuses,value.status)||typeof value.amount!=='string'||!/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/.test(value.amount)||!Array.isArray(value.logs))throw Error('退款资料未完整返回，请重新读取原单');if((value.can_review===true||value.can_continue===true)&&(typeof value.request_version!=='string'||!/^[a-f0-9]{64}$/.test(value.request_version)))throw Error('申请版本未完整返回，暂不能审核');return value}
async function open(row:CoopRow){if(!canDetail.value||busy.value)return;const id=Number(row.id);if(!Number.isSafeInteger(id)||id<=0)return;selectedId=id;detail.value={};actionError.value='';reason.value='';readRequired.value=false;requestId='refund_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,14);visible.value=true;await readDetail()}
async function readDetail(){const actor=identity(),id=selectedId;if(!current(actor,id))return false;const turn=++generation;reading.value=true;readError.value='';try{const result=await getOrderRefund(id);if(turn!==generation||!current(actor,id))return false;detail.value=validate(result.data,id);readRequired.value=false;actionError.value='';return true}catch(cause){if(turn===generation&&current(actor,id)){readError.value=coopError(cause,'退款资料读取失败');readRequired.value=true}return false}finally{if(turn===generation&&!disposed)reading.value=false}}
async function mutate(action:'create'|'approve'|'reject'|'refresh'|'continue'){
    const permission=action==='create'?'create':action==='refresh'?'refresh':'review',actor=identity(),id=selectedId,turn=generation
    if(busy.value||reading.value||readRequired.value||!current(actor,id,permission))return
    if(action==='create'&&detail.value.can_request!==true||action==='refresh'&&detail.value.can_refresh!==true||action==='continue'&&(!canContinue.value||detail.value.can_continue!==true)||['approve','reject'].includes(action)&&detail.value.can_review!==true)return
    const note=reason.value.trim();if(['create','approve','reject'].includes(action)&&note.length<4){actionError.value=action==='reject'?'请填写至少4个字符的拒绝原因':action==='approve'?'请填写至少4个字符的审核说明':'请填写至少4个字符的退款原因';return}
    const version=String(detail.value.request_version||'');busy.value=true;actionError.value='';let sent=false
    try{
        if(action!=='refresh')await ElMessageBox.confirm(action==='reject'?`拒绝订单 ${detail.value.order_no} 的退款申请？不会修改原收款。`:action==='continue'?`继续订单 ${detail.value.order_no} 已审核但尚未发送的原退款？金额 ${coopMoney(detail.value.amount)}。沿用原退款号；已提交的原退款不会重复发送。`:`确认将订单 ${detail.value.order_no} 的 ${coopMoney(detail.value.amount)} 按原支付渠道退回？系统将先核对权益和已分出的收益；提交不代表到账。`,action==='reject'?'拒绝退款申请':action==='continue'?'继续已审核退款':'确认原路退款',{type:'warning',confirmButtonText:action==='reject'?'确认拒绝':action==='continue'?'继续原退款':'核对并申请退款',cancelButtonText:'取消',distinguishCancelAndClose:true})
        if(!current(actor,id,permission)||turn!==generation||String(detail.value.request_version||'')!==version||action==='continue'&&!canContinue.value)return
        sent=true
        if(action==='create')await requestOrderRefund(id,{confirm:true,reason:note,request_id:requestId})
        else if(action==='refresh')await refreshOrderRefund(id)
        else if(action==='continue')await continueOrderRefund(id,{request_version:version})
        else await reviewOrderRefund(id,{decision:action,reason:note,request_version:version})
        if(!current(actor,id,permission))return
        const loaded=await readDetail();if(!current(actor,id,permission))return
        if(!loaded)throw Error('退款处理结果未核实，请重新读取原单，勿重复申请')
        if(action==='reject'&&detail.value.status!=='rejected')throw Error('拒绝结果未核实，请读取最新申请')
        if(action==='reject')ElMessage.success('退款申请已拒绝');else if(detail.value.status==='refunded')ElMessage.success('原渠道已确认退款成功');else ElMessage.warning(detail.value.status_name||statusLabel(detail.value.status))
        emit('changed')
    }catch(cause){if(current(actor,id,permission)&&cause!=='cancel'&&cause!=='close'){actionError.value=coopError(cause,'退款结果待核对，请读取原单，不要重复申请');if(sent)readRequired.value=true}}
    finally{busy.value=false}
}
const requestRefund=()=>mutate('create'),review=(decision:'approve'|'reject')=>mutate(decision),refresh=()=>mutate('refresh'),continueRefund=()=>mutate('continue')
function clear(){generation++;selectedId=0;detail.value={};readError.value='';actionError.value='';readRequired.value=false;reading.value=false;reason.value='';requestId=''}
function close(){if(!busy.value){visible.value=false;clear()}}
function beforeClose(done:()=>void){if(!busy.value){clear();done()}}
watch(()=>[identity(),canDetail.value,canCreate.value,canReview.value,canRefresh.value,canContinue.value].join('|'),()=>{visible.value=false;clear()})
onBeforeUnmount(()=>{disposed=true;generation++})
defineExpose({open})
</script>
<style scoped>
.refund-content{min-height:120px}.refund-summary{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr) minmax(0,1fr);gap:20px;margin:0 0 20px}.refund-summary>div{display:flex;flex-direction:column;align-items:flex-start;gap:8px;min-width:0}.refund-summary span{font-size:12px;color:var(--el-text-color-secondary)}.refund-summary strong{font-size:14px;line-height:1.6;overflow-wrap:anywhere}.refund-summary .refund-amount{font-size:22px;color:var(--el-color-danger)}.refund-facts{overflow-wrap:anywhere}.refund-notice{margin-bottom:16px}.refund-form{margin-top:20px}.refund-help{margin-top:18px}.refund-help p,.refund-log p{font-size:12px;line-height:1.8;color:var(--el-text-color-secondary);overflow-wrap:anywhere}.refund-footer{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:10px}.refund-footer .el-button+.el-button{margin-left:0}.refund-log h3{font-size:14px;margin:22px 0 16px}.refund-log strong,.refund-log span{font-size:13px}@media(max-width:600px){.refund-summary{grid-template-columns:minmax(0,1fr)}:deep(.el-descriptions__label){min-width:76px}}
</style>
