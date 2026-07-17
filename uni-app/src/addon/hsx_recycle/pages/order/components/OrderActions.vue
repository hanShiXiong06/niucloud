<template>
  <view class="order-actions">
    <view
      v-if="hasReturnOrder"
      class="order-actions__link order-actions__link--warning"
      @tap="goToReturnOrder(order.id)"
    >
      <text>退货信息</text>
    </view>
    <view
      v-if="order.status === 1"
      class="order-actions__link order-actions__link--danger"
      @tap="$emit('cancel')"
    >
      <text>取消订单</text>
    </view>
    <view
      v-if="order.status === 9"
      class="order-actions__link order-actions__link--danger"
      @tap="$emit('delete')"
    >
      <text>删除订单</text>
    </view>
    <view class="order-actions__spacer" />
    <view class="order-actions__primary" @tap="$emit('view-detail')">
      <text>查看详情</text>
      <up-icon name="arrow-right" size="13" color="#fff" />
    </view>
  </view>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import type { OrderListItem } from '../../../types/order'
import { useReturnOrder } from '../../../hooks/useReturnOrder'

interface Props {
  order: OrderListItem
}

const props = defineProps<Props>()

defineEmits<{
  'view-detail': []
  'cancel': []
  'confirm': []
  'delete': []
}>()

const { hasReturnOrder, goToReturnOrder, checkReturnOrderByDevices } = useReturnOrder()

watch(
  () => props.order,
  (order) => {
    checkReturnOrderByDevices(order.id, order.devices)
  },
  { immediate: true }
)
</script>

<style scoped lang="scss">
.order-actions {
  min-height: 78rpx;
  padding-top: 18rpx;
  display: flex;
  align-items: center;
  gap: 22rpx;
}

.order-actions__spacer {
  flex: 1;
}

.order-actions__link {
  padding: 10rpx 0;
  color: #7b8798;
  font-size: 22rpx;
  line-height: 32rpx;
}

.order-actions__link--warning {
  color: #d97706;
}

.order-actions__link--danger {
  color: #dc6262;
}

.order-actions__primary {
  height: 58rpx;
  padding: 0 22rpx;
  border-radius: 999rpx;
  background: var(--recycle-button-bg);
  color: var(--recycle-button-text);
  display: flex;
  align-items: center;
  gap: 6rpx;
  font-size: 23rpx;
  line-height: 58rpx;
  font-weight: 600;

  &:active {
    opacity: 0.84;
  }
}
</style>
