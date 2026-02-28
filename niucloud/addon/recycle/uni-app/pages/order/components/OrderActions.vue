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
      @click="goReturnOrder"
    >
      <text>退货信息</text>
    </view>

    <!-- 根据订单状态显示不同操作按钮 -->
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
import { ref, watch } from 'vue'
import type { OrderListItem } from '../../../types/order'
import { getReturnOrderByOrderId } from '../../../api/return_order'

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

const hasReturnOrder = ref(false)
const returnOrderList = ref<any[]>([])

// 检查是否有退货订单
const checkReturnOrder = async () => {
  try {
    const res = await getReturnOrderByOrderId(Number(props.order.id))
    const data = res.data || []
    const list = Array.isArray(data) ? data : []
    returnOrderList.value = list
    hasReturnOrder.value = list.length > 0
  } catch (e) {
    hasReturnOrder.value = false
    returnOrderList.value = []
  }
}

// 跳转退货详情
const goReturnOrder = () => {
  if (returnOrderList.value.length === 1) {
    uni.navigateTo({
      url: `/addon/recycle/pages/return_order/detail?id=${returnOrderList.value[0].id}`
    })
  } else if (returnOrderList.value.length > 1) {
    uni.navigateTo({
      url: `/addon/recycle/pages/return_order/list?order_id=${props.order.id}`
    })
  }
}

// 有退货设备(status===6)时才去查询退货订单，避免每张卡片都请求
watch(
  () => props.order,
  (order) => {
    const hasReturnDevice = order.devices?.some((d: any) => d.status === 6)
    if (hasReturnDevice) {
      checkReturnOrder()
    } else {
      hasReturnOrder.value = false
      returnOrderList.value = []
    }
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
    background: linear-gradient(to right, #8C7575, #5F758A);
    color: #fff;
    border-color: transparent;

    &:active {
      opacity: 0.8;
    }
  }

  &.secondary {
    background: #fff;
    color: #64748b;
    border-color: #e2e8f0;

    &:active {
      background: #f8fafc;
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
    background: #fff;
    color: #f59e0b;
    border-color: #fde68a;

    &:active {
      background: #fffbeb;
    }
  }
}
</style>
