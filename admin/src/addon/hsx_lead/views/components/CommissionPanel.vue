<template>
    <section>
        <div class="lead-page-title"><div><h2>佣金收款</h2><p>固定金额由平台核验时手填，实际收款与退款单独登记；不自动收费。</p></div><el-button :icon="Refresh" :loading="loading" @click="load">刷新账目</el-button></div>
        <div class="lead-summary"><span>待收佣金<b>{{ money(summary.commission_unpaid) }}</b></span><span>已登记收佣<b>{{ money(summary.commission_received) }}</b></span></div>
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon class="lead-error-banner" />
        <div class="lead-card lead-table-card"><HsxSearchPanel :collapsible="false" :show-layout-switch="false"><el-form inline class="lead-search" @submit.prevent="search"><el-form-item label="门店 / 关联单号"><el-input v-model="query.keyword" clearable placeholder="搜索门店、客资或工单号" @keyup.enter="search" @clear="search" /></el-form-item><el-form-item label="收款状态"><el-select v-model="query.status" clearable placeholder="全部状态"><el-option v-for="status in statuses" :key="status" :label="label(status)" :value="status" /></el-select></el-form-item><el-form-item><el-button :icon="Search" @click="search">查询</el-button></el-form-item></el-form></HsxSearchPanel>
            <HsxTable :columns="columns" :data="rows" :loading="loading" empty-text="暂无佣金，成交核验通过后才生成应收账目"><template #merchant="{ row }"><div class="lead-cell"><strong>{{ row.merchant_name || row.merchant?.name }}</strong><small>{{ row.task_no || `工单 #${row.workorder_id}` }}</small></div></template><template #amount="{ row }"><span class="lead-money">{{ money(row.amount) }}</span></template><template #status="{ row }"><el-tag :type="tone(row.status)">{{ label(row.status) }}</el-tag></template><template #receipt="{ row }"><div class="lead-cell"><strong>{{ row.receipt_no || '尚未登记收款凭证' }}</strong><small>{{ time(row.received_at) }}</small></div></template><template #actions="{ row }"><div class="lead-actions"><el-button v-if="row.status === 'unpaid'" link type="primary" @click="open(row, 'receive')">登记收款</el-button><el-button v-if="row.status === 'refund_pending'" link type="danger" @click="open(row, 'refund')">登记实退</el-button><el-button link @click="open(row)">明细</el-button></div></template></HsxTable>
            <div class="lead-pagebar"><HsxPagination v-model:current-page="query.page" v-model:page-size="query.limit" :total="total" @change="load" /></div>
        </div>
        <HsxDrawer v-model="visible" :title="action === 'receive' ? '登记实际收款' : action === 'refund' ? '登记实际退款' : '佣金明细'" subtitle="本操作只登记业务结果，不会执行真实资金划转。" size="sm" :confirm-loading="saving" :show-footer="!!action" :confirm-disabled="!form.confirmed" :confirm-text="action === 'refund' ? '确认已实际退款' : '确认已实际到账'" @confirm="submit">
            <el-descriptions :column="1" border><el-descriptions-item label="门店">{{ detail.merchant_name || detail.merchant?.name }}</el-descriptions-item><el-descriptions-item label="应收固定佣金">{{ money(detail.amount) }}</el-descriptions-item><el-descriptions-item label="当前状态">{{ label(detail.status) }}</el-descriptions-item><el-descriptions-item label="关联工单"><el-button link type="primary" @click="taskId = Number(detail.workorder_id); taskVisible = true">{{ detail.task_no || `工单 #${detail.workorder_id}` }}</el-button></el-descriptions-item><el-descriptions-item label="收款凭证">{{ detail.receipt_no || '尚未登记' }}</el-descriptions-item><el-descriptions-item label="退款凭证">{{ detail.refund_no || '尚未登记' }}</el-descriptions-item><el-descriptions-item label="备注">{{ detail.remark || '无' }}</el-descriptions-item></el-descriptions>
            <template v-if="action"><el-alert :title="action === 'refund' ? '请先完成线下实际退款，再登记；不会替你退还资金。' : '请核对财务实际到账记录，再登记；不能将客户成交款当作佣金收款。'" type="warning" :closable="false" show-icon style="margin: 22px 0" /><el-form ref="formRef" :model="form" label-position="top" :disabled="saving" class="lead-form"><el-form-item :label="action === 'refund' ? '实际退款金额（元）' : '实际收到佣金（元）'" prop="amount" :rules="[{ required: true, type: 'number', min: 0.01, message: '请填写实际金额' }]"><el-input-number v-model="form.amount" :min="0.01" :max="99999999" :precision="2" /></el-form-item><el-form-item :label="action === 'refund' ? '退款流水 / 凭证号' : '收款流水 / 凭证号'" prop="receipt_no" :rules="[{ required: true, whitespace: true, message: '请填写可核对的流水或凭证号' }]"><el-input v-model.trim="form.receipt_no" maxlength="100" /></el-form-item><el-form-item label="财务备注"><el-input v-model="form.remark" type="textarea" :rows="3" maxlength="1000" /></el-form-item><el-checkbox v-model="form.confirmed">{{ action === 'refund' ? '我已核实佣金实际退还' : '我已核实佣金实际到账' }}</el-checkbox></el-form></template>
        </HsxDrawer>
        <WorkorderDrawer v-model="taskVisible" :id="taskId" @changed="load" />
    </section>
</template>
<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, type FormInstance } from 'element-plus'
import { Refresh, Search } from '@element-plus/icons-vue'
import HsxDrawer from '@/addon/hsx_components/components/HsxDrawer/index.vue'
import HsxTable from '@/addon/hsx_components/components/HsxTable/index.vue'
import HsxSearchPanel from '@/addon/hsx_components/components/HsxSearchPanel/index.vue'
import HsxPagination from '@/addon/hsx_components/components/HsxPagination/index.vue'
import WorkorderDrawer from './WorkorderDrawer.vue'
import * as api from '../../api'
import { errorText, label, money, time, tone, useLeadList, type Row } from '../../composables/useLeadUi'
withDefaults(defineProps<{ summary?: Row }>(), { summary: () => ({}) })
const emit = defineEmits<{ (e: 'changed'): void }>()
const { rows, total, loading, error, query, load, search } = useLeadList(api.getCommissions, { status: '' })
const statuses = ['unpaid', 'paid', 'refund_pending', 'refunded', 'void']
const columns = [{ prop: 'merchant_name', label: '成交门店 / 工单', slot: 'merchant', minWidth: 220 }, { prop: 'deal_revision', label: '成交批次', width: 105, formatter: (row: Row) => `第 ${row.deal_revision || 1} 批` }, { prop: 'amount', label: '本批固定佣金', slot: 'amount', width: 145 }, { prop: 'status', label: '收款状态', slot: 'status', width: 120 }, { prop: 'receipt_no', label: '收款记录', slot: 'receipt', minWidth: 210 }, { prop: 'actions', label: '操作', slot: 'actions', width: 180, fixed: 'right' as const }]
const visible = ref(false), saving = ref(false), action = ref<'' | 'receive' | 'refund'>(''), detail = ref<Row>({}), formRef = ref<FormInstance>(), taskVisible = ref(false), taskId = ref(0)
const form = reactive({ amount: 0, receipt_no: '', remark: '', confirmed: false })
function open(row: Row, value: '' | 'receive' | 'refund' = '') { detail.value = row; action.value = value; Object.assign(form, { amount: Number(row.amount || 0), receipt_no: '', remark: '', confirmed: false }); visible.value = true }
async function submit() {
    if (!action.value || !form.confirmed || !await formRef.value?.validate().catch(() => false)) return
    saving.value = true
    try { await api.commissionAction(detail.value.id, action.value, { amount: form.amount, receipt_no: form.receipt_no, remark: form.remark }); visible.value = false; ElMessage.success(action.value === 'refund' ? '实际退款已登记' : '实际收款已登记'); await load(); emit('changed') }
    catch (cause) { ElMessage.error(errorText(cause, '登记未完成，请核对账目状态与金额。')) }
    finally { saving.value = false }
}
onMounted(load)
</script>
