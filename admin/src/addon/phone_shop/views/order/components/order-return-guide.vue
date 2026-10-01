<template>
    <el-popover v-if="online" placement="left-start" :width="340" trigger="click">
        <template #reference><el-button type="primary" link>退回处理</el-button></template>
        <div class="return-online">
            <strong>线上付款请走原路退款</strong>
            <p>原业务员：{{ order.return_handler_name || '尚未记录' }}。请先核对串号并接收退货。</p>
            <p>退款成功后，点击设备下方的“确认设备已收回”。收回后恢复待上架，原订单和收付款记录保留。</p>
            <p v-if="order.return_context_error">{{ order.return_context_error }}</p>
        </div>
    </el-popover>
    <el-button v-else type="primary" link @click="open">退回处理</el-button>
    <HsxDrawer v-model="visible" title="收回设备并处理原账" :subtitle="plan.order_no || order.order_no"
        size="lg" show-footer confirm-text="确认收回并处理原账" :body-loading="loading"
        :confirm-loading="submitting" :confirm-disabled="!canSubmit" @confirm="submit">
        <div class="return-form">
            <HsxNotice title="收回后恢复待上架，不会自动退款" :closable="false"
                description="未收款部分冲销原应收；已收款部分生成待退款。财务实际转账后，在 ERP 待付款中选择退款账户确认。全部设备退回后关闭原订单，原成交记录保留。" />
            <div class="return-owner">原业务员：{{ order.return_handler_name || '尚未记录' }}<span> · 本次操作人将作为退回接收人留痕。</span></div>
            <HsxNotice v-if="error" type="error" :title="error" :closable="false">
                <template #actions><el-button link type="primary" :disabled="submitting" @click="load">重新核对原账</el-button></template>
            </HsxNotice>
            <HsxNotice v-if="refundPending > 0" type="warning" :title="'已退回设备还有 ¥' + money(refundPending) + ' 待财务退款'" :closable="false">
                <template #actions><el-button type="primary" link @click="router.push('/site/hsx_erp/payable')">查看退款账目</el-button></template>
            </HsxNotice>
            <template v-if="plan.items.length">
                <el-table :data="plan.items" row-key="order_goods_id" border class="return-table">
                    <el-table-column label="退回" width="62" align="center">
                        <template #default="{ row }"><el-checkbox v-model="row.selected" :disabled="!eligible(row) || submitting" :aria-label="'退回 ' + (row.imei || row.goods_name)" /></template>
                    </el-table-column>
                    <el-table-column label="设备 / 原成交串号" min-width="220">
                        <template #default="{ row }">
                            <strong>{{ row.goods_name }}</strong><div>{{ row.sku_name }}</div><div class="return-imei">{{ row.imei || '未记录串号，请核对实物' }}</div>
                            <div v-if="!eligible(row)" class="return-block">{{ row.requires_location ? '原仓库信息缺失，请到 ERP 退货单选择实际收货仓库办理' : row.reason }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="原成交" width="105" align="right"><template #default="{ row }">¥{{ money(row.return_amount) }}</template></el-table-column>
                    <el-table-column label="冲销未收" width="105" align="right"><template #default="{ row }">¥{{ money(row.offset_amount) }}</template></el-table-column>
                    <el-table-column label="交财务退款" width="115" align="right"><template #default="{ row }">¥{{ money(row.refund_amount) }}</template></el-table-column>
                </el-table>
                <div class="return-total"><span>已选 {{ selected.length }} 台</span><span>冲销未收 <strong>¥{{ money(offsetTotal) }}</strong></span><span>待退款 <strong>¥{{ money(refundTotal) }}</strong></span></div>
                <el-form label-position="top">
                    <el-form-item label="退回原因" required>
                        <el-input v-model="reason" type="textarea" :rows="2" maxlength="255" show-word-limit :disabled="submitting" placeholder="例如：客户未售出退回，已核对原设备" />
                    </el-form-item>
                    <el-checkbox v-model="received" :disabled="submitting" class="return-received">我已核对所选设备串号，并实际收回设备</el-checkbox>
                </el-form>
            </template>
            <el-empty v-else-if="!loading && !error" description="没有可办理的设备" />
            <el-button v-if="plan.items.some((row: any) => row.requires_location)" link type="primary" @click="openErp">到 ERP 选择收货仓库办理</el-button>
        </div>
    </HsxDrawer>
</template>
<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { HsxDrawer, HsxNotice } from '@/addon/hsx_components/core'
import { processOfflineOrderAction } from '@/addon/phone_shop/api/order'
const props = defineProps<{ order: any }>()
const emit = defineEmits<{ (event: 'complete'): void }>()
const router = useRouter()
const visible = ref(false), loading = ref(false), submitting = ref(false), error = ref('')
const reason = ref(''), received = ref(false)
const plan = reactive<{ order_no: string; preview_token: string; items: any[] }>({ order_no: '', preview_token: '', items: [] })
const online = computed(() => !props.order.relate_source && !['offline_cash', 'offline_credit'].includes(props.order.payment_mode))
const eligible = (row: any) => row.can_return && !row.requires_location
const selected = computed(() => plan.items.filter(row => row.selected && eligible(row)))
const offsetTotal = computed(() => selected.value.reduce((sum, row) => sum + Number(row.offset_amount || 0), 0))
const refundTotal = computed(() => selected.value.reduce((sum, row) => sum + Number(row.refund_amount || 0), 0))
const refundPending = computed(() => plan.items.reduce((sum, row) => sum + Number(row.refund_pending || 0), 0))
const canSubmit = computed(() => !loading.value && !error.value && selected.value.length > 0 && !!reason.value.trim() && received.value)
const money = (value: unknown) => Number(value || 0).toFixed(2)
const message = (e: any) => e?.msg || e?.message || '核对失败，请刷新原账后重试；不要重复退款'
async function open() { visible.value = true; reason.value = ''; received.value = false; await load() }
async function load() {
    if (loading.value || submitting.value) return
    loading.value = true; error.value = ''; received.value = false; plan.items = []; plan.preview_token = ''
    try {
        const { data } = await processOfflineOrderAction({ order_id: props.order.order_id, action: 'return_preview' })
        plan.order_no = data.order_no; plan.preview_token = data.preview_token
        plan.items = (data.items || []).map((row: any) => ({ ...row, selected: !!eligible(row) }))
    } catch (e) { error.value = message(e) } finally { loading.value = false }
}
async function submit() {
    if (!canSubmit.value || submitting.value) return
    submitting.value = true
    try {
        const { data } = await processOfflineOrderAction({
            order_id: props.order.order_id, action: 'return_received', order_goods_ids: selected.value.map(row => row.order_goods_id),
            reason: reason.value.trim(), received: true, preview_token: plan.preview_token
        })
        ElMessage.success(data.duplicate ? data.message : `设备已收回；冲销未收 ¥${money(data.offset_amount)}，待财务退款 ¥${money(data.refund_amount)}`)
        visible.value = false; emit('complete')
    } catch (e) { error.value = message(e); received.value = false } finally { submitting.value = false }
}
function openErp() {
    const row = plan.items.find(item => item.requires_location)
    router.push({ path: '/site/hsx_erp/sale_return', query: { sale_order_id: row?.sale_order_id, refund_mode: 'payable' } })
}
</script>
<style scoped>
.return-form { display: grid; gap: 16px; color: var(--el-text-color-primary); }
.return-owner { font-size: 13px; line-height: 22px; }
.return-owner span { margin-left: 16px; color: var(--el-text-color-secondary); }
.return-imei { font-family: ui-monospace, monospace; color: var(--el-text-color-secondary); overflow-wrap: anywhere; }
.return-table strong { font-size: 13px; }
.return-block { color: var(--el-color-warning); font-size: 12px; line-height: 19px; margin-top: 4px; }
.return-total { display: flex; flex-wrap: wrap; gap: 10px 24px; justify-content: flex-end; padding: 12px; background: var(--el-fill-color-light); border-radius: 6px; font-size: 13px; }
.return-total strong { font-size: 17px; }
.return-received { height: auto; white-space: normal; }
.return-received :deep(.el-checkbox__label) { white-space: normal; line-height: 22px; }
.return-online { font-size: 13px; line-height: 1.7; }
.return-online p { margin: 10px 0; }
</style>
