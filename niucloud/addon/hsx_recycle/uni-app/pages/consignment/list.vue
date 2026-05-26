<template>
  <view class="consignment-page">
    <RecyclePageHeader title="代卖订单" subtitle="查看代卖进度、成交与结算结果" />

    <view class="status-tabs">
      <scroll-view scroll-x class="status-scroll">
        <view class="status-inner">
          <view
            v-for="tab in statusTabs"
            :key="tab.key"
            class="status-tab"
            :class="{ active: currentStatus === tab.key }"
            @tap="handleStatusChange(tab.key)"
          >
            <text>{{ tab.text }}</text>
            <text v-if="Number(tab.count || 0) > 0" class="status-count">{{ tab.count }}</text>
          </view>
        </view>
      </scroll-view>
    </view>

    <view class="search-card">
      <view class="search-box">
        <up-icon name="search" size="15" color="#94a3b8"></up-icon>
        <input
          v-model="keyword"
          class="search-input"
          confirm-type="search"
          placeholder="搜索代卖单号、IMEI、型号"
          @confirm="refreshList"
        />
        <view v-if="keyword" class="clear-btn" @tap="clearKeyword">
          <up-icon name="close" size="13" color="#94a3b8"></up-icon>
        </view>
      </view>
    </view>

    <mescroll-body
      ref="mescrollRef"
      @down="mescrollDown"
      @up="mescrollUp"
      :up="upOption"
      :down="downOption"
    >
      <view class="list-wrap">
        <view
          v-for="item in orderList"
          :key="item.id"
          class="consignment-card"
          @tap="goDetail(item.id)"
        >
          <view class="card-head">
            <view>
              <text class="order-no">{{ item.consignment_no }}</text>
              <text class="source-no">来源：{{ item.source_order_no || '-' }}</text>
            </view>
            <view class="status-badge" :class="statusClass(item.status)">{{ item.status_name }}</view>
          </view>

          <view class="device-row">
            <view class="device-icon">
              <up-icon name="phone" size="18" color="#2563eb"></up-icon>
            </view>
            <view class="device-main">
              <text class="device-model">{{ item.device_model || item.sourceDevice?.model || '未知设备' }}</text>
              <text class="device-imei">{{ item.device_imei || item.sourceDevice?.imei || '-' }}</text>
            </view>
          </view>

          <view class="amount-grid">
            <view>
              <text>挂牌价</text>
              <strong>¥{{ item.listing_price_text || money(item.listing_price) }}</strong>
            </view>
            <view>
              <text>成交价</text>
              <strong>¥{{ item.sold_price_text || money(item.sold_price) }}</strong>
            </view>
            <view>
              <text>客户结算</text>
              <strong class="green">¥{{ item.settlement_amount_text || money(item.settlement_amount) }}</strong>
            </view>
          </view>

          <view class="card-foot">
            <text>{{ item.progress_text || '代卖订单处理中' }}</text>
            <up-icon name="arrow-right" size="14" color="#cbd5e1"></up-icon>
          </view>
        </view>
      </view>

      <view v-if="orderList.length === 0 && !loading" class="empty-wrap">
        <up-empty mode="data" icon="http://cdn.uviewui.com/uview/empty/data.png" text="暂无代卖订单" textColor="#999999" textSize="15"></up-empty>
      </view>
    </mescroll-body>
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
import RecyclePageHeader from '../components/RecyclePageHeader.vue'
import { getConsignmentOrderList, getConsignmentStatusCount } from '../../api/consignment'

const currentStatus = ref('all')
const keyword = ref('')
const orderList = ref<any[]>([])
const statusTabs = ref<any[]>([{ key: 'all', text: '全部', count: 0 }])
const loading = ref(false)
const mescrollRef = ref<any>(null)

const upOption = {
  auto: true,
  page: { num: 0, size: 10 },
  noMoreSize: 3,
  empty: { tip: '暂无代卖订单' }
}

const downOption = {
  auto: false,
  textInOffset: '下拉刷新',
  textOutOffset: '释放更新',
  textLoading: '加载中...'
}

const money = (value: any) => Number(value || 0).toFixed(2)

const statusClass = (status: any) => {
  const value = Number(status)
  if (value === 4) return 'success'
  if ([1, 2, 3].includes(value)) return 'warning'
  if ([5, 6].includes(value)) return 'muted'
  return 'primary'
}

const loadStatusCount = async () => {
  try {
    const res: any = await getConsignmentStatusCount()
    if (res.code === 1 && res.data?.enabled) {
      statusTabs.value = res.data.list || statusTabs.value
    }
  } catch (error) {
    console.error('加载代卖状态统计失败', error)
  }
}

const mescrollDown = (mescroll: any) => {
  mescroll.resetUpScroll()
}

const mescrollUp = async (mescroll: any) => {
  loading.value = true
  try {
    const params: any = {
      page: mescroll.num,
      limit: mescroll.size,
      keyword: keyword.value
    }
    if (currentStatus.value !== 'all') {
      params.status = currentStatus.value
    }
    const res: any = await getConsignmentOrderList(params)
    if (res.code !== 1) {
      mescroll.endErr()
      uni.showToast({ title: res.msg || '加载失败', icon: 'none' })
      return
    }

    const list = res.data?.data || []
    if (mescroll.num === 1) {
      orderList.value = []
      loadStatusCount()
    }
    orderList.value = [...orderList.value, ...list]
    mescroll.endSuccess(list.length)
    if (list.length < mescroll.size) {
      mescroll.endUpScroll(false)
    }
  } catch (error) {
    console.error('加载代卖订单失败', error)
    mescroll.endErr()
    uni.showToast({ title: '加载失败', icon: 'none' })
  } finally {
    loading.value = false
  }
}

const refreshList = () => {
  if (mescrollRef.value?.mescroll) {
    mescrollRef.value.mescroll.resetUpScroll()
  }
}

const clearKeyword = () => {
  keyword.value = ''
  refreshList()
}

const handleStatusChange = (status: string) => {
  if (currentStatus.value === status) return
  currentStatus.value = status
  refreshList()
}

const goDetail = (id: number) => {
  uni.navigateTo({ url: `/addon/hsx_recycle/pages/consignment/detail?id=${id}` })
}

onLoad((options?: Record<string, any>) => {
  if (options?.status !== undefined) {
    currentStatus.value = String(options.status)
  }
  loadStatusCount()
})
</script>

<style lang="scss" scoped>
.consignment-page {
  min-height: 100vh;
  background: #f5f7fb;
  padding-bottom: 24rpx;
}

.status-tabs {
  background: #fff;
  border-bottom: 1rpx solid #eef2f7;
}

.status-scroll {
  white-space: nowrap;
}

.status-inner {
  display: flex;
  gap: 12rpx;
  padding: 18rpx 24rpx;
}

.status-tab {
  display: inline-flex;
  align-items: center;
  gap: 8rpx;
  padding: 12rpx 20rpx;
  border-radius: 999rpx;
  background: #f8fafc;
  color: #64748b;
  font-size: 24rpx;
}

.status-tab.active {
  background: #2563eb;
  color: #fff;
  font-weight: 600;
}

.status-count {
  font-size: 22rpx;
  opacity: 0.86;
}

.search-card {
  padding: 20rpx 24rpx 0;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 12rpx;
  height: 72rpx;
  padding: 0 22rpx;
  border-radius: 16rpx;
  background: #fff;
}

.search-input {
  flex: 1;
  min-width: 0;
  height: 72rpx;
  color: #111827;
  font-size: 26rpx;
}

.clear-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40rpx;
  height: 40rpx;
  border-radius: 50%;
  background: #f1f5f9;
}

.list-wrap {
  padding: 20rpx 24rpx;
}

.consignment-card {
  margin-bottom: 20rpx;
  padding: 24rpx;
  border-radius: 18rpx;
  background: #fff;
  box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16rpx;
}

.order-no {
  display: block;
  color: #111827;
  font-size: 28rpx;
  font-weight: 700;
}

.source-no {
  display: block;
  margin-top: 6rpx;
  color: #94a3b8;
  font-size: 22rpx;
}

.status-badge {
  flex-shrink: 0;
  padding: 8rpx 14rpx;
  border-radius: 999rpx;
  font-size: 22rpx;
}

.status-badge.primary {
  color: #2563eb;
  background: #eff6ff;
}

.status-badge.warning {
  color: #d97706;
  background: #fffbeb;
}

.status-badge.success {
  color: #059669;
  background: #ecfdf5;
}

.status-badge.muted {
  color: #64748b;
  background: #f1f5f9;
}

.device-row {
  display: flex;
  align-items: center;
  gap: 18rpx;
  margin-top: 22rpx;
  padding: 18rpx;
  border-radius: 14rpx;
  background: #f8fafc;
}

.device-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64rpx;
  height: 64rpx;
  border-radius: 50%;
  background: #dbeafe;
}

.device-main {
  min-width: 0;
  flex: 1;
}

.device-model,
.device-imei {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.device-model {
  color: #111827;
  font-size: 27rpx;
  font-weight: 600;
}

.device-imei {
  margin-top: 5rpx;
  color: #64748b;
  font-size: 23rpx;
}

.amount-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12rpx;
  margin-top: 20rpx;
}

.amount-grid view {
  min-width: 0;
}

.amount-grid text {
  display: block;
  color: #94a3b8;
  font-size: 22rpx;
}

.amount-grid strong {
  display: block;
  margin-top: 6rpx;
  color: #111827;
  font-size: 26rpx;
}

.amount-grid .green {
  color: #059669;
}

.card-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12rpx;
  margin-top: 20rpx;
  padding-top: 18rpx;
  border-top: 1rpx solid #f1f5f9;
  color: #64748b;
  font-size: 23rpx;
  line-height: 1.5;
}

.empty-wrap {
  padding-top: 180rpx;
}
</style>
