<template>
    <el-popover placement="left-start" :width="340" trigger="click">
        <template #reference><el-button type="primary" link>退回处理</el-button></template>
        <div class="return-guide">
            <strong>先处理原单，再重新上架</strong>
            <p>原业务员：{{ order.return_handler_name || '尚未记录，请核对实际交付人' }}。请先联系原业务员核对串号、验机和接收退货。</p>
            <p v-if="online">线上付款请在本订单走原路退款。退款成功后，核对实物并点击设备下方的“确认设备已收回”。</p>
            <p v-else>到 ERP 原销售单办理退货：未收款部分冲减原应收；已收款部分按退货单办理退款，不会当作再次销售。</p>
            <p>已收回的设备恢复为待上架，由业务员确认后上架。原订单和收付款记录保留；上架本身不产生账目。</p>
            <p v-if="order.return_context_error" class="warning">{{ order.return_context_error }}</p>
            <template v-if="!online">
                <p v-if="order.erp_return_context">原单剩余应收 ¥{{ money(order.erp_return_context.receivable_remaining) }} · 待退给客户 ¥{{ money(order.erp_return_context.refund_pending) }}</p>
                <el-button v-if="canReturn" type="primary" @click="openErp">到 ERP 办理原单退货</el-button>
                <p v-else-if="hasReturnedItems">设备已办理退回。若仍有待退款，请财务在 ERP 退款应付中处理；不要再建销售单。</p>
                <p v-else class="warning">尚未找到可退回的 ERP 设备明细，请先核对原销售记录及商城设备关联，不要另建销售单。</p>
            </template>
        </div>
    </el-popover>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
const props = defineProps<{ order: any }>()
const router = useRouter()
const online = computed(() => !props.order.relate_source && !['offline_cash', 'offline_credit'].includes(props.order.payment_mode))
const canReturn = computed(() => props.order.erp_return_context?.items?.some((item: any) => item.asset_id > 0 && item.status === 'sold'))
const hasReturnedItems = computed(() => props.order.erp_return_context?.items?.some((item: any) => item.status === 'returned'))
const money = (value: unknown) => Number(value || 0).toFixed(2)
function openErp() {
    router.push({ path: '/site/hsx_erp/sale_return', query: { sale_order_id: props.order.erp_return_context.sale_order_id, refund_mode: 'payable' } })
}
</script>
<style scoped>
.return-guide { color: #475569; font-size: 13px; line-height: 1.7; }
.return-guide strong { color: #0f172a; font-size: 14px; }
.return-guide p { margin: 10px 0; }
.warning { color: #b45309; }
</style>
