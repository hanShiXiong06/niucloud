<template>
  <view class="flex items-center justify-end gap-2 mt-3">
    <!-- 查看详情按钮 -->
    <view
      class="action-btn secondary"
      @click="$emit('view-detail')"
    >
      <text>查看详情</text>
    </view>

    <!-- 退货信息按钮 -->
    <view
      v-if="hasReturnOrder"
      class="action-btn return-info"
      @click="goToReturnOrder(order.id)"
    >
      <text>退货信息</text>
    </view>

    <!-- 状态1-待签收：可以取消订单 -->
    <view
      v-if="order.status === 1"
      class="action-btn danger"
      @click="$emit('cancel')"
    >
      <text>取消订单</text>
    </view>

    <!-- 状态9-已取消：可以删除订单 -->
    <view
      v-if="order.status === 9"
      class="action-btn danger"
      @click="$emit('delete')"
    >
      <text>删除订单</text>
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
.action-btn {
  padding: 6px 16px;
  border-radius: 16px;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s;
  border: 1px solid;

  &.primary {
    background: var(--recycle-button-bg);
    color: var(--recycle-button-text);
    border-color: transparent;

    &:active {
      opacity: 0.8;
    }
  }

  &.secondary {
    background: var(--recycle-bg-card);
    color: var(--recycle-text-sub);
    border-color: var(--recycle-line);

    &:active {
      background: var(--recycle-bg-soft);
    }
  }

  &.danger {
    background: #fff;
    color: #ef4444;
    border-color: #fecaca;

    &:active {
      background: #fef2f2;
    }
  }

  &.return-info {
    background: var(--recycle-notice-bg);
    color: var(--recycle-notice-text);
    border-color: rgba(245, 158, 11, 0.24);

    &:active {
      background: #fffbeb;
    }
  }
}
</style>
