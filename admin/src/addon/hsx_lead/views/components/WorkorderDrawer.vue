<template>
    <HsxDrawer v-model="visible" title="工单详情与处理" :subtitle="merchantMode ? '仅处理当前账号绑定门店的工单。' : '保留派单、跟进、成交核验与佣金记录。'" size="lg" :body-loading="loading" :confirm-loading="saving">
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="lead-error-banner" />
        <template v-if="detail.id && !loading && !error">
            <div class="lead-status-line"><el-tag :type="tone(detail.status)">{{ label(detail.status) }}</el-tag><strong>{{ detail.task_no || `工单 #${detail.id}` }}</strong><span v-if="detail.completion_status" class="lead-muted">{{ completionLabels[detail.completion_status] }}</span><el-tag v-if="detail.status === 'assigned'" :type="expired ? 'danger' : 'warning'">{{ deadlineLabel(detail.response_deadline, now) }}</el-tag></div>
            <el-descriptions :column="1" border><el-descriptions-item label="客户">{{ lead.customer_name || detail.customer_name || '未填写' }} · {{ lead.mobile || detail.mobile || '未提供电话' }}</el-descriptions-item><el-descriptions-item label="服务地区">{{ lead.region_text || region(lead) }}</el-descriptions-item><el-descriptions-item label="购机需求">{{ [lead.model || detail.model, lead.memory, lead.color, lead.grade].filter(Boolean).join(' · ') || '未填写' }}</el-descriptions-item><el-descriptions-item label="客户预算">{{ lead.requirements?.length ? '按各项分别记录，见下方完整需求' : budget(lead) }}</el-descriptions-item><el-descriptions-item label="承接门店">{{ detail.merchant?.name || detail.merchant_name || '未返回' }}</el-descriptions-item><el-descriptions-item label="接单期限">{{ time(detail.response_deadline) }}</el-descriptions-item><el-descriptions-item label="客资备注">{{ lead.remark || '无' }}</el-descriptions-item></el-descriptions>
            <RequirementList v-if="taskRequirements(detail).length" :items="taskRequirements(detail)" :mode="detail.demand_mode || lead.demand_mode" />
            <div v-if="detail.deal_no || detail.evidence" class="lead-form-section"><h3>第 {{ detail.deal_revision || 1 }} 批成交申报 · {{ detail.status === 'deal_pending' ? '待核验' : '最近一批' }}</h3><el-descriptions :column="1" border><el-descriptions-item label="成交单号">{{ detail.deal_no || '未填写' }}</el-descriptions-item><el-descriptions-item label="成交金额">{{ money(detail.deal_amount) }}</el-descriptions-item><el-descriptions-item label="成交凭证"><span style="white-space: pre-wrap; word-break: break-word">{{ detail.evidence || '未提供' }}</span></el-descriptions-item></el-descriptions><el-table v-if="detail.deal_items?.length" :data="detail.deal_items" style="margin-top: 14px"><el-table-column prop="title" label="本批售出机型" /><el-table-column prop="quantity" label="台数" width="80" /><el-table-column label="实际单价" width="120"><template #default="{ row }">{{ money(row.unit_price) }}</template></el-table-column></el-table></div>
            <DealHistory :task="detail" />
            <div class="lead-form-section"><h3>可执行操作</h3><div class="lead-actions" style="margin-top: 14px">
                <el-button v-if="merchantMode && detail.status === 'assigned'" type="primary" :disabled="saving || expired" @click="choose('accept')">确认接单</el-button>
                <el-button v-if="merchantMode && detail.status === 'assigned'" :disabled="saving" @click="choose('reject')">拒绝接单</el-button>
                <el-button v-if="detail.status === 'following'" type="primary" :disabled="saving" @click="choose('follow')">添加跟进</el-button>
                <el-button v-if="['following', 'deal_pending'].includes(detail.status)" :disabled="saving" @click="choose('deal')">{{ detail.status === 'deal_pending' ? '修改本批申报' : '申报新一批成交' }}</el-button>
                <el-button v-if="!merchantMode && detail.status === 'deal_pending'" type="primary" :disabled="saving" @click="choose('verify')">核验成交</el-button>
                <el-button v-if="['assigned', 'following'].includes(detail.status)" :disabled="saving" @click="choose('close')">{{ Number(detail.total_confirmed_amount) > 0 ? '结束剩余需求' : '关闭工单' }}</el-button><el-tooltip v-if="!merchantMode && ['assigned', 'following'].includes(detail.status)" :content="Number(detail.total_confirmed_amount) > 0 ? '已有部分成交，请先结束剩余，再为剩余需求新建客资' : '保留原记录，并重新选择承接门店'"><span><el-button :disabled="saving || Number(detail.total_confirmed_amount) > 0" @click="$emit('reassign', detail.lead || detail)">重新派单</el-button></span></el-tooltip>
                <el-button v-if="!merchantMode && (detail.status === 'completed' || (Number(detail.total_confirmed_amount) > 0 && ['following', 'deal_pending'].includes(detail.status)))" type="danger" plain :disabled="saving" @click="choose('cancel')">撤销成交</el-button>
                <span v-if="['rejected', 'reassigned', 'closed', 'cancelled'].includes(detail.status)" class="lead-muted">该工单已结束，原记录继续保留。</span>
                <span v-if="merchantMode && ['deal_pending', 'completed'].includes(detail.status)" class="lead-muted">{{ detail.status === 'deal_pending' ? '成交已提交，等待平台核验。' : '成交已核验，佣金由平台登记实际收款。' }}</span>
            </div></div>
            <div v-if="action" class="lead-card" style="margin-top: 18px">
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
                <div class="lead-actions"><el-button :disabled="saving" @click="action = ''">取消操作</el-button><el-button type="primary" :loading="saving" @click="submit">{{ action === 'verify' ? '确认核验结果' : actionTitles[action] }}</el-button></div>
            </div>
            <div v-if="!merchantMode" class="lead-form-section"><h3>通知与站内待办</h3><el-alert v-if="noticeError" :title="noticeError" type="warning" :closable="false" /><div class="lead-status-line"><el-tag type="info">{{ notice.label || '读取中' }}</el-tag><el-button link :loading="noticeLoading" @click="loadNotice">刷新通知状态</el-button><el-button v-if="notice.retry_allowed" link type="primary" :loading="noticeLoading" @click="retryNotice">重试本次通知</el-button></div><p class="lead-muted">{{ notice.message || '站内工单不依赖微信投递，框架记录不等于终端送达。' }}</p></div>
            <div class="lead-form-section lead-follow"><h3>跟进记录</h3><el-empty v-if="!detail.followups?.length" description="暂无跟进记录" :image-size="65" /><el-timeline v-else><el-timeline-item v-for="item in detail.followups" :key="item.id" :timestamp="`${time(item.create_time || item.create_at)} · ${item.operator_name || '经办人'}`">{{ item.content }}</el-timeline-item></el-timeline></div>
        </template>
        <template #footer><el-button :disabled="saving" @click="visible = false">关闭</el-button><el-button :disabled="saving" :loading="loading" @click="load">刷新详情</el-button></template>
    </HsxDrawer>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { ElMessage, type FormInstance } from 'element-plus'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import RequirementList from './RequirementList.vue'
import DealItemsEditor from './DealItemsEditor.vue'
import DealHistory from './DealHistory.vue'
import { buildDealRows, completionLabels, deadlineLabel, dealItems, isExpired, taskRequirements, validateDeal } from '../../composables/deal'
import * as api from '../../api'
import { budget, errorText, label, money, region, time, tone, type Row } from '../../composables/useLeadUi'
const props = withDefaults(defineProps<{ modelValue: boolean, id: number, merchantMode?: boolean }>(), { merchantMode: false })
const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void, (e: 'changed'): void, (e: 'reassign', lead: Row): void }>()
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const detail = ref<Row>({}), loading = ref(false), error = ref(''), saving = ref(false), action = ref(''), actionRef = ref<FormInstance>()
const lead = computed(() => detail.value.lead || detail.value)
const saleRows = ref<Row[]>([]), now = ref(Date.now()), expired = computed(() => isExpired(detail.value, now.value))
const clock = setInterval(() => { now.value = Date.now() }, 30000)
const actionTitles: Record<string, string> = { accept: '确认接单', reject: '确认拒单', follow: '保存跟进', deal: '提交成交核验', verify: '核验成交', close: '关闭工单', cancel: '撤销成交' }
const emptyForm = () => ({ content: '', deal_no: '', close_remaining: false, remaining_reason: '', evidence: '', remark: '', approved: true, commission_amount: undefined as number | undefined, reason: '' })
const form = reactive(emptyForm())
const notice = ref<Row>({}), noticeError = ref(''), noticeLoading = ref(false)
async function loadNotice() { if (props.merchantMode || !props.id) return; noticeLoading.value = true; noticeError.value = ''; try { const result = await api.getTaskNotice(props.id); notice.value = result.data } catch (cause) { noticeError.value = errorText(cause, '通知状态读取失败，工单仍可正常处理。') } finally { noticeLoading.value = false } }
async function retryNotice() { noticeLoading.value = true; try { const result = await api.retryTaskNotice(props.id); notice.value = result.data; ElMessage.info(result.data.message || '已请求重试，请核对实际状态') } catch (cause) { noticeError.value = errorText(cause, '通知未重试，请先核对框架记录，避免重复投递。') } finally { noticeLoading.value = false } }
let sequence = 0
async function load() {
    if (!props.id || !visible.value) return
    const token = ++sequence; loading.value = true; error.value = ''; action.value = ''
    try { const result = await api.getWorkorder(props.id); if (token === sequence && visible.value) { detail.value = result.data; void loadNotice() } }
    catch (cause) { if (token === sequence) error.value = errorText(cause, '工单加载失败，可能没有权限或已不存在。') }
    finally { if (token === sequence) loading.value = false }
}
function choose(value: string) { if (value === 'accept' && expired.value) return; Object.assign(form, emptyForm()); saleRows.value = buildDealRows(detail.value); if (value === 'deal' && detail.value.status === 'deal_pending') Object.assign(form, { deal_no: detail.value.deal_no || '', evidence: detail.value.evidence || '', close_remaining: !!detail.value.close_remaining, remaining_reason: detail.value.remaining_reason || '' }); action.value = value }
async function submit() {
    if (!action.value || saving.value) return
    if (actionRef.value && !await actionRef.value.validate().catch(() => false)) return
    if (action.value === 'deal') { const issue = validateDeal(detail.value, saleRows.value, form.close_remaining, form.remaining_reason); if (issue) { ElMessage.warning(issue); return } }
    const currentAction = action.value; saving.value = true
    try { await api.workorderAction(props.id, currentAction, { ...form, ...(currentAction === 'verify' ? { deal_revision: Number(detail.value.deal_revision || 0) } : {}), ...(currentAction === 'deal' ? { deal_items: dealItems(saleRows.value), expected_revision: Number(detail.value.last_verified_revision || 0), expected_deal_revision: Number(detail.value.deal_revision || 0) } : {}) }); ElMessage.success(currentAction === 'verify' ? (form.approved ? '本批已核验，成交进度及本批佣金台账已更新；剩余需求请继续跟进' : '已退回商户继续跟进') : currentAction === 'reject' ? '已拒单，客资已回到待分配池' : currentAction === 'cancel' ? '成交已撤销，请在佣金中核对退款状态' : '处理已保存'); action.value = ''; await load(); emit('changed') }
    catch (cause) { ElMessage.error(errorText(cause, '处理未完成，工单可能已被其他人更新，请刷新核对。')) }
    finally { saving.value = false }
}
watch(() => [props.modelValue, props.id], () => { if (visible.value) { detail.value = {}; void load() } else { sequence++; loading.value = false; action.value = '' } }, { immediate: true })
onBeforeUnmount(() => { sequence++; clearInterval(clock) })
</script>
