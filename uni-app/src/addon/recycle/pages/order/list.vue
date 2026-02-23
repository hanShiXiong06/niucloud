<template>
    <up-sticky bgColor="#fff" class="!top-0 !z-10 p-2">
        <OrderListFilters
        v-model:currentStatus="currentStatus"
        v-model:deliveryType="deliveryType"
        v-model:searchKeyword="searchKeyword"
        :statusOptions="statusOptions"
        :deliveryOptions="deliveryOptions"
        @search="handleSearch"
        />
        </up-sticky>
  <view class="min-h-screen bg-gray-50">
    <!-- 筛选栏 -->
      <!-- 状态标签栏 - 使用 up-tabs -->
    
    <!-- 订单列表 -->
    <mescroll-body
      ref="mescrollRef"
      @down="mescrollDown"
      @up="mescrollUp"
      :up="upOption"
      :down="downOption"
    >
      <OrderCard
        v-for="order in orderList"
        :key="order.id"
        :order="order"
        @action-success="handleActionSuccess"
      />

      <!-- 空状态 -->
      <mescroll-empty
        v-if="orderList.length === 0"
        :option="emptyOption"
      />
    </mescroll-body>

    <tabbar addon="recycle" />
  </view>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
import MescrollEmpty from '@/components/mescroll/mescroll-empty/mescroll-empty.vue'

// 导入组件
import OrderListFilters from './components/OrderListFilters.vue'
import OrderCard from './components/OrderCard.vue'

// 导入 composables
import { useOrderList } from '../../hooks/useOrderList'
import { useOrderFilters } from '../../hooks/useOrderFilters'

// 筛选管理
const {
  currentStatus,
  deliveryType,
  searchKeyword,
  statusOptions,
  deliveryOptions,
  filters,
  fetchStatusCounts
} = useOrderFilters()

// 订单列表管理
const {
  orderList,
  mescrollRef,
  upOption,
  downOption,
  emptyOption,
  mescrollDown,
  mescrollUp: mescrollUpBase,
  refreshList,
  removeOrder,
  updateOrderStatus
} = useOrderList()

// 包装 mescrollUp 以传递筛选条件
const mescrollUp = (mescroll: any) => {
  mescrollUpBase(mescroll, filters.value)
}

// 监听筛选条件变化，刷新列表
watch([currentStatus, deliveryType], () => {
  refreshList()
})

// 搜索处理
const handleSearch = () => {
  refreshList()
}

// 操作成功后的处理
const handleActionSuccess = (action: string) => {
  // 刷新列表
  refreshList()

  // 删除订单后，同步刷新筛选栏中的远程统计数量
    fetchStatusCounts()
}
</script>

<style scoped lang="scss">
// 使用 Windi CSS，无需额外样式
</style>
