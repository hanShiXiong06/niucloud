<template>
    <section>
        <LeadSectionHeader title="分享收益" description="客户直接向门店付款；这里只记录商户自愿分享的金额，实际到账后再确认。"><el-button :icon="Refresh" :loading="loading" @click="load">刷新账目</el-button></LeadSectionHeader>
        <div class="lead-summary"><span>{{ viewerScope.all_data === true ? '全站分享概况' : '本人归属分享概况' }}</span><span>分享待到账<b>{{ money(summary.commission_unpaid) }}</b></span><span>分享已到账<b>{{ money(summary.commission_received) }}</b></span><small class="lead-muted">不随下方筛选变化</small></div>
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="lead-error-banner" />
        <div class="lead-card lead-table-card"><LeadFilterBar :query="query" :filters="filterOptions" :time-fields="timeFields" :total="total" :loading="loading" label="分享收益" search-label="门店或关联单号" placeholder="搜索门店、客资或工单号" @apply="applyFilters" @reload="load" :extra-active-count="Number(ownerFilterActive)" :reset-values="{ owner_uid: '' }"><template #filters><OwnerFilter v-if="viewerScope.all_data === true && viewerScope.can_transfer === true" :model-value="query.owner_uid" @apply="applyFilters" /></template></LeadFilterBar>
            <HsxTable :columns="columns" :data="rows" :loading="loading" :empty-text="error ? '账目未加载，请查看上方提示后重试' : filtered ? '没有符合当前筛选的账目，可清空筛选后查看全部' : '暂无分享记录，商户填写自愿分享金额后才生成；成单不会自动产生费用'"><template #merchant="{ row }"><div class="lead-cell"><strong>{{ row.merchant_name || row.merchant?.name }}</strong><small>{{ row.task_no || (row.workorder_id ? `工单 #${row.workorder_id}` : '关联工单缺失') }}</small><small v-if="row.association_anomaly || row.data_issue" class="lead-error">{{ row.association_anomaly_label || row.data_issue || '关联工单/客资缺失，仅核对' }}</small></div></template><template #owner="{ row }"><span>{{ row.owner_name || '未分配（历史资料）' }}</span></template><template #amount="{ row }"><span class="lead-money">{{ money(row.amount) }}</span></template><template #status="{ row }"><el-tag :type="tone(row.status)">{{ label(row.status) }}</el-tag></template><template #receipt="{ row }"><div class="lead-cell"><strong>{{ row.receipt_no || '尚未登记收款凭证' }}</strong><LeadTimestamp :value="row.received_at" empty="尚未收款" /></div></template><template #actions="{ row }"><div class="lead-actions"><el-button v-if="row.can_transfer === true" link type="primary" @click="openTransfer(row)">转交归属</el-button><el-button v-if="row.can_receive === true" link type="primary" @click="open(row, 'receive')">确认到账</el-button><el-button v-if="row.can_refund === true" link type="danger" @click="open(row, 'refund')">登记实退</el-button><el-button link @click="open(row)">明细</el-button></div></template></HsxTable>
            <div class="lead-pagebar"><HsxPagination layout="sizes, prev, pager, next, jumper" v-model:current-page="query.page" v-model:page-size="query.limit" :total="total" @change="load" /></div>
        </div>
        <HsxDrawer v-model="visible" :title="action === 'receive' ? '登记实际收款' : action === 'refund' ? '登记实际退款' : '分享收益明细'" subtitle="本操作只登记业务结果，不会执行真实资金划转。" size="sm" :confirm-loading="saving" :show-footer="!!action" :confirm-disabled="!form.confirmed" :confirm-text="action === 'refund' ? '确认已实际退款' : '确认已实际到账'" @confirm="submit">
            <el-alert v-if="detail.association_anomaly || detail.data_issue" :title="detail.association_anomaly_label || detail.data_issue || '关联工单/客资缺失，仅核对'" type="warning" show-icon :closable="false" class="lead-banner" />
            <el-descriptions :column="1" border><el-descriptions-item label="门店">{{ detail.merchant_name || detail.merchant?.name }}</el-descriptions-item><el-descriptions-item label="归属员工">{{ detail.owner_name || '未分配（历史资料）' }}<el-button v-if="detail.can_transfer === true" link type="primary" :disabled="saving" style="margin-left: 12px" @click="openTransfer(detail)">转交归属</el-button></el-descriptions-item><el-descriptions-item label="商户分享金额">{{ money(detail.amount) }}</el-descriptions-item><el-descriptions-item label="当前状态">{{ label(detail.status) }}</el-descriptions-item><el-descriptions-item label="关联工单"><el-button v-if="Number(detail.workorder_id) > 0 && !detail.association_anomaly && !detail.data_issue" link type="primary" @click="taskId = Number(detail.workorder_id); taskVisible = true">{{ detail.task_no || `工单 #${detail.workorder_id}` }}</el-button><span v-else>关联资料缺失，仅供核对</span></el-descriptions-item><el-descriptions-item label="收款凭证">{{ detail.receipt_no || '尚未登记' }}</el-descriptions-item><el-descriptions-item label="退款凭证">{{ detail.refund_no || '尚未登记' }}</el-descriptions-item><el-descriptions-item label="备注">{{ detail.remark || '无' }}</el-descriptions-item></el-descriptions>
            <template v-if="action"><el-alert :title="action === 'refund' ? '请先完成线下实际退款，再登记；不会替你退还资金。' : '请核对财务实际到账记录，再登记；这里不登记客户支付给门店的货款。'" type="warning" :closable="false" show-icon style="margin: 22px 0" /><el-form ref="formRef" :model="form" label-position="top" :disabled="saving" class="lead-form"><el-form-item :label="action === 'refund' ? '实际退款金额（元）' : '实际收到分享收益（元）'" prop="amount" :rules="[{ required: true, type: 'number', min: 0.01, message: '请填写实际金额' }]"><el-input-number v-model="form.amount" :min="0.01" :max="99999999" :precision="2" /></el-form-item><el-form-item :label="action === 'refund' ? '退款流水 / 凭证号' : '收款流水 / 凭证号'" prop="receipt_no" :rules="detail.revenue_source === 'voluntary_share' && action === 'receive' ? [] : [{ required: true, whitespace: true, message: '请填写可核对的流水或凭证号' }]"><el-input v-model.trim="form.receipt_no" maxlength="100" :placeholder="detail.revenue_source === 'voluntary_share' && action === 'receive' ? '选填，便于后续核对' : '填写可核对的流水或凭证号'" /></el-form-item><el-form-item label="财务备注"><el-input v-model="form.remark" type="textarea" :rows="3" maxlength="1000" /></el-form-item><el-checkbox v-model="form.confirmed">{{ action === 'refund' ? '我已核实分享收益实际退还' : '我已核实分享收益实际到账' }}</el-checkbox></el-form></template>
        </HsxDrawer>
        <WorkorderDrawer v-model="taskVisible" :id="taskId" :viewer-scope="viewerScope" @changed="load" />
        <OwnershipTransferDrawer v-model="transferVisible" :lead="transferTarget" @changed="ownerTransferred" />
    </section>
</template>
<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, type FormInstance } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import LeadSectionHeader from './LeadSectionHeader.vue'
import LeadTimestamp from './LeadTimestamp.vue'
import LeadFilterBar from './LeadFilterBar.vue'
import { hasListFilters } from '../../composables/filtering'
import WorkorderDrawer from './WorkorderDrawer.vue'
import OwnerFilter from './OwnerFilter.vue'
import OwnershipTransferDrawer from './OwnershipTransferDrawer.vue'
import * as api from '../../api'
import { errorText, label, money, tone, useLeadList, type Row } from '../../composables/useLeadUi'
const props = withDefaults(defineProps<{ summary?: Row; initialFilter?: Row; viewerScope?: Row }>(), { summary: () => ({}), initialFilter: () => ({}), viewerScope: () => ({ all_data: false, can_transfer: false }) })
const emit = defineEmits<{ (e: 'changed'): void }>()
const { rows, total, loading, error, query, load, search } = useLeadList(api.getCommissions, { status: props.initialFilter.status || '', owner_uid: '', time_field: 'create_time', start_time: 0, end_time: 0 })
const statuses = ['unpaid', 'paid', 'refund_pending', 'refunded', 'void']
const filterOptions = computed(() => [{ key: 'status', label: '收款状态', options: statuses.map(value => ({ value, label: label(value) })) }])
const timeFields = [{ value: 'create_time', label: '账目生成时间' }, { value: 'received_at', label: '收款时间' }, { value: 'refunded_at', label: '退款时间' }, { value: 'update_time', label: '更新时间' }]
const ownerFilterActive = computed(() => query.owner_uid !== '' && query.owner_uid != null)
const filtered = computed(() => hasListFilters(query, ['status', 'owner_uid']))
function applyFilters(values: Row) { Object.assign(query, values); return search() }
const columns = [{ prop: 'merchant_name', label: '成交门店 / 工单', slot: 'merchant', minWidth: 220 }, { prop: 'owner_name', label: '归属员工', slot: 'owner', minWidth: 140 }, { prop: 'amount', label: '分享金额', slot: 'amount', width: 145 }, { prop: 'status', label: '收款状态', slot: 'status', width: 120 }, { prop: 'receipt_no', label: '收款记录', slot: 'receipt', minWidth: 210 }, { prop: 'actions', label: '操作', slot: 'actions', width: 180, fixed: 'right' as const }]
const visible = ref(false), saving = ref(false), action = ref<'' | 'receive' | 'refund'>(''), detail = ref<Row>({}), formRef = ref<FormInstance>(), taskVisible = ref(false), taskId = ref(0)
const transferVisible = ref(false), transferTarget = ref<Row>({})
function openTransfer(row: Row) { if (row.can_transfer !== true || !Number(row.lead_id)) return; transferTarget.value = { ...row, id: Number(row.lead_id) }; transferVisible.value = true }
async function ownerTransferred() { visible.value = false; taskVisible.value = false; await load(); emit('changed') }
const form = reactive({ amount: 0, receipt_no: '', remark: '', confirmed: false })
function open(row: Row, value: '' | 'receive' | 'refund' = '') { if (value && (row.association_anomaly || row.data_issue || (value === 'receive' ? row.can_receive !== true : row.can_refund !== true))) { ElMessage.warning('当前账目仅可核对，不能登记收款或退款'); return }; detail.value = row; action.value = value; Object.assign(form, { amount: Number(row.amount || 0), receipt_no: '', remark: '', confirmed: false }); visible.value = true }
async function submit() {
    if (!action.value || saving.value || detail.value.association_anomaly || detail.value.data_issue || (action.value === 'receive' ? detail.value.can_receive !== true : detail.value.can_refund !== true) || !form.confirmed || !await formRef.value?.validate().catch(() => false)) return
    saving.value = true
    try { await api.commissionAction(detail.value.id, action.value, { amount: form.amount, receipt_no: form.receipt_no, remark: form.remark }); visible.value = false; ElMessage.success(action.value === 'refund' ? '实际退款已登记' : '实际收款已登记'); await load(); emit('changed') }
    catch (cause) { ElMessage.error(errorText(cause, '登记未完成，请核对账目状态与金额。')) }
    finally { saving.value = false }
}
onMounted(load)
</script>
