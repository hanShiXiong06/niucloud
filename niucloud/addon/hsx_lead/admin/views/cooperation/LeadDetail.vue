<template>
    <HsxDrawer v-model="visible" title="协作线索详情" :subtitle="detail.lead_no || '正在读取最新记录'" size="md" :body-loading="loading">
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="coop-error" />
        <el-button v-if="error" link type="primary" @click="load">重新读取</el-button>
        <template v-if="detail.id && !loading">
            <el-alert v-if="detail.requires_manual_review" title="已收款，仍需人工核对" :description="detail.review_message || '本单收益入账或权益处理存在异常，请财务核对具体原因与原支付流水；不要重复收款或重复入账。'" type="warning" :closable="false" show-icon class="coop-error" />
            <section class="coop-detail-section"><div class="coop-section-caption"><h3>{{ detail.customer_name }} <el-tag size="small" type="info">{{ coopBusiness(detail.business_type,detail.business_name) }}</el-tag></h3><el-tag :type="coopTone(detail.status)">{{ detail.status_label || coopLabel(detail.status) }}</el-tag></div><div class="coop-kv"><span>客户地区</span><span>{{ detail.region_text || '未返回' }}</span><span>联系电话</span><span>{{ detail.mobile || '当前身份不可查看' }}</span><span>联系同意时间</span><span>{{ coopTime(detail.consent_at) }}</span></div></section>
            <section class="coop-detail-section"><h3>客户需求 · {{ businessDimensionLabels[detail.dimension] || '需求选项' }}</h3><div class="coop-tags"><el-tag v-for="name in coopModels(detail)" :key="name" type="info" effect="plain">{{ name }}</el-tag><span v-if="!coopModels(detail).length" class="coop-muted">需求未返回</span></div><div v-if="businessFormEntries(detail).length" class="coop-kv" style="margin-top:16px"><template v-for="field in businessFormEntries(detail)" :key="field.key"><span>{{ field.label }}</span><span>{{ field.value }}</span></template></div><p v-if="detail.remark" class="coop-readout">{{ detail.remark }}</p><div v-if="detail.attachments?.length" class="coop-thumb-list"><el-image v-for="(url,index) in detail.attachments" :key="url" :src="img(url)" :preview-src-list="detail.attachments.map((url:string)=>img(url))" :initial-index="index" preview-teleported fit="cover" /></div></section>
            <section class="coop-detail-section"><h3>门店交付与平台监管</h3><div class="coop-kv"><span>发布门店</span><span>{{ detail.publisher?.name || detail.publisher_name || '未返回' }}</span><span>承接门店</span><span>{{ detail.receiver?.name || detail.receiver_name || '尚未选择' }}</span><span>监管负责人</span><span>{{ detail.owner_name || (detail.owner_uid ? '平台员工' : '未指定') }}</span><span>接单时间</span><span>{{ coopTime(detail.accepted_at) }}</span></div><el-button v-if="viewerScope.can_transfer || detail.can_transfer" link type="primary" style="margin-top:12px" @click="beginTransfer">调整监管负责人</el-button><p class="coop-muted">由商户选择承接门店，平台仅负责监管。调整监管负责人不更换承接门店，不改变成交结果或金额。</p></section>
            <section v-if="detail.is_internal" class="coop-detail-section"><h3>本店内部交付</h3><p class="coop-muted">由同一门店成员完成交付，不收取协作费，不产生分佣或付佣经验。</p></section>
            <section v-else class="coop-detail-section"><h3>佣金与收益</h3><div class="coop-kv"><span>本线索冻结最低协作费</span><span>{{ coopMoney(detail.commission_minimum) }}</span><span>冻结平台费比例 / 最低费</span><span>{{ detail.fee_percent===undefined ? '未返回' : `${detail.fee_percent}%` }} / {{ coopMoney(detail.fee_minimum) }}</span><span>实际协作佣金</span><span>{{ coopMoney(detail.commission_amount) }}</span><span>平台维护费</span><span>{{ coopMoney(detail.fee_amount) }}</span><span>发布方应得净收益</span><span>{{ coopMoney(detail.net_amount) }}</span></div><p class="coop-muted">冻结规则来自发单时快照，不因后来业务配置改动而重算。客户货款由门店直接收取。未支付佣金不产生可提现收益。收益是否入账，以框架会员佣金账本为准。</p></section>
            <section class="coop-detail-section"><h3>处理记录</h3><div v-if="!detail.history?.length" class="coop-muted">暂无处理记录</div><el-timeline v-else><el-timeline-item v-for="item in detail.history" :key="item.id || item.create_time" :timestamp="coopTime(item.create_time)"><strong>{{ item.label || item.action }}</strong><p class="coop-muted">{{ item.content || item.remark || item.note || '' }}</p></el-timeline-item></el-timeline></section>
            <el-collapse v-if="allows('notice_list')" v-model="noticePanels"><el-collapse-item title="通知记录" name="notices"><NoticeDeliveryPanel :lead-id="Number(detail.id)" :active="noticePanels.includes('notices')" /></el-collapse-item></el-collapse>
        </template>
        <template #footer><el-button @click="visible=false">关闭</el-button><el-button v-if="detail.id && canReview" type="primary" @click="emit('review',Number(detail.id));visible=false">记录客服抽检</el-button></template>
    </HsxDrawer>
    <HsxDrawer v-model="transferVisible" title="调整监管负责人" subtitle="仅变更平台内的监管责任，不转交客户、不更换承接门店。" size="sm" show-footer confirm-text="确认调整" :confirm-loading="saving" :confirm-disabled="!targetUid || !confirmed" @confirm="transfer">
        <el-alert v-if="transferError" :title="transferError" type="error" :closable="false" show-icon class="coop-error" />
        <p class="coop-readout">{{ detail.customer_name }} · 当前监管负责人：{{ detail.owner_name || '未指定' }}</p>
        <el-form label-position="top" class="coop-form" :disabled="saving"><el-form-item label="新的监管负责人"><OwnerSelect v-model="targetUid" :exclude-uid="Number(detail.owner_uid || 0)" @selected="targetName=$event?.name||'';confirmed=false" /></el-form-item><el-checkbox v-model="confirmed">确认由 {{ targetName || '所选员工' }} 负责平台监管</el-checkbox></el-form>
    </HsxDrawer>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { img } from '@/utils/common'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import OwnerSelect from '../components/OwnerSelect.vue'
import NoticeDeliveryPanel from './NoticeDeliveryPanel.vue'
import { getCooperationLead, transferCooperationOwner } from '../../api/cooperation'
import { coopBusiness, coopError, coopLabel, coopModels, coopMoney, coopTime, coopTone, useCooperationAccess, type CoopRow } from '../../composables/cooperation'
import { businessDimensionLabels, businessFormEntries } from '../../composables/business'
const props = withDefaults(defineProps<{ modelValue: boolean; leadId: number; viewerScope?: CoopRow; canReview?: boolean }>(), { viewerScope: () => ({}), canReview: false })
const { allows }=useCooperationAccess(),noticePanels=ref<string[]>([])
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void; (event: 'changed'): void; (event: 'review', id: number): void }>()
const loading = ref(false), error = ref(''), detail = ref<CoopRow>({}), saving = ref(false), transferVisible = ref(false), transferError = ref(''), targetUid = ref<number | ''>(''), targetName = ref(''), confirmed = ref(false)
const visible = computed({ get: () => props.modelValue, set: value => { if (!saving.value) emit('update:modelValue',value) } })
let sequence = 0, disposed = false
async function load() { if (!props.leadId || !props.modelValue) return; const token=++sequence; loading.value=true;error.value='';try{const result=await getCooperationLead(props.leadId);if(token===sequence&&!disposed&&visible.value)detail.value=result.data||{}}catch(cause){if(token===sequence&&!disposed)error.value=coopError(cause,'详情读取失败，请核对访问权限')}finally{if(token===sequence&&!disposed)loading.value=false} }
function beginTransfer(){targetUid.value='';targetName.value='';confirmed.value=false;transferError.value='';transferVisible.value=true}
async function transfer(){if(saving.value||!targetUid.value||!confirmed.value)return;saving.value=true;transferError.value='';try{await transferCooperationOwner(Number(detail.value.id),{owner_uid:Number(targetUid.value),expected_owner_uid:Number(detail.value.owner_uid||0)});transferVisible.value=false;ElMessage.success('监管负责人已调整');emit('changed');await load()}catch(cause){transferError.value=coopError(cause,'调整未完成，请刷新详情核对后重试');confirmed.value=false}finally{saving.value=false}}
watch(()=>[props.modelValue,props.leadId],()=>{sequence++;error.value='';detail.value={};noticePanels.value=[];if(props.modelValue)void load();else transferVisible.value=false},{immediate:true})
onBeforeUnmount(()=>{disposed=true;sequence++})
</script>
