<template>
    <HsxDrawer v-model="visible" title="工单详情与处理" :subtitle="merchantMode ? '处理分配给自己门店的客资，客户直接向门店付款。' : '查看门店反馈与分享收益，不代收客户货款。'" size="lg" :body-loading="loading" :confirm-loading="saving">
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="lead-error-banner" />
        <template v-if="detail.id && !loading && !error">
            <el-alert v-if="detail.association_anomaly || detail.data_issue" :title="detail.association_anomaly_label || detail.data_issue || '关联工单/客资缺失，仅核对'" type="warning" :closable="false" show-icon class="lead-banner" />
            <div class="lead-status-line"><el-tag :type="tone(detail.status)">{{ detail.result_label || label(detail.status) }}</el-tag><strong>{{ detail.merchant?.name || detail.merchant_name }}</strong><span v-if="!simple && detail.completion_status" class="lead-muted">{{ completionLabels[detail.completion_status] }}</span><el-tag v-if="detail.status === 'assigned'" :type="expired ? 'danger' : 'warning'">{{ deadlineLabel(detail.response_deadline, now) }}</el-tag></div>
            <div v-if="simple && detail.outcome" class="lead-outcome-summary"><p class="lead-muted">{{ detail.next_action }}</p><p v-if="detail.has_shared_revenue">自愿分享 <b class="lead-money">{{ money(detail.share_amount) }}</b> · {{ shareStatusLabel }}</p><p v-else class="lead-muted">尚未填写分享金额，不产生待收账目。</p></div>
            <el-descriptions :column="1" border><el-descriptions-item label="客户">{{ lead.customer_name || detail.customer_name || '未填写' }} · {{ lead.mobile || detail.mobile || '未提供电话' }}</el-descriptions-item><el-descriptions-item v-if="!merchantMode" label="归属员工">{{ detail.owner_name || '未分配（历史资料）' }}<el-button v-if="detail.can_transfer === true" link type="primary" :disabled="saving" style="margin-left: 12px" @click="openTransfer">转交归属</el-button></el-descriptions-item><el-descriptions-item label="服务地区">{{ lead.region_text || region(lead) }}</el-descriptions-item><el-descriptions-item label="购机需求">{{ [lead.model || detail.model, lead.memory, lead.color, lead.grade].filter(Boolean).join(' · ') || '未填写' }}</el-descriptions-item><el-descriptions-item label="客户预算">{{ lead.requirements?.length ? '按各项分别记录，见下方完整需求' : budget(lead) }}</el-descriptions-item><el-descriptions-item label="承接门店">{{ detail.merchant?.name || detail.merchant_name || '未返回' }}</el-descriptions-item><el-descriptions-item v-if="detail.status === 'assigned'" label="接单期限">{{ time(detail.response_deadline) }}</el-descriptions-item><el-descriptions-item label="客资备注">{{ lead.remark || '无' }}</el-descriptions-item></el-descriptions>
            <RequirementList v-if="taskRequirements(detail).length" :items="visibleRequirements" :mode="detail.demand_mode || lead.demand_mode" />
            <div v-if="!simple && (detail.deal_no || detail.evidence)" class="lead-form-section"><h3>第 {{ detail.deal_revision || 1 }} 批成交申报 · {{ detail.status === 'deal_pending' ? '待核验' : '最近一批' }}</h3><el-descriptions :column="1" border><el-descriptions-item label="成交单号">{{ detail.deal_no || '未填写' }}</el-descriptions-item><el-descriptions-item label="成交金额">{{ money(detail.deal_amount) }}</el-descriptions-item><el-descriptions-item label="成交凭证"><span style="white-space: pre-wrap; word-break: break-word">{{ detail.evidence || '未提供' }}</span></el-descriptions-item></el-descriptions><el-table v-if="detail.deal_items?.length" :data="detail.deal_items" style="margin-top: 14px"><el-table-column prop="title" label="本批售出机型" /><el-table-column prop="quantity" label="台数" width="80" /><el-table-column label="实际单价" width="120"><template #default="{ row }">{{ money(row.unit_price) }}</template></el-table-column></el-table></div>
            <el-collapse v-if="!simple" class="lead-form-section"><el-collapse-item name="legacy" title="查看历史成交申报记录"><DealHistory :task="detail" /></el-collapse-item></el-collapse>
            <div v-if="!detail.association_anomaly && !detail.data_issue && (!simple || ['assigned', 'following'].includes(detail.status))" class="lead-form-section"><h3>可执行操作</h3><div class="lead-actions" style="margin-top: 14px">
                <el-button v-if="merchantMode && detail.status === 'assigned'" type="primary" :disabled="saving || expired" @click="choose('accept')">确认接单</el-button>
                <el-button v-if="merchantMode && detail.status === 'assigned'" :disabled="saving" @click="choose('reject')">拒绝接单</el-button>
                <el-button v-if="detail.status === 'following'" type="primary" :disabled="saving" @click="choose('follow')">添加跟进</el-button>
                <el-button v-if="!simple && ['following', 'deal_pending'].includes(detail.status)" :disabled="saving" @click="choose('deal')">{{ detail.status === 'deal_pending' ? '修改本批申报' : '申报新一批成交' }}</el-button>
                <el-button v-if="!simple && !merchantMode && detail.status === 'deal_pending'" type="primary" :disabled="saving" @click="choose('verify')">核验成交</el-button>
                <el-button v-if="['assigned', 'following'].includes(detail.status)" :disabled="saving" @click="choose('close')">{{ Number(detail.total_confirmed_amount) > 0 ? '结束剩余需求' : '关闭工单' }}</el-button><el-tooltip v-if="!merchantMode && ['assigned', 'following'].includes(detail.status)" :content="Number(detail.total_confirmed_amount) > 0 ? '已有部分成交，请先结束剩余，再为剩余需求新建客资' : '保留原记录，并重新选择承接门店'"><span><el-button :disabled="saving || Number(detail.total_confirmed_amount) > 0" @click="$emit('reassign', detail.lead || detail)">重新派单</el-button></span></el-tooltip>
                <el-button v-if="!simple && !merchantMode && (detail.status === 'completed' || (Number(detail.total_confirmed_amount) > 0 && ['following', 'deal_pending'].includes(detail.status)))" type="danger" plain :disabled="saving" @click="choose('cancel')">撤销成交</el-button>
                <span v-if="['rejected', 'reassigned', 'closed', 'cancelled'].includes(detail.status)" class="lead-muted">该工单已结束，原记录继续保留。</span>
                <span v-if="!simple && merchantMode && ['deal_pending', 'completed'].includes(detail.status)" class="lead-muted">{{ detail.status === 'deal_pending' ? '成交已提交，等待平台核验。' : '成交已核验，佣金由平台登记实际收款。' }}</span>
            </div></div>
            <div v-if="action" ref="actionSection" class="lead-card lead-action-card" style="margin-top: 18px" tabindex="-1">
                <h3>{{ actionTitles[action] }}</h3>
                <el-alert v-if="action === 'accept'" title="接单前请核对实际可售库存与客户需求。接单不代表 ERP 锁货。" type="info" :closable="false" class="lead-banner" />
                <el-alert v-if="action === 'reject'" title="拒单后客资回到待分配池，平台可重新安排其他门店。" type="warning" :closable="false" class="lead-banner" />
                <el-alert v-if="action === 'cancel'" title="将撤销整个工单的所有已确认成交批次，保留原记录：未收佣金作废、已收佣金转待退款。实际退佣仍需财务登记。" type="warning" :closable="false" class="lead-banner" />
                <el-alert v-if="action === 'verify'" title="一期佣金按固定金额人工填写；核验不会自动收费或发起扣款。" type="info" :closable="false" class="lead-banner" />
                <el-form ref="actionRef" :model="form" label-position="top" :disabled="saving" class="lead-form" @submit.prevent="submit">
                    <el-form-item v-if="action === 'follow'" label="跟进内容" prop="content" :rules="[{ required: true, whitespace: true, message: '请填写本次跟进内容' }]"><el-input v-model="form.content" type="textarea" :rows="4" maxlength="2000" show-word-limit /></el-form-item>
                    <template v-if="action === 'deal'"><DealItemsEditor v-model="saleRows" :task="detail" /><el-form-item label="本批成交单号" prop="deal_no" :rules="[{ required: true, whitespace: true, message: '请填写可核对的成交单号' }]"><el-input v-model.trim="form.deal_no" maxlength="100" /></el-form-item><el-form-item label="成交凭证 / 核对说明" prop="evidence" :rules="[{ required: true, whitespace: true, message: '请填写成交凭证或可核对的凭证编号' }]"><el-input v-model="form.evidence" type="textarea" :rows="3" maxlength="2000" placeholder="可填写收款凭证编号、订单凭证链接及核对说明" /></el-form-item><el-form-item><el-checkbox v-model="form.close_remaining">本批核验通过后结束未满足的剩余需求</el-checkbox></el-form-item><el-form-item v-if="form.close_remaining" label="结束剩余原因" prop="remaining_reason" :rules="[{ required: true, whitespace: true, message: '请说明为何结束剩余需求' }]"><el-input v-model="form.remaining_reason" type="textarea" :rows="2" maxlength="1000" /></el-form-item></template>
                    <template v-if="action === 'verify'"><el-form-item label="核验结果"><el-radio-group v-model="form.approved"><el-radio :value="true">通过核验</el-radio><el-radio :value="false">退回跟进</el-radio></el-radio-group></el-form-item><el-form-item v-if="form.approved" label="固定佣金金额（元）" prop="commission_amount" :rules="[{ required: true, type: 'number', min: 0, message: '请填写大于或等于 0 的固定佣金' }]"><el-input-number v-model="form.commission_amount" :min="0" :max="99999999" :precision="2" /></el-form-item></template>
                    <el-form-item v-if="['reject', 'close', 'cancel'].includes(action)" label="处理原因" prop="reason" :rules="[{ required: true, whitespace: true, message: '请填写处理原因' }]"><el-input v-model="form.reason" type="textarea" :rows="3" maxlength="1000" /></el-form-item>
                    <el-form-item v-if="['deal', 'verify'].includes(action)" label="补充说明" prop="remark" :rules="action === 'verify' && !form.approved ? [{ required: true, whitespace: true, message: '退回时请填写原因' }] : []"><el-input v-model="form.remark" type="textarea" :rows="2" maxlength="1000" /></el-form-item>
                </el-form>
            </div>
            <el-collapse class="lead-form-section"><el-collapse-item name="notice" title="通知状态与发送记录"><div v-if="!merchantMode"><el-alert v-if="noticeError" :title="noticeError" type="warning" :closable="false" /><div class="lead-status-line"><el-tag type="info">{{ notice.label || '读取中' }}</el-tag><el-button link :loading="noticeLoading" @click="loadNotice">刷新通知状态</el-button><el-button v-if="notice.retry_allowed" link type="primary" :loading="noticeLoading" @click="retryNotice">重试可重试渠道</el-button></div><p class="lead-muted">{{ notice.message || '站内工单不依赖微信投递，框架记录不等于终端送达。' }}</p><el-table v-if="noticeChannels.length" :data="noticeChannels" size="small" style="margin-top: 14px"><el-table-column prop="name" label="通知渠道" width="100" /><el-table-column label="本次状态" min-width="150"><template #default="{ row }"><el-tag size="small" :type="noticeTone(row.status)">{{ row.label || '状态待核实' }}</el-tag><span v-if="row.retry_allowed" class="lead-muted"> · 可重试</span></template></el-table-column><el-table-column label="原因 / 下一步" min-width="220"><template #default="{ row }">{{ row.message || (row.selected === false ? '本次未选择此渠道' : '请刷新状态并核对框架投递记录') }}</template></el-table-column></el-table><p v-if="notice.retry_allowed" class="lead-muted">仅处理服务端判定可安全重试的失败或配置阻塞渠道；已提交、结果不明的渠道不会重复发送。</p></div></el-collapse-item><el-collapse-item name="history" title="工单记录与补充资料"><p class="lead-muted">工单号：{{ detail.task_no }} · 派单时间：{{ time(detail.create_time) }}</p>
            <div class="lead-follow"><el-empty v-if="!detail.followups?.length" description="暂无跟进记录" :image-size="65" /><el-timeline v-else><el-timeline-item v-for="item in detail.followups" :key="item.id" :timestamp="`${time(item.create_time || item.create_at)} · ${item.operator_name || '经办人'}`">{{ item.content }}</el-timeline-item></el-timeline></div></el-collapse-item></el-collapse>
        </template>
        <template #footer>
            <div class="lead-workorder-footer">
                <span class="lead-muted" role="status">{{ action ? `当前操作：${actionTitles[action]}` : '查看详情不会改变工单状态' }}</span>
                <div class="lead-actions">
                    <template v-if="action"><el-button :disabled="saving" @click="action = ''">取消操作</el-button><el-button :type="['reject', 'close', 'cancel'].includes(action) ? 'danger' : 'primary'" :loading="saving" :disabled="loading || !!error" @click="submit">{{ action === 'verify' ? '确认核验结果' : actionTitles[action] }}</el-button></template>
                    <template v-else><el-button :disabled="saving" @click="visible = false">关闭</el-button><el-button :disabled="saving" :loading="loading" @click="load">刷新详情</el-button></template>
                </div>
            </div>
        </template>
    </HsxDrawer>
    <OwnershipTransferDrawer v-model="transferVisible" :lead="transferTarget" @changed="ownerTransferred" />
</template>
<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { ElMessage, type FormInstance } from 'element-plus'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import RequirementList from './RequirementList.vue'
import DealItemsEditor from './DealItemsEditor.vue'
import DealHistory from './DealHistory.vue'
import OwnershipTransferDrawer from './OwnershipTransferDrawer.vue'
import { buildDealRows, completionLabels, deadlineLabel, dealItems, isExpired, taskRequirements, validateDeal } from '../../composables/deal'
import * as api from '../../api'
import { budget, errorText, label, money, region, time, tone, type Row } from '../../composables/useLeadUi'
const props = withDefaults(defineProps<{ modelValue: boolean, id: number, merchantMode?: boolean, viewerScope?: Row }>(), { merchantMode: false, viewerScope: () => ({ all_data: false, can_transfer: false }) })
const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void, (e: 'changed'): void, (e: 'reassign', lead: Row): void }>()
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const detail = ref<Row>({}), loading = ref(false), error = ref(''), saving = ref(false), action = ref(''), actionRef = ref<FormInstance>()
const transferVisible = ref(false), transferTarget = ref<Row>({})
function openTransfer() { if (detail.value.can_transfer !== true || props.merchantMode || !Number(detail.value.lead_id || detail.value.lead?.id)) return; transferTarget.value = { ...detail.value, id: Number(detail.value.lead_id || lead.value.id) }; transferVisible.value = true }
async function ownerTransferred() { action.value = ''; await load(); emit('changed') }
const actionSection = ref<HTMLElement>()
const lead = computed(() => detail.value.lead || detail.value)
const simple = computed(() => detail.value.collaboration_mode === 'simple')
const visibleRequirements = computed(() => taskRequirements(detail.value).map(row => { if (!simple.value) return row; const { confirmed_quantity, remaining_quantity, confirmed_amount, ...requested } = row; return requested }))
const shareStatusLabel = computed(() => ({ unpaid: '待核对实际到账', paid: '已确认到账', void: '已作废', refund_pending: '待退回', refunded: '已退回' }[detail.value.share_status as string] || '未填写'))
const saleRows = ref<Row[]>([]), now = ref(Date.now()), expired = computed(() => isExpired(detail.value, now.value))
const clock = setInterval(() => { now.value = Date.now() }, 30000)
const actionTitles: Record<string, string> = { accept: '确认接单', reject: '确认拒单', follow: '保存跟进', deal: '提交成交核验', verify: '核验成交', close: '结束工单', cancel: '撤销成交' }
const emptyForm = () => ({ content: '', deal_no: '', close_remaining: false, remaining_reason: '', evidence: '', remark: '', approved: true, commission_amount: undefined as number | undefined, reason: '' })
const form = reactive(emptyForm())
const notice = ref<Row>({}), noticeError = ref(''), noticeLoading = ref(false)
const noticeChannels = computed<Row[]>(() => Array.isArray(notice.value.channels) ? notice.value.channels : [])
const noticeTone = (status: string) => status === 'failed' ? 'danger' : ['blocked', 'unconfigured', 'disabled'].includes(status) ? 'warning' : 'info'
let noticeSequence = 0
async function loadNotice() {
    if (props.merchantMode || !props.id) return
    const token = ++noticeSequence, id = props.id
    const current = () => token === noticeSequence && props.id === id && visible.value
    noticeLoading.value = true; noticeError.value = ''
    try { const result = await api.getTaskNotice(id); if (current()) notice.value = result.data || {} }
    catch (cause) { if (current()) noticeError.value = errorText(cause, '通知状态读取失败，工单仍可正常处理。') }
    finally { if (current()) noticeLoading.value = false }
}
async function retryNotice() {
    if (noticeLoading.value || !notice.value.retry_allowed || props.merchantMode || !props.id) return
    const token = ++noticeSequence, id = props.id
    const current = () => token === noticeSequence && props.id === id && visible.value
    noticeLoading.value = true; noticeError.value = ''
    try { const result = await api.retryTaskNotice(id); if (current()) { notice.value = result.data || {}; ElMessage.info(result.data?.message || '已请求安全重试，请核对各渠道的实际状态') } }
    catch (cause) { if (current()) noticeError.value = errorText(cause, '通知未重试，请先核对框架记录，避免重复投递。') }
    finally { if (current()) noticeLoading.value = false }
}
let sequence = 0
async function load() {
    if (!props.id || !visible.value) return
    const token = ++sequence; loading.value = true; error.value = ''; action.value = ''
    try { const result = await api.getWorkorder(props.id); if (token === sequence && visible.value) { detail.value = result.data; void loadNotice() } }
    catch (cause) { if (token === sequence) error.value = errorText(cause, '工单加载失败，可能没有权限或已不存在。') }
    finally { if (token === sequence) loading.value = false }
}
async function choose(value: string) { if (saving.value || detail.value.association_anomaly || detail.value.data_issue || (value === 'accept' && expired.value)) return; Object.assign(form, emptyForm()); saleRows.value = buildDealRows(detail.value); if (value === 'deal' && detail.value.status === 'deal_pending') Object.assign(form, { deal_no: detail.value.deal_no || '', evidence: detail.value.evidence || '', close_remaining: !!detail.value.close_remaining, remaining_reason: detail.value.remaining_reason || '' }); action.value = value; await nextTick(); actionSection.value?.scrollIntoView({ block: 'start' }); actionSection.value?.focus({ preventScroll: true }) }
async function submit() {
    if (!action.value || saving.value || detail.value.association_anomaly || detail.value.data_issue) return
    if (actionRef.value && !await actionRef.value.validate().catch(() => false)) return
    if (action.value === 'deal') { const issue = validateDeal(detail.value, saleRows.value, form.close_remaining, form.remaining_reason); if (issue) { ElMessage.warning(issue); return } }
    const currentAction = action.value; saving.value = true
    try { await api.workorderAction(props.id, currentAction, { ...form, ...(currentAction === 'verify' ? { deal_revision: Number(detail.value.deal_revision || 0) } : {}), ...(currentAction === 'deal' ? { deal_items: dealItems(saleRows.value), expected_revision: Number(detail.value.last_verified_revision || 0), expected_deal_revision: Number(detail.value.deal_revision || 0) } : {}) }); ElMessage.success(currentAction === 'verify' ? (form.approved ? '本批已核验，成交进度及本批佣金台账已更新；剩余需求请继续跟进' : '已退回商户继续跟进') : currentAction === 'reject' ? '已拒单，客资已回到待分配池' : currentAction === 'cancel' ? '成交已撤销，请在佣金中核对退款状态' : '处理已保存'); action.value = ''; await load(); emit('changed') }
    catch (cause) { ElMessage.error(errorText(cause, '处理未完成，工单可能已被其他人更新，请刷新核对。')) }
    finally { saving.value = false }
}
watch(() => [props.modelValue, props.id], () => { if (visible.value) { detail.value = {}; notice.value = {}; noticeError.value = ''; noticeSequence++; noticeLoading.value = false; void load() } else { sequence++; noticeSequence++; loading.value = false; noticeLoading.value = false; action.value = '' } }, { immediate: true })
onBeforeUnmount(() => { sequence++; noticeSequence++; clearInterval(clock) })
</script>
