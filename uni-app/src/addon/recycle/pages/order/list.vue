<template>
  <view class="recycle-order-list-page" :style="pageVars">
    <RecyclePageHeader title="我的订单" subtitle="查看回收进度和物流状态" />
    <z-paging
      ref="pagingRef"
      v-model="orderList"
      class="order-z-paging"
      :paging-style="pagingStyle"
      :fixed="false"
      :safe-area-inset-bottom="false"
      :default-page-size="10"
      :auto-clean-list-when-reload="false"
      :hide-no-more-inside="true"
      :show-loading-more-no-more-line="false"
      empty-view-text="暂无订单数据"
      loading-more-no-more-text="没有更多订单了"
      loading-more-loading-text="正在加载订单..."
      @query="queryOrderList"
    >
      <template #top>
        <view class="order-list-filter-shell">
          <OrderListFilters
            v-model:currentStatus="currentStatus"
            v-model:deliveryType="deliveryType"
            v-model:searchKeyword="searchKeyword"
            :statusOptions="statusOptions"
            :deliveryOptions="deliveryOptions"
            @search="handleSearch"
          />
        </view>
      </template>

      <view class="order-list-content">
        <OrderCard
          v-for="order in orderList"
          :key="order.id"
          :order="order"
          @action-success="handleActionSuccess"
        />

      </view>

    </z-paging>

    <tabbar addon="recycle" />
  </view>
</template>

<script setup lang="ts">
import { onShow } from '@dcloudio/uni-app'
import { computed, watch } from 'vue'
import ZPaging from '../../components/z-paging/z-paging/z-paging.vue'
import RecyclePageHeader from '../components/RecyclePageHeader.vue'
import { useRecyclePageTheme } from '../../hooks/useRecyclePageTheme'
import { getRecycleNavbarMetrics } from '../../hooks/useRecycleNavbar'

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
  pagingRef,
  queryList,
  refreshList,
  removeOrder,
  updateOrderStatus
} = useOrderList()

const { themeVars, loadTheme } = useRecyclePageTheme()
const navbarMetrics = getRecycleNavbarMetrics()
const pageVars = computed(() => [
  themeVars.value,
  `--recycle-navbar-height:${navbarMetrics.navbarHeightPx}px;`,
  `--recycle-tabbar-height:50px;`
].join(''))
const pagingStyle = computed(() => ({
  height: `calc(100vh - ${navbarMetrics.navbarHeightPx}px - var(--recycle-tabbar-height) - env(safe-area-inset-bottom))`,
  background: 'var(--recycle-bg-main)'
}))

onShow(() => {
  loadTheme()
  fetchStatusCounts()
})

const queryOrderList = (pageNo: number, pageSize: number) => {
  queryList(pageNo, pageSize, filters.value)
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
.recycle-order-list-page {
  min-height: 100vh;
  background: var(--recycle-bg-main);
  color: var(--recycle-text-main);
}

.order-list-filter-shell {
  padding: 12rpx 16rpx;
  background: var(--recycle-bg-main);
}

.order-list-content {
  padding: 0 0 12rpx;
}

</style>
