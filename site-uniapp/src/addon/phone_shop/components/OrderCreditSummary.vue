<template>
    <view v-if="isCreditOrder(order)" class="credit-summary" :class="{ 'credit-summary--compact': compact }">
        <view class="credit-heading"><text>{{ compact ? creditStatusLabel(order) : 'ERP 收款 · ' + creditStatusLabel(order) }}</text><text v-if="!compact && settlements.length" class="credit-toggle" @click="expanded = !expanded">{{ expanded ? '收起记录' : '收款记录' }}</text></view>
        <view v-if="finance.available && finance.matched" class="credit-amounts"><text>已收 ¥{{ creditMoney(finance.cash_received_amount) }}</text><text>未收 ¥{{ creditMoney(finance.remaining_amount) }}</text><text v-if="Number(finance.offset_amount) > 0">已折账 ¥{{ creditMoney(finance.offset_amount) }}</text></view>
        <template v-if="!compact">
            <view v-if="creditMethods(order)" class="credit-detail">实际收款方式：{{ creditMethods(order) }}</view>
            <view v-if="creditAccounts(order)" class="credit-detail">实际到账账户：{{ creditAccounts(order) }}</view>
            <view class="credit-message">{{ creditMessage(order) }}</view>
            <view class="credit-detail">交付与收款分别记录；财务结清后才能完成订单。此处不发起收款。</view>
            <template v-if="expanded"><view v-for="item in settlements" :key="item.settlement_id" class="credit-record"><view><text>{{ item.method_name || '方式待核对' }}</text><text>¥{{ creditMoney(item.amount) }}</text></view><text v-if="item.capital_account_name">{{ item.capital_account_name }}</text><text>{{ item.operator_name || '财务' }} · {{ formatOrderTime(item.confirmed_at) }}</text><text>{{ item.settlement_no }}</text></view></template>
        </template>
    </view>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue'
import { creditAccounts, creditFinance, creditMessage, creditMethods, creditMoney, creditStatusLabel, isCreditOrder } from '@/addon/phone_shop/utils/orderCredit'
import { formatOrderTime } from '@/addon/phone_shop/utils/orderWorkflow'
const props = withDefaults(defineProps<{ order: Record<string, any>; compact?: boolean }>(), { compact: false })
const finance = computed(() => creditFinance(props.order))
const settlements = computed(() => Array.isArray(finance.value.settlements) ? finance.value.settlements : [])
const expanded = ref(false)
</script>
<style scoped lang="scss">
.credit-summary { padding: 24rpx; border: 1rpx solid #e4eaf3; border-radius: 10rpx; background: #f7f9fc; }.credit-heading { display: flex; align-items: center; justify-content: space-between; gap: 16rpx; color: #516174; font-size: 26rpx; font-weight: 600; }.credit-toggle { color: var(--primary-color); font-size: 23rpx; font-weight: 400; flex-shrink: 0; }.credit-amounts { display: flex; gap: 22rpx; flex-wrap: wrap; color: #516174; font-size: 25rpx; margin-top: 14rpx; }.credit-detail, .credit-message { color: #8590a2; font-size: 23rpx; line-height: 1.6; margin-top: 12rpx; overflow-wrap: anywhere; }.credit-message { color: #9c691e; }.credit-record { display: flex; flex-direction: column; gap: 8rpx; margin-top: 18rpx; padding-top: 18rpx; border-top: 1rpx solid #e4eaf3; color: #8590a2; font-size: 23rpx; overflow-wrap: anywhere; }.credit-record view { display: flex; justify-content: space-between; color: #516174; }.credit-summary--compact { margin: 0 24rpx 20rpx; padding: 14rpx 18rpx; }.credit-summary--compact .credit-heading { font-size: 23rpx; }.credit-summary--compact .credit-amounts { font-size: 23rpx; margin-top: 8rpx; }
</style>
