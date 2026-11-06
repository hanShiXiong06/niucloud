<template>
    <section class="deposit-requests">
        <header class="coop-head"><div><h2>押金退还</h2></div></header>
        <CooperationFilter :query="query" :loading="loading" :statuses="statuses" :with-date="false" placeholder="搜索门店或申请单号" @search="search" @reload="load" />
        <el-alert v-if="error" class="coop-error" :title="error" type="error" :closable="false" show-icon />
        <HsxTable :columns="columns" :data="rows" :loading="loading" row-key="id" empty-text="暂无符合条件的退押金申请">
            <template #merchant="{row}"><div class="coop-cell"><strong>{{ row.merchant_name || row.name || '门店名称待核对' }}</strong><small>{{ row.request_no || row.order_no }}</small></div></template>
            <template #amount="{row}"><strong class="coop-price">{{ coopMoney(row.amount) }}</strong></template>
            <template #status="{row}"><el-tag :type="statusTone(row.status)" effect="plain">{{ statusLabel(row.status) }}</el-tag></template>
            <template #time="{row}"><div class="coop-cell"><span>{{ coopTime(row.created_at || row.create_time) }}</span><small v-if="row.updated_at || row.update_time">更新 {{ coopTime(row.updated_at || row.update_time) }}</small></div></template>
            <template #actions="{row}"><el-button v-if="canDetail" link type="primary" @click="open(row)">{{ row.status==='pending' && canReview ? '核对与审核' : '查看进度' }}</el-button></template>
        </HsxTable>
        <HsxPagination class="coop-pagination" :current-page="query.page" :page-size="query.limit" :total="total" :disabled="loading" @change="changePage" />
        <HsxDrawer v-model="visible" title="退押金核对" size="lg" :confirm-loading="busy" :body-loading="reading" @closed="clear">
            <el-alert v-if="detailError" class="coop-error" :title="detailError" type="error" :closable="false" show-icon><template #default><el-button link :disabled="busy || reading" @click="readDetail">重新读取申请</el-button></template></el-alert>
            <template v-if="detail.id">
                <div class="deposit-summary"><div><span>申请门店</span><strong>{{ detail.merchant_name || detail.name || '门店名称待核对' }}</strong></div><div><span>申请退押金</span><strong>{{ coopMoney(detail.amount) }}</strong></div><div><span>当前进度</span><el-tag :type="statusTone(detail.status)" effect="plain">{{ statusLabel(detail.status) }}</el-tag></div></div>
                <el-alert v-if="detail.status==='pending'" title="审核通过将按原支付来源申请退押金；不退服务费和经验费。退款以渠道结果为准。" type="warning" :closable="false" show-icon class="deposit-notice" />
                <el-alert v-if="actionError" class="coop-error" :title="actionError" type="warning" :closable="false" show-icon><template #default><el-button link :disabled="busy || reading" @click="readDetail">核对最新进度</el-button></template></el-alert>
                <div v-if="detail.reason" class="deposit-reason"><span>申请说明</span><p>{{ detail.reason }}</p></div>
                <div v-if="detail.review_reason" class="deposit-reason"><span>审核说明</span><p>{{ detail.review_reason }}</p></div>
                <HsxTable :columns="sourceColumns" :data="sources" row-key="order_id" empty-text="原付款来源尚未完整返回，不能审核">
                    <template #source="{row}"><div class="coop-cell"><strong>{{ row.order_no || '原付款单' }}</strong><small>{{ row.refund_no || '尚未申请渠道退款' }}</small></div></template>
                    <template #money="{row}">{{ coopMoney(row.amount) }}</template>
                    <template #result="{row}"><div class="coop-cell"><span>{{ row.status_label || statusLabel(row.status) }}</span><small v-if="row.message">{{ row.message }}</small></div></template>
                </HsxTable>
                <el-input v-if="detail.status==='pending' && canReview" v-model="reviewReason" type="textarea" :rows="3" maxlength="300" show-word-limit placeholder="填写审核说明；拒绝时必填" aria-label="退押金审核说明" class="deposit-review-input" />
                <el-collapse class="deposit-help"><el-collapse-item title="申请、审核与到账的区别" name="deposit-help"><p>申请后暂停门店新接发，已存在业务和原账目仍保留。有未完结协作或待付佣时，系统会阻止申请与审核。拒绝申请后可恢复协作。</p><p>通过审核只代表开始退款，不代表到账；“核对退款进度”只核对已申请的原退款，不会创建新退款号。出现异常时保留原单和退款号，先核实，不能重复打款。</p></el-collapse-item></el-collapse>
            </template>
            <template #footer><div class="deposit-footer"><el-button :disabled="busy" @click="visible=false">关闭</el-button><template v-if="detail.status==='pending' && canReview"><el-button :disabled="busy || reading || readRequired || !sources.length" @click="review('reject')">拒绝申请</el-button><el-button type="primary" :loading="busy" :disabled="reading || readRequired || !sources.length" @click="review('approve')">通过并申请退押金</el-button></template><el-button v-else-if="detail.can_continue===true && canReview" type="primary" :loading="busy" :disabled="reading || readRequired" @click="review('approve')">继续已审核退款</el-button><el-button v-else-if="detail.id && canRefresh && ['approved','refunding','attention'].includes(detail.status)" type="primary" :loading="busy" :disabled="reading || readRequired" @click="refresh">核对退款进度</el-button></div></template>
        </HsxDrawer>
    </section>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import useUserStore from '@/stores/modules/user'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import CooperationFilter from './CooperationFilter.vue'
import { getDeposits, getDeposit, reviewDeposit, refreshDeposit } from '../../api/deposit'
import { coopError, coopMoney, coopTime, useCooperationList, type CoopRow } from '../../composables/cooperation'
import { hasLeadPagePermission } from '../../composables/navigation'

const user=useUserStore(),canList=computed(()=>hasLeadPagePermission(user,'hsx_lead_deposit_manage')),canDetail=computed(()=>canList.value && hasLeadPagePermission(user,'hsx_lead_deposit_detail')),canReview=computed(()=>canDetail.value && hasLeadPagePermission(user,'hsx_lead_deposit_review')),canRefresh=computed(()=>canDetail.value && hasLeadPagePermission(user,'hsx_lead_deposit_refresh'))
const statusNames:Record<string,string>={pending:'待审核',approved:'审核通过',refunding:'退款处理中',refunded:'渠道已确认退款',rejected:'申请已拒绝',attention:'需核对原退款'}
const statuses=Object.entries(statusNames).map(([value,label])=>({value,label}))
// Framework actor credentials are compared only in memory, never displayed,
// persisted or sent as business parameters.
function actorIdentity(){const site=Number(user.siteInfo?.site_id||0),token=String(user.token||'');return Number.isSafeInteger(site)&&site>0&&token?site+':'+token:''}
function statusLabel(value:string){return statusNames[value] || '状态待核对'}
function statusTone(value:string){return value==='refunded'?'success':value==='attention'?'danger':value==='pending'||value==='refunding'?'warning':'info'}
const {query,rows,total,loading,error,load,search,changePage}=useCooperationList(async params=>{const actor=actorIdentity();if(!actor||disposed||!canList.value)throw Error('请先登录并取得本站押金管理权限');const result=await getDeposits(params);if(disposed||actor!==actorIdentity()||!canList.value)throw Error('登录、站点或权限已变化，请重新读取列表');return result})
const columns=[{prop:'merchant_name',label:'门店 / 申请',slot:'merchant',minWidth:220},{prop:'amount',label:'退还押金',slot:'amount',width:140},{prop:'status',label:'审核 / 退款进度',slot:'status',minWidth:170},{prop:'created_at',label:'申请 / 更新时间',slot:'time',minWidth:190},{prop:'actions',label:'操作',slot:'actions',width:150,fixed:'right' as const}]
const sourceColumns=[{prop:'order_no',label:'原收款单 / 原退款号',slot:'source',minWidth:230},{prop:'amount',label:'本笔押金',slot:'money',width:130},{prop:'status',label:'退款核对',slot:'result',minWidth:180}]
const visible=ref(false),detail=ref<CoopRow>({}),reading=ref(false),busy=ref(false),detailError=ref(''),actionError=ref(''),reviewReason=ref(''),readRequired=ref(false)
const sources=computed<CoopRow[]>(()=>Array.isArray(detail.value.sources)?detail.value.sources:[])
let selectedId=0,generation=0,disposed=false
function current(actor:string,id:number,permission:'detail'|'review'|'refresh'='detail'){return !disposed&&!!actor&&actor===actorIdentity()&&visible.value&&id===selectedId&&(permission==='review'?canReview.value:permission==='refresh'?canRefresh.value:canDetail.value)}
async function open(row:CoopRow){if(!canDetail.value||busy.value)return;const id=Number(row.id);if(!Number.isSafeInteger(id)||id<=0)return;selectedId=id;detail.value={};reviewReason.value='';actionError.value='';readRequired.value=false;visible.value=true;await readDetail()}
async function readDetail(){
    const actor=actorIdentity();if(!selectedId||!current(actor,selectedId))return false
    const epoch=++generation,id=selectedId;reading.value=true;detailError.value=''
    try{const result=await getDeposit(id);if(epoch!==generation||!current(actor,id))return false;const value=result.data;if(Number(value?.id)!==id||!statusNames[value.status]||!Array.isArray(value.sources)||typeof value.amount!=='string'||!/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/.test(value.amount)||typeof value.request_version!=='string'||!/^[a-f0-9]{64}$/.test(value.request_version))throw Error('申请及原付款来源未完整返回，请先核对');detail.value=value;readRequired.value=false;actionError.value='';return true}
    catch(cause){if(epoch===generation&&current(actor,id)){detailError.value=coopError(cause,'申请读取失败，请重新核对');readRequired.value=true}return false}
    finally{if(!disposed&&epoch===generation)reading.value=false}
}
async function review(decision:'approve'|'reject'){
    if(!canReview.value||busy.value||reading.value||readRequired.value||!sources.value.length)return
    const continuing=decision==='approve'&&detail.value.can_continue===true&&['approved','refunding','attention'].includes(detail.value.status)
    if(detail.value.status!=='pending'&&!continuing)return
    const reason=reviewReason.value.trim();if(decision==='reject'&&!reason){actionError.value='请填写拒绝原因，方便商户处理';return}
    const id=Number(detail.value.id),requestVersion=String(detail.value.request_version||''),actor=actorIdentity(),epoch=generation;if(!current(actor,id,'review')||!/^[a-f0-9]{64}$/.test(requestVersion))return;busy.value=true;actionError.value='';let submitted=false
    try{
        await ElMessageBox.confirm(continuing?'继续提交这份申请中已审核但尚未发送的原退款？沿用已保存的退款号，不创建新退款；已提交的款项不会因此再次发送。':decision==='approve'?`确认退还“${detail.value.merchant_name || detail.value.name || '该门店'}”的押金 ${coopMoney(detail.value.amount)}？将向原渠道申请退款，服务费和经验费不退；请先核对原付款来源。`:'确认拒绝退押金申请？门店可恢复协作，原付款和押金不会被修改。',continuing?'继续已审核退款':decision==='approve'?'审核并申请退押金':'拒绝退押金申请',{type:'warning',confirmButtonText:continuing?'继续原退款':decision==='approve'?'确认申请退款':'确认拒绝',cancelButtonText:'取消',distinguishCancelAndClose:true})
        if(!current(actor,id,'review')||epoch!==generation||detail.value.request_version!==requestVersion)return
        submitted=true;await reviewDeposit(id,decision,reason,requestVersion);if(!current(actor,id,'review'))return;const loaded=await readDetail();if(!current(actor,id,'review'))return
        if(!loaded || decision==='reject' && detail.value.status!=='rejected' || decision==='approve' && !['approved','refunding','refunded','attention'].includes(detail.value.status))throw Error('审核结果尚未核实，请先读取原申请，不要重复提交')
        if(decision==='reject')ElMessage.success('申请已拒绝');else if(detail.value.status==='refunded')ElMessage.success('渠道已确认退还押金');else ElMessage.warning(statusLabel(detail.value.status)+'，请继续核对原退款进度')
        if(current(actor,id,'review'))await load()
    }catch(cause){if(current(actor,id,'review')&&cause!=='cancel'&&cause!=='close'){actionError.value=coopError(cause,'审核结果尚未核实，请读取原申请');if(submitted)readRequired.value=true}}
    finally{busy.value=false}
}
async function refresh(){
    if(!canRefresh.value||busy.value||reading.value||readRequired.value||!['approved','refunding','attention'].includes(detail.value.status))return
    const actor=actorIdentity(),id=Number(detail.value.id);if(!current(actor,id,'refresh'))return
    busy.value=true;actionError.value=''
    try{await refreshDeposit(id);if(!current(actor,id,'refresh'))return;if(!await readDetail())throw Error('原退款进度未核实，请重新读取申请');if(current(actor,id,'refresh'))await load()}
    catch(cause){if(current(actor,id,'refresh')){actionError.value=coopError(cause,'退款结果尚未核实，不会自动创建新退款');readRequired.value=true}}
    finally{busy.value=false}
}
function clear(){generation++;selectedId=0;detail.value={};detailError.value='';actionError.value='';reviewReason.value='';readRequired.value=false;reading.value=false}
watch(()=>[actorIdentity(),canList.value,canDetail.value,canReview.value,canRefresh.value].join('|'),()=>{visible.value=false;clear();rows.value=[];total.value=0;error.value=''})
onMounted(load);onBeforeUnmount(()=>{disposed=true;generation++})
</script>
<style scoped>
.deposit-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;padding-bottom:20px}.deposit-summary>div{display:flex;flex-direction:column;align-items:flex-start;gap:8px;min-width:0}.deposit-summary span,.deposit-reason>span{font-size:12px;color:var(--el-text-color-secondary)}.deposit-summary strong{font-size:17px;overflow-wrap:anywhere}.deposit-notice{margin-bottom:16px}.deposit-reason{margin:14px 0;font-size:13px}.deposit-reason p{margin:6px 0;line-height:1.7;white-space:pre-wrap;overflow-wrap:anywhere}.deposit-review-input{margin-top:18px}.deposit-help{margin-top:18px;font-size:12px;line-height:1.8;color:var(--el-text-color-secondary)}.deposit-footer{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}.deposit-footer .el-button+.el-button{margin-left:0}@media(max-width:600px){.deposit-summary{grid-template-columns:minmax(0,1fr)}}
</style>
