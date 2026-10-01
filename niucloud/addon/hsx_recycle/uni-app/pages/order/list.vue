<template>
  <view class="recycle-order-list-page" :style="pageVars">
    <RecyclePageHeader title="我的订单" />
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
      :empty-view-text="hasFilters ? '没有符合条件的订单' : '还没有回收订单'"
      empty-view-error-text="订单加载失败，点击重试"
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
      <template #empty="{ isLoadFailed }">
        <view class="order-list-empty">
          <up-empty :mode="isLoadFailed ? 'wifi' : 'order'" :text="isLoadFailed ? '订单加载失败，请稍后重试' : hasFilters ? '没有符合条件的订单' : '还没有回收订单'" />
          <view class="empty-action">
            <OrderUiButton variant="primary" @click="isLoadFailed ? refreshList() : handleEmptyAction()">{{ isLoadFailed ? '重新加载' : hasFilters ? '清除筛选' : '去下单' }}</OrderUiButton>
          </view>
        </view>
      </template>
    </z-paging>

    <tabbar addon="hsx_recycle" />
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
import OrderUiButton from './components/OrderUiButton.vue'
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
  hasFilters,
  resetFilters,
  fetchStatusCounts
} = useOrderFilters()

// 订单列表管理
const {
  orderList,
  pagingRef,
  queryList,
  refreshList
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

const handleEmptyAction = () => {
  if (hasFilters.value) {
    const statusWillChange = currentStatus.value !== 'all' || deliveryType.value !== 0
    resetFilters()
    if (!statusWillChange) refreshList()
  } else {
    uni.navigateTo({ url: '/addon/hsx_recycle/pages/order/order' })
  }
}

// 搜索处理
const handleSearch = () => {
  refreshList()
}

// 操作成功后的处理
const handleActionSuccess = () => {
  // 刷新列表
  refreshList()

  // 删除订单后，同步刷新筛选栏中的远程统计数量
  fetchStatusCounts()
}
</script>

<style scoped lang="scss">
@import './order-ui.scss';
.recycle-order-list-page { @include recycle-order-page; }
.order-list-filter-shell { background: var(--recycle-bg-card); }
.order-list-content { padding: 20rpx 0; }
.order-list-empty { padding: 48rpx 28rpx; }
.empty-action { padding: 24rpx; display: flex; justify-content: center; }
</style>
