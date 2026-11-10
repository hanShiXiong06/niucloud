<template>
    <section v-if="canRead" class="notice-panel" aria-label="线索通知记录">
        <div class="notice-panel__intro">
            <span>渠道受理不代表用户已读；结果不明的通知不会重复发送。</span>
            <el-button link type="primary" :loading="loading" :disabled="busy" @click="load">刷新记录</el-button>
        </div>
        <el-form class="notice-panel__filters" label-position="top" :disabled="loading || busy" @submit.prevent="search">
            <el-form-item label="通知对象">
                <el-input v-model="filters.recipient" clearable placeholder="姓名、会员编号或会员 ID" @keyup.enter="search" @clear="search" />
            </el-form-item>
            <el-form-item label="渠道">
                <el-select v-model="filters.channel" clearable placeholder="全部渠道">
                    <el-option v-for="item in channels" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
            </el-form-item>
            <el-form-item label="状态">
                <el-select v-model="filters.status" clearable placeholder="全部状态">
                    <el-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value" />
                </el-select>
            </el-form-item>
            <div class="notice-panel__filter-actions">
                <el-button :disabled="loading || busy" @click="reset">重置</el-button>
                <el-button type="primary" native-type="submit" :loading="loading" :disabled="busy">查询</el-button>
            </div>
        </el-form>
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="notice-panel__error">
            <template #default><el-button link type="primary" :disabled="loading || busy" @click="load">重新读取</el-button></template>
        </el-alert>
        <HsxTable :columns="columns" :data="rows" :loading="loading" :max-height="460" row-key="id" :empty-text="error ? '通知记录未能读取，请重试' : '暂无符合条件的通知记录'">
            <template #time="{row}"><div class="notice-cell"><span>{{ coopTime(row.create_time) }}</span><small v-if="row.update_time && row.update_time !== row.create_time">更新 {{ coopTime(row.update_time) }}</small></div></template>
            <template #event="{row}"><div class="notice-cell"><strong>{{ eventLabel(row) }}</strong><small>{{ phaseLabel(row.phase) }}</small></div></template>
            <template #recipient="{row}"><div class="notice-cell"><strong>{{ row.recipient_label || (row.channel === 'queue' ? '通知任务' : '对象待核对') }}</strong><small v-if="row.recipient_member_id">会员 ID：{{ row.recipient_member_id }}</small></div></template>
            <template #channel="{row}">{{ channelLabel(row.channel) }}</template>
            <template #template="{row}"><div class="notice-cell"><strong>{{ row.notice_key || '未记录' }}</strong><small v-if="row.template_id">模板：{{ row.template_id }}</small></div></template>
            <template #status="{row}"><div class="notice-cell"><el-tag :type="statusTone(row.status)" size="small" effect="plain">{{ statusLabel(row.status) }}</el-tag><small v-if="Number(row.retry_count)>0">已复查 {{ row.retry_count }} 次</small></div></template>
            <template #result="{row}"><div class="notice-cell"><strong v-if="hasCode(row.provider_code)">渠道返回码：{{ row.provider_code }}</strong><span>{{ row.message || '未记录渠道说明' }}</span><small v-if="unverifiedNoticeIds.has(Number(row.id))">本次复查结果待核实，请先刷新记录。</small><small v-else-if="row.retry_block_reason">{{ row.retry_block_reason }}</small></div></template>
            <template #actions="{row}"><el-button v-if="retryAllowed(row)" link type="primary" :disabled="loading || busy" @click="beginRetry(row)">重新检查并发送</el-button><span v-else class="notice-cell__muted">—</span></template>
        </HsxTable>
        <div class="notice-panel__pagination">
            <HsxPagination :current-page="query.page" :page-size="query.limit" :total="total" :disabled="loading || busy" @change="changePage" />
        </div>
        <el-dialog v-model="retryVisible" title="重新检查并发送通知" width="min(520px, 94vw)" append-to-body :close-on-click-modal="false" :close-on-press-escape="!busy" :show-close="!busy" :before-close="beforeCloseRetry" @closed="clearRetry">
            <template v-if="selected.id">
                <p class="notice-panel__retry-summary">{{ selected.recipient_label || '所选通知对象' }} · {{ channelLabel(selected.channel) }} · {{ eventLabel(selected) }}</p>
                <el-alert title="将重新核对原线索、通知对象和渠道配置；仅确认未发送时才提交，不会补发过时业务。" type="warning" :closable="false" show-icon />
                <el-alert v-if="selected.channel==='sms'" title="短信提交后可能计费，请确认接收人和发送原因。" type="warning" :closable="false" show-icon class="notice-panel__error" />
                <el-alert v-if="retryError" :title="retryError" type="error" :closable="false" show-icon class="notice-panel__error" />
                <el-form label-position="top" class="notice-panel__retry-form" :disabled="busy || readRequired">
                    <el-form-item label="复查原因" required><el-input v-model="reason" type="textarea" :rows="3" maxlength="200" show-word-limit placeholder="请说明本次重新检查并发送的原因" /></el-form-item>
                    <el-checkbox v-model="confirmed">已核对通知对象，确认重新检查并发送</el-checkbox>
                </el-form>
            </template>
            <template #footer>
                <el-button :disabled="busy" @click="retryVisible=false">取消</el-button>
                <el-button v-if="readRequired" type="primary" :loading="loading" @click="refreshAfterUnknown">重新读取记录</el-button>
                <el-button v-else type="primary" :loading="busy" :disabled="!confirmed || !reason.trim() || !retryAllowed(selected) || loading" @click="retry">重新检查并发送</el-button>
            </template>
        </el-dialog>
    </section>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import useUserStore from '@/stores/modules/user'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import { getCooperationNotices, retryCooperationNotice } from '../../api/cooperation'
import { hasLeadPagePermission } from '../../composables/navigation'
import { coopError, coopTime, type CoopRow } from '../../composables/cooperation'

const props=withDefaults(defineProps<{ leadId:number; active?:boolean }>(),{active:true})
const user=useUserStore()
const canRead=computed(()=>hasLeadPagePermission(user,'hsx_lead_cooperation_notice_list'))
const canRetry=computed(()=>canRead.value&&hasLeadPagePermission(user,'hsx_lead_cooperation_notice_retry'))
const channels=[{value:'weapp',label:'小程序'},{value:'wechat',label:'公众号'},{value:'sms',label:'短信'},{value:'queue',label:'通知任务'}]
const statuses:Record<string,string>={pending:'等待处理',processing:'处理中',processed:'任务处理完成',obsolete:'业务已过时',failed:'处理失败，待核对',submitted:'已交框架，渠道未核实',accepted:'渠道已受理',rejected:'渠道明确拒绝',not_submitted:'未提交渠道',uncertain:'结果待核实',dispatching:'提交结果待核实',disabled:'渠道未开启',pending_configuration:'待配置模板',pending_recipient:'接收人待绑定'}
const statusOptions=Object.entries(statuses).map(([value,label])=>({value,label}))
const channelLabel=(value:string)=>channels.find(item=>item.value===value)?.label||'未知渠道'
const statusLabel=(value:string)=>statuses[value]||'状态待核对'
const statusTone=(value:string):'success'|'warning'|'danger'|'info'=>value==='accepted'?'success':['rejected','failed'].includes(value)?'danger':['uncertain','dispatching','pending_configuration','pending_recipient'].includes(value)?'warning':'info'
const phases:Record<string,string>={publisher:'发布方',receiver:'承接方',assigned:'线索交付提醒',changed:'处理结果提醒',accepted:'已接单',following:'跟进中',unpaid:'成交待付佣',paid:'佣金已支付',no_deal:'未成交',cancelled:'线索已撤回',queue:'通知任务'}
const eventLabels:Record<string,string>={created:'发布客户需求',published:'发布客户需求',assigned:'交付门店',assign:'交付门店',accepted:'接单',accept:'接单',reject:'退回发布门店',withdraw:'撤回交付',following:'跟进',deal:'成交',unpaid:'成交待付佣',paid:'佣金支付',no_deal:'未成交',cancelled:'撤回线索',cancel:'撤回线索',commission_refunded:'协作费用已退还'}
const phaseLabel=(value:string)=>phases[value]||value||'业务阶段未记录'
const eventLabel=(row:CoopRow)=>eventLabels[row.event_action]||row.event_action||row.event_label||'业务通知'
const hasCode=(value:unknown)=>value!==undefined&&value!==null&&value!==''
const retryStatuses=new Set(['rejected','not_submitted','disabled','pending_configuration','pending_recipient'])
const unverifiedNoticeIds=reactive(new Set<number>())
const retryAllowed=(row:CoopRow)=>canRetry.value&&row.can_retry===true&&retryStatuses.has(String(row.status))&&Number(row.retry_count||0)<3&&!unverifiedNoticeIds.has(Number(row.id))
const filters=reactive({recipient:'',channel:'',status:''})
const query=reactive({page:1,limit:20,recipient:'',channel:'',status:''})
const rows=ref<CoopRow[]>([]),total=ref(0),loading=ref(false),error=ref('')
const retryVisible=ref(false),selected=ref<CoopRow>({}),reason=ref(''),confirmed=ref(false),busy=ref(false),retryError=ref(''),readRequired=ref(false)
const columns=[
    {prop:'create_time',label:'时间',slot:'time',minWidth:155},
    {prop:'event_action',label:'业务事件',slot:'event',minWidth:135},
    {prop:'recipient_label',label:'通知对象',slot:'recipient',minWidth:180},
    {prop:'channel',label:'渠道',slot:'channel',width:90},
    {prop:'notice_key',label:'通知 / 模板',slot:'template',minWidth:190},
    {prop:'status',label:'状态',slot:'status',minWidth:180},
    {prop:'message',label:'渠道结果 / 说明',slot:'result',minWidth:240},
    {prop:'actions',label:'操作',slot:'actions',width:145,fixed:'right' as const}
]
let generation=0,disposed=false
function identity(){return String(user.siteInfo?.site_id||'')+':'+String(user.token||'')}
function current(actor:string,id:number){return !disposed&&identity()===actor&&props.active&&props.leadId===id&&canRead.value}
async function load(){
    const actor=identity(),id=props.leadId
    if(!current(actor,id)||!Number.isSafeInteger(id)||id<=0)return
    const turn=++generation;loading.value=true;error.value=''
    try{
        const result=await getCooperationNotices(id,{...query})
        if(turn!==generation||!current(actor,id))return
        const page=result.data
        if(!page||!Array.isArray(page.data)||!Number.isFinite(Number(page.total)))throw Error('通知列表返回不完整，请核对配套后端')
        rows.value=page.data;total.value=Number(page.total);page.data.forEach((row:CoopRow)=>unverifiedNoticeIds.delete(Number(row.id)))
        query.page=Number(page.current_page)||query.page
    }catch(cause){if(turn===generation&&current(actor,id)){rows.value=[];total.value=0;error.value=coopError(cause,'通知记录读取失败，请核对权限后重试')}}
    finally{if(turn===generation&&!disposed)loading.value=false}
}
function search(){Object.assign(query,filters,{page:1});return load()}
function reset(){Object.assign(filters,{recipient:'',channel:'',status:''});return search()}
function changePage(page:{page:number;limit:number}){if(loading.value||busy.value)return;Object.assign(query,page);return load()}
function beginRetry(row:CoopRow){if(busy.value||loading.value||!retryAllowed(row))return;selected.value={...row};reason.value='';confirmed.value=false;retryError.value='';readRequired.value=false;retryVisible.value=true}
function clearRetry(){if(busy.value)return;selected.value={};reason.value='';confirmed.value=false;retryError.value='';readRequired.value=false}
function beforeCloseRetry(done:()=>void){if(!busy.value)done()}
async function refreshAfterUnknown(){await load();retryVisible.value=false;clearRetry()}
async function retry(){
    const actor=identity(),id=props.leadId,row={...selected.value},note=reason.value.trim()
    if(busy.value||loading.value||readRequired.value||!retryVisible.value||!confirmed.value||!note||note.length>200||!current(actor,id)||!retryAllowed(row))return
    busy.value=true;retryError.value=''
    try{
        const result=await retryCooperationNotice(id,Number(row.id),{confirm:true,reason:note})
        if(!current(actor,id))return
        if(Number(result.data?.id)!==Number(row.id))throw Error('未能核实本次通知处理结果，请刷新记录，不要重复发送')
        retryVisible.value=false
        if(result.data.status==='accepted')ElMessage.success('渠道已受理；不代表用户已读')
        else ElMessage.warning('检查已完成，请查看最新通知状态')
        await load()
    }catch(cause){if(current(actor,id)){unverifiedNoticeIds.add(Number(row.id));retryError.value=coopError(cause,'处理结果待核实，请重新读取记录，不要重复提交');readRequired.value=true;confirmed.value=false}}
    finally{busy.value=false;if(!retryVisible.value)clearRetry()}
}
watch(()=>[props.leadId,props.active,identity(),canRead.value],()=>{
    generation++;loading.value=false;error.value='';rows.value=[];total.value=0;query.page=1;retryVisible.value=false;unverifiedNoticeIds.clear()
    if(!busy.value)clearRetry()
    if(props.active&&canRead.value)void load()
},{immediate:true})
watch(canRetry,value=>{if(!value&&!busy.value){retryVisible.value=false;clearRetry()}})
onBeforeUnmount(()=>{disposed=true;generation++})
</script>
<style scoped>
.notice-panel{min-width:0}.notice-panel__intro{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px;color:var(--el-text-color-secondary);font-size:12px;line-height:1.7}.notice-panel__intro .el-button{flex:none}.notice-panel__filters{display:grid;grid-template-columns:minmax(170px,1.5fr) minmax(110px,1fr) minmax(150px,1fr) auto;gap:12px;align-items:end}.notice-panel__filters :deep(.el-form-item){margin-bottom:16px;min-width:0}.notice-panel__filters :deep(.el-select){width:100%}.notice-panel__filter-actions{display:flex;gap:8px;margin-bottom:16px}.notice-panel__filter-actions .el-button+.el-button{margin-left:0}.notice-panel__error{margin-top:12px;margin-bottom:12px}.notice-cell{display:flex;flex-direction:column;align-items:flex-start;gap:4px;line-height:1.7;min-width:0;overflow-wrap:anywhere}.notice-cell strong{font-size:12px;font-weight:500}.notice-cell small,.notice-cell__muted{font-size:12px;color:var(--el-text-color-secondary)}.notice-panel__pagination{display:flex;justify-content:flex-end;margin-top:16px}.notice-panel__retry-summary{margin:0 0 16px;font-size:14px;line-height:1.7;overflow-wrap:anywhere}.notice-panel__retry-form{margin-top:18px}.notice-panel__retry-form :deep(.el-checkbox){height:auto;white-space:normal;line-height:1.7}@media(max-width:760px){.notice-panel__filters{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}.notice-panel__filters .el-form-item:first-child{grid-column:1/-1} .notice-panel__filter-actions{grid-column:1/-1;justify-content:flex-end}.notice-panel__intro{align-items:flex-start}}
</style>
