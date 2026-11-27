<template>
    <section>
        <header class="coop-head"><h2>客服抽检</h2><el-button type="primary" @click="beginCreate()">记录客户回访</el-button></header>
        <CooperationFilter :query="query" :loading="loading" :statuses="statuses" placeholder="搜索线索、客户或回访内容" @search="search" @reload="load" />
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="coop-error" />
        <div class="coop-surface"><HsxTable :columns="columns" :data="rows" :loading="loading" empty-text="暂无对应的客服回访记录">
            <template #lead="{row}"><div class="coop-cell"><strong>{{ row.customer_name || row.lead?.customer_name || '客户回访' }}</strong><small>{{ row.lead_no || row.lead?.lead_no || '线索记录' }}</small><small>{{ row.receiver_name || row.lead?.receiver?.name || '承接门店未返回' }}</small></div></template>
            <template #outcome="{row}"><div class="coop-cell"><strong>客户反馈：{{ outcomeLabel(row.customer_outcome) }}</strong><small>门店记录：{{ row.merchant_outcome_label || row.lead?.status_label || coopLabel(row.merchant_outcome || row.lead?.status) }}</small></div></template>
            <template #note="{row}"><div class="coop-cell"><span>{{ row.note || '未填写回访内容' }}</span><small>{{ coopTime(row.create_time) }}</small></div></template>
            <template #status="{row}"><el-tag :type="coopTone(row.decision || row.status)" size="small">{{ row.decision ? coopLabel(row.decision) : coopLabel(row.status) }}</el-tag></template>
            <template #action="{row}"><el-button link type="primary" @click="openReview(row)">{{ row.status === 'resolved' || row.decision === 'confirmed_violation' || row.decision === 'clear' ? '查看核实记录' : '查看并复核' }}</el-button></template>
        </HsxTable><div class="coop-pagination"><HsxPagination :current-page="query.page" :page-size="query.limit" :total="total" :disabled="loading" @change="changePage" /></div></div>
        <HsxDrawer v-model="createVisible" title="记录客户回访" subtitle="只登记核实事实，不会自动拉黑门店或产生赔付。" size="md" show-footer confirm-text="保存回访记录" :confirm-loading="saving" :confirm-disabled="uploading" @confirm="create">
            <el-alert v-if="formError" :title="formError" type="error" :closable="false" show-icon class="coop-error" />
            <el-form class="coop-form" label-position="top" :disabled="saving">
                <el-form-item label="关联线索"><el-select v-model="form.lead_id" filterable remote clearable remote-show-suffix :remote-method="searchLeads" :loading="leadLoading" loading-text="正在查找线索…" no-match-text="没有匹配的线索" :no-data-text="leadError ? '线索读取失败，请重试' : '没有匹配的线索，可换客户、手机号或线索号搜索'" aria-label="搜索并选择回访关联线索" placeholder="搜索客户、手机号、门店或线索号" @visible-change="value=>value&&loadLeads()"><el-option v-for="item in leadOptions" :key="item.id" :value="Number(item.id)" :label="leadOptionLabel(item)" /><template v-if="leadError" #footer><span class="coop-danger-copy">{{ leadError }}</span> <el-button link type="primary" :loading="leadLoading" @click.stop="loadLeads()">重新读取候选</el-button></template></el-select><div v-if="leadError" class="coop-danger-copy" role="alert">{{ leadError }} <el-button link type="primary" :disabled="leadLoading" @click="loadLeads()">重新读取</el-button></div></el-form-item>
                <el-form-item label="客户反馈的交易结果"><el-radio-group v-model="form.customer_outcome"><el-radio value="unknown">尚未确认</el-radio><el-radio value="deal">已成交</el-radio><el-radio value="no_deal">未成交</el-radio></el-radio-group></el-form-item>
                <el-form-item label="回访内容与核实依据"><el-input v-model="form.note" type="textarea" :rows="5" maxlength="2000" show-word-limit placeholder="记录联系时间、客户反馈和需要进一步核实的事实" /></el-form-item>
                <el-form-item label="佐证图片（选填）"><EvidenceUpload v-model="evidence" :disabled="saving" @busy="uploading=$event" /><p class="coop-form-note">{{ uploading?'图片正在上传，请完成后保存。':'使用系统存储并记录上传归属，不自动读取微信聊天记录。' }}</p></el-form-item>
            </el-form>
        </HsxDrawer>
        <HsxDrawer v-model="reviewVisible" title="回访核实记录" :subtitle="selected.lead_no || ''" size="md" :confirm-loading="resolving">
            <section class="coop-detail-section"><div class="coop-kv"><span>客户反馈</span><span>{{ outcomeLabel(selected.customer_outcome) }}</span><span>回访时间</span><span>{{ coopTime(selected.create_time) }}</span><span>当前结论</span><span>{{ selected.decision ? coopLabel(selected.decision) : '尚待人工核实' }}</span></div><p class="coop-readout">{{ selected.note }}</p><div v-if="selected.evidence?.length" class="coop-thumb-list"><el-image v-for="(url,index) in selected.evidence" :key="url" :src="img(url)" :preview-src-list="selected.evidence.map((url:string)=>img(url))" :initial-index="index" preview-teleported fit="cover" /></div></section>
            <section v-if="canResolve" class="coop-detail-section"><h3>人工处理结论</h3><el-alert v-if="resolveError" :title="resolveError" type="error" :closable="false" show-icon class="coop-error" /><el-form label-position="top" class="coop-form" :disabled="resolving"><el-form-item label="请选择核实结论"><el-select v-model="decision"><el-option label="核查无违规，结束复核" value="clear" /><el-option label="需要继续跟进，暂不定性" value="followup" /><el-option label="已核实故意隐瞒成交 / 拒付，停止合作" value="confirmed_violation" /></el-select></el-form-item><el-form-item label="核实说明"><el-input v-model="reason" type="textarea" :rows="4" maxlength="2000" placeholder="说明核查结果与依据，不要仅凭未成交次数判断" /></el-form-item><el-checkbox v-model="confirmed">我已核对反馈与相关证据，并对本次结论负责</el-checkbox></el-form><p v-if="decision==='confirmed_violation'" class="coop-danger-copy">确认违规会永久停止与承接商户合作，历史与余额保留；平台不垫付未收到的佣金。提交时还需再次确认。</p></section>
            <section v-else class="coop-detail-section"><h3>处理说明</h3><p class="coop-readout">{{ selected.reason || selected.resolution_reason || '处理记录已保留' }}</p></section>
            <template #footer><el-button :disabled="resolving" @click="reviewVisible=false">关闭</el-button><el-button v-if="canResolve" :type="decision==='confirmed_violation'?'danger':'primary'" :loading="resolving" :disabled="!decision||!confirmed" @click="resolve">{{ decision==='confirmed_violation'?'继续确认停止合作':'保存核实结论' }}</el-button></template>
        </HsxDrawer>
    </section>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { img } from '@/utils/common'
import useUserStore from '@/stores/modules/user'
import EvidenceUpload from './EvidenceUpload.vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import CooperationFilter from './CooperationFilter.vue'
import { addCooperationReview, getCooperationLead, getCooperationLeads, getCooperationReviews, resolveCooperationReview } from '../../api/cooperation'
import { coopError, coopLabel, coopTime, coopTone, useCooperationList, type CoopRow } from '../../composables/cooperation'
import { remoteOptionRows, useRemoteOptions } from '../../composables/useRemoteOptions'
const props=withDefaults(defineProps<{ initialLeadId?:number }>(),{initialLeadId:0})
const statuses=[{value:'pending',label:'待处理'},{value:'followup',label:'继续跟进'},{value:'resolved',label:'已处理'}]
const {rows,total,loading,error,query,load,search,changePage}=useCooperationList(getCooperationReviews)
const columns=[{prop:'customer_name',label:'客户 / 关联线索',slot:'lead',minWidth:210},{prop:'customer_outcome',label:'客户与门店反馈',slot:'outcome',minWidth:190},{prop:'note',label:'回访内容 / 时间',slot:'note',minWidth:260},{prop:'status',label:'核实状态',slot:'status',width:135},{prop:'action',label:'操作',slot:'action',width:140,fixed:'right' as const}]
const outcomeLabel=(value:unknown)=>({deal:'已成交',no_deal:'未成交',unknown:'尚未确认'}[String(value)]||'尚未确认')
const createVisible=ref(false),saving=ref(false),uploading=ref(false),formError=ref(''),evidence=ref<string[]>([]),form=reactive<CoopRow>({lead_id:undefined,customer_outcome:'unknown',note:''})
const user=useUserStore(),candidateActor=()=>`${user.token||''}:${user.siteInfo?.site_id||''}`
const leadCandidates=useRemoteOptions<CoopRow>({request:async keyword=>remoteOptionRows(await getCooperationLeads({keyword,limit:20,page:1}),row=>row.id),key:row=>Number(row.id),selectedKey:()=>form.lead_id,scope:()=>`${candidateActor()}:${createVisible.value}`,errorText:coopError,fallback:'线索候选读取失败，请重新读取'})
const {options:leadOptions,loading:leadLoading,error:leadError,load:loadLeads,search:searchLeads}=leadCandidates
const leadOptionLabel=(item:CoopRow)=>[item.customer_name||'未填写客户',item.customer_mobile||item.mobile,item.lead_no||`线索 #${item.id}`].filter(Boolean).join(' · ')
let detailSequence=0,disposed=false
watch(candidateActor,()=>{detailSequence++;createVisible.value=false})
watch(createVisible,open=>{if(!open)detailSequence++})
async function beginCreate(id=0){const turn=++detailSequence,actor=candidateActor();leadCandidates.reset();Object.assign(form,{lead_id:id||undefined,customer_outcome:'unknown',note:''});formError.value='';evidence.value=[];createVisible.value=true;if(id){try{const result=await getCooperationLead(id);if(turn===detailSequence&&createVisible.value&&!disposed&&actor===candidateActor()){const [row]=remoteOptionRows({data:[result.data]},row=>row.id);leadCandidates.remember(row)}}catch(cause){if(turn===detailSequence&&createVisible.value&&!disposed&&actor===candidateActor())formError.value=coopError(cause,'关联线索无法读取，请重新选择')}}else void loadLeads('')}
async function create(){if(saving.value)return;formError.value='';if(!Number(form.lead_id)||String(form.note).trim().length<4){formError.value='请选择关联线索，并填写至少4个字的回访内容';return}saving.value=true;try{await addCooperationReview({...form,lead_id:Number(form.lead_id),note:String(form.note).trim(),evidence:evidence.value});createVisible.value=false;ElMessage.success('回访已记录，未自动改变商户资格');await load()}catch(cause){formError.value=coopError(cause,'记录未保存，已保留填写内容')}finally{saving.value=false}}
const selected=ref<CoopRow>({}),reviewVisible=ref(false),decision=ref(''),reason=ref(''),confirmed=ref(false),resolving=ref(false),resolveError=ref('')
const canResolve=computed(()=>!['resolved'].includes(selected.value.status)&&!['clear','confirmed_violation'].includes(selected.value.decision))
function openReview(row:CoopRow){selected.value={...row};decision.value='';reason.value='';confirmed.value=false;resolveError.value='';reviewVisible.value=true}
async function resolve(){if(resolving.value||!confirmed.value||!decision.value)return;resolveError.value='';if(reason.value.trim().length<4){resolveError.value='请填写明确的核实说明（至少4个字符）';return}resolving.value=true;try{if(decision.value==='confirmed_violation')await ElMessageBox.confirm('你确认已经核实该商户故意隐瞒成交或拒付？提交后将永久停止合作，并保留全部历史记录。仅客户单方反馈或未成交次数不足以作出此结论。','再次确认核实结论',{type:'warning',confirmButtonText:'已核实，确认停止合作',cancelButtonText:'继续核查'});await resolveCooperationReview(Number(selected.value.id),{decision:decision.value,reason:reason.value.trim(),confirm:true});reviewVisible.value=false;ElMessage.success(decision.value==='confirmed_violation'?'违规已记录并停止合作':'核实结论已保存');await load()}catch(cause){if(cause!=='cancel'&&cause!=='close')resolveError.value=coopError(cause,'处理未完成，请保留证据并刷新记录核对')}finally{resolving.value=false}}
watch(()=>props.initialLeadId,value=>{if(value>0)void beginCreate(value)},{immediate:true});watch(decision,()=>{confirmed.value=false})
onMounted(load);onBeforeUnmount(()=>{disposed=true;detailSequence++})
</script>
