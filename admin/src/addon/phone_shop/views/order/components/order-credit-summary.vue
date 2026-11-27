<template>
    <div v-if="isCreditOrder(order)" class="order-credit" :class="{ 'order-credit--compact': compact }">
        <div class="credit-heading"><strong>ERP 收款 · {{ creditStatusLabel(order) }}</strong><el-button v-if="!compact && settlements.length" type="primary" link @click="expanded = !expanded">{{ expanded ? '收起记录' : '收款记录' }}</el-button></div>
        <div v-if="finance.available && finance.matched" class="credit-amounts"><span>已收 ¥{{ creditMoney(finance.cash_received_amount) }}</span><span>未收 ¥{{ creditMoney(finance.remaining_amount) }}</span><span v-if="Number(finance.offset_amount) > 0">已折账 ¥{{ creditMoney(finance.offset_amount) }}</span></div>
        <template v-if="!compact">
            <div v-if="creditMethods(order)">实际收款方式：{{ creditMethods(order) }}</div>
            <div v-if="creditAccounts(order)">实际到账账户：{{ creditAccounts(order) }}</div>
            <p class="credit-message">{{ creditMessage(order) }}</p>
            <p class="credit-help">交付与收款分别记录；财务结清后才能完成订单。此处不发起收款。</p>
            <el-table v-if="expanded" :data="settlements" size="small" class="credit-records">
                <el-table-column label="收款方式" prop="method_name" min-width="110" />
                <el-table-column label="到账账户" prop="capital_account_name" min-width="150" />
                <el-table-column label="结算金额" min-width="110"><template #default="{ row }">¥{{ creditMoney(row.amount) }}</template></el-table-column>
                <el-table-column label="经办人" prop="operator_name" min-width="100" />
                <el-table-column label="确认时间" min-width="160"><template #default="{ row }">{{ receiptTime(row.confirmed_at) }}</template></el-table-column>
            </el-table>
        </template>
        <p v-else-if="!finance.available || !finance.matched" class="credit-message">{{ creditMessage(order) }}</p>
    </div>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue'
import { creditAccounts, creditFinance, creditMessage, creditMethods, creditMoney, creditStatusLabel, isCreditOrder } from '@/addon/phone_shop/utils/order-credit'
const props = withDefaults(defineProps<{ order: Record<string, any>; compact?: boolean }>(), { compact: false })
const finance = computed(() => creditFinance(props.order))
const settlements = computed(() => Array.isArray(finance.value.settlements) ? finance.value.settlements : [])
const expanded = ref(false)
function receiptTime(value: any) {
    const time = Number(value)
    if (!Number.isFinite(time) || time <= 0) return '—'
    return new Date(time * 1000).toLocaleString('zh-CN', { hour12: false })
}
</script>
<style scoped>
.order-credit { display: grid; gap: 8px; margin: 0 30px 20px; padding: 14px 16px; background: var(--el-fill-color-lighter); border: 1px solid var(--el-border-color-light); border-radius: 6px; color: var(--el-text-color-regular); font-size: 13px; line-height: 1.6; overflow-wrap: anywhere; }.credit-heading { display: flex; justify-content: space-between; align-items: center; gap: 12px; }.credit-amounts { display: flex; flex-wrap: wrap; gap: 8px 24px; }.credit-message, .credit-help { margin: 0; }.credit-message { color: var(--el-color-warning-dark-2); }.credit-help { color: var(--el-text-color-secondary); }.order-credit--compact { margin: 0; padding: 8px 12px; border-radius: 0; border-left: 0; border-right: 0; }.order-credit--compact .credit-heading { font-size: 12px; }
</style>
