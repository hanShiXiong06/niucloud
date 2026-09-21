<template>
    <HsxDialog v-model="visible" :title="action === 'ship' ? '批量确认退回发货' : '批量确认客户签收'"
        width="min(1080px, calc(100vw - 32px))" :confirm-loading="running" :before-close="beforeClose"
        :show-close="!running" :close-on-click-modal="false" :close-on-press-escape="false">
        <div class="batch-summary">
            <strong>本次 {{ eligible.length }} 单 · {{ deviceCount }} 台</strong>
            <span v-if="skipped">另有 {{ skipped }} 单状态不符，已列明且不会处理</span>
        </div>
        <el-alert :closable="false" type="info" show-icon :title="action === 'ship'
            ? '仅登记已实际发出的设备。快递单号逐单填写，不会合并客户包裹或自动购买运单；系统快递请使用单笔发货工作台。'
            : '签收方是客户，不是商家仓库。只处理已核实收货的退回单；尚未收到的请勿勾选。'" />
        <div v-if="action === 'ship'" class="batch-tools">
            <span>统一退回方式</span>
            <el-select v-model="commonMode" :disabled="running" style="width: 150px">
                <el-option label="手动快递" value="manual" /><el-option label="物流车" value="logistics_car" /><el-option label="客户自取" value="self_pickup" />
            </el-select>
            <el-button :disabled="running" @click="applyMode">应用到未成功项</el-button>
        </div>
        <el-table :data="rows" border size="small" max-height="410" class="batch-table">
            <el-table-column label="退回单 / 客户" min-width="210">
                <template #default="{ row }"><strong>{{ row.order_no }}</strong><div class="muted">{{ row.member_name || row.member?.nickname || '未填写姓名' }} · {{ returnDevices(row).length }} 台</div></template>
            </el-table-column>
            <el-table-column v-if="action === 'ship'" label="退回方式" width="140">
                <template #default="{ row }"><el-select v-model="row.mode" :disabled="locked(row)"><el-option label="手动快递" value="manual" /><el-option label="物流车" value="logistics_car" /><el-option label="客户自取" value="self_pickup" /></el-select></template>
            </el-table-column>
            <el-table-column v-if="action === 'ship'" label="快递公司 / 单号" min-width="220">
                <template #default="{ row }"><div v-if="row.mode === 'manual'" class="waybill-fields"><el-input v-model="row.express_company" :disabled="locked(row)" maxlength="100" placeholder="快递公司" /><el-input v-model="row.express_no" :disabled="locked(row)" maxlength="100" placeholder="本单快递单号" /></div><span v-else class="muted">无需快递单号，请核实实际交接</span></template>
            </el-table-column>
            <el-table-column v-else label="退回物流" min-width="180"><template #default="{ row }">{{ row.express_company || '未记录' }}<div class="muted">{{ row.express_no || '无快递单号' }}</div></template></el-table-column>
            <el-table-column label="本次处理结果" min-width="220"><template #default="{ row }"><span :class="['result', row.result]">{{ row.message || '待提交' }}</span></template></el-table-column>
        </el-table>
        <el-input v-model="comment" :disabled="running" type="textarea" :rows="2" maxlength="500" show-word-limit placeholder="本次处理备注（选填，将逐单保留）" />
        <div class="muted batch-rules">发出后仍属于未完成；人工确认客户签收，或发出满 72 小时自动完成后才扣减台数。操作不退款、不打款、不删除历史。</div>
        <template #footer>
            <div class="batch-footer">
                <div class="batch-footer-state">
                    <el-checkbox v-model="acknowledged" :disabled="running" class="batch-ack">{{ action === 'ship' ? '我已核实本次设备已实际发出或交还客户' : '我已核实本次退回设备客户均已收到' }}</el-checkbox>
                    <span>{{ progress }}</span>
                </div>
                <div class="batch-footer-buttons"><el-button :disabled="running" @click="visible = false">{{ attempted ? '关闭结果' : '取消' }}</el-button><el-button type="primary" :loading="running" :disabled="!acknowledged || !remaining.length" @click="submit">{{ attempted ? '重试未成功项' : action === 'ship' ? '确认发货' : '确认客户已签收' }}</el-button></div>
            </div>
        </template>
    </HsxDialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { HsxDialog } from '@/addon/hsx_components/core'
import { confirmReturnOrder, updateReturnOrderStatus } from '../../../api/recycle_return_order'
import { prepareReturnRows, returnDevices, returnResponseError, returnShipmentPayload, type BatchRow, type ReturnAction } from '../return-workflow'
const emit = defineEmits(['changed'])
const visible = ref(false), running = ref(false), acknowledged = ref(false), attempted = ref(false)
const action = ref<ReturnAction>('ship'), rows = ref<BatchRow[]>([]), comment = ref(''), commonMode = ref('manual')
const eligible = computed(() => rows.value.filter(row => row.result !== 'skipped'))
const skipped = computed(() => rows.value.length - eligible.value.length)
const remaining = computed(() => eligible.value.filter(row => row.result !== 'success'))
const deviceCount = computed(() => eligible.value.reduce((sum, row) => sum + returnDevices(row).length, 0))
const progress = computed(() => attempted.value ? `成功 ${rows.value.filter(row => row.result === 'success').length} 单 · 失败 ${rows.value.filter(row => row.result === 'failed').length} 单${running.value ? ' · 正在逐单处理，请勿关闭页面' : ''}` : '每单独立处理，失败项可修改后重试')
const locked = (row: BatchRow) => running.value || ['success', 'skipped'].includes(row.result)
function open(selected: Record<string, any>[], type: ReturnAction) {
    if (running.value) return
    rows.value = prepareReturnRows(selected, type); action.value = type
    acknowledged.value = false; attempted.value = false; comment.value = ''; commonMode.value = 'manual'
    visible.value = true
}
function beforeClose(done: () => void) { if (!running.value) done() }
function applyMode() { if (!running.value) remaining.value.forEach(row => { row.mode = commonMode.value }) }
async function submit() {
    if (running.value || !acknowledged.value || !remaining.value.length) return
    running.value = true; attempted.value = true
    try {
        for (const row of [...remaining.value]) {
            try {
                row.message = '处理中…'
                const res = action.value === 'ship' ? await confirmReturnOrder(row.id, returnShipmentPayload(row, comment.value.trim()))
                    : await updateReturnOrderStatus(row.id, { status: 2, comment: comment.value.trim() })
                const error = returnResponseError(res)
                if (error) throw new Error(error)
                row.result = 'success'; row.message = res.data?.data?.duplicate ? '已处理，无需重复操作' : action.value === 'ship' ? '已发出，等待客户签收' : '已确认客户签收，退回完成'
            } catch (error: any) {
                row.result = 'failed'; row.message = error?.msg || error?.message || '未确认结果，请核对后重试'
            }
        }
        emit('changed')
    } finally { running.value = false }
}
defineExpose({ open })
</script>

<style scoped>
.batch-summary,.batch-tools,.batch-footer{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.batch-summary{margin-bottom:12px}.batch-summary span,.muted{font-size:12px;color:var(--el-text-color-secondary);line-height:1.6}.batch-tools,.batch-rules{margin-top:12px}.batch-table{margin:12px 0}.waybill-fields{display:grid;gap:6px}.batch-ack{margin:0;max-width:100%;height:auto;white-space:normal}.batch-ack :deep(.el-checkbox__label){white-space:normal}.batch-footer{justify-content:space-between;width:100%;text-align:left}.batch-footer-state{display:grid;gap:6px;flex:1;min-width:260px}.batch-footer-state>span{font-size:12px;color:var(--el-text-color-secondary)}.batch-footer-buttons{display:flex;flex-wrap:wrap;gap:8px}.batch-footer-buttons :deep(.el-button){margin:0}.result{overflow-wrap:anywhere}.result.success{color:var(--el-color-success)}.result.failed{color:var(--el-color-danger)}.result.skipped{color:var(--el-text-color-secondary)}
</style>
