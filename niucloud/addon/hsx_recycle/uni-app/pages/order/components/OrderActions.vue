<template>
  <view class="order-actions">
    <OrderUiButton v-if="hasReturnOrder" variant="text" @click="goToReturnOrder(order.id)">退回信息</OrderUiButton>
    <OrderUiButton v-if="Number(order.status) === 1" :disabled="busy" @click="$emit('cancel')">取消订单</OrderUiButton>
    <OrderUiButton v-if="Number(order.status) === 9" :disabled="busy" @click="$emit('delete')">删除订单</OrderUiButton>
    <OrderUiButton :variant="Number(order.status) === 5 ? 'primary' : 'secondary'" @click="$emit('view-detail')">{{ Number(order.status) === 5 ? '核对报价' : '查看详情' }}</OrderUiButton>
  </view>
</template>
<script setup lang="ts">
import { watch } from 'vue'
import type { OrderListItem } from '../../../types/order'
import { useReturnOrder } from '../../../hooks/useReturnOrder'
import OrderUiButton from './OrderUiButton.vue'
const props = defineProps<{ order: OrderListItem; busy?: boolean }>()
defineEmits<{ 'view-detail': []; cancel: []; confirm: []; delete: [] }>()
const { hasReturnOrder, goToReturnOrder, checkReturnOrderByDevices } = useReturnOrder()
watch(() => props.order, order => checkReturnOrderByDevices(order.id, order.devices), { immediate: true })
</script>
<style scoped lang="scss">
.order-actions { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 12rpx; padding-top: 20rpx; margin-top: 20rpx; border-top: 1rpx solid var(--recycle-line); }
</style>
