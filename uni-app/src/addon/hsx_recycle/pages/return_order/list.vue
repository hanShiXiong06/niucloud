<template>
  <view class="return-order-page">
    <RecyclePageHeader title="退货进度" subtitle="查看设备退回和物流状态" />

    <scroll-view scroll-x class="return-order-tabs" :show-scrollbar="false">
      <view class="return-order-tabs__inner">
        <view
          v-for="tab in statusTabs"
          :key="tab.value"
          class="return-order-tabs__item"
          :class="{ 'return-order-tabs__item--active': currentStatus === tab.value }"
          @tap="handleStatusChange(tab.value)"
        >
          {{ tab.text }}
        </view>
      </view>
    </scroll-view>

    <mescroll-body
      ref="mescrollRef"
      @down="mescrollDown"
      @up="mescrollUp"
      :up="upOption"
      :down="downOption"
    >
      <view class="return-order-list">
        <view
          v-for="item in orderList"
          :key="item.id"
          class="return-order-card"
          @tap="goToDetail(item.id)"
        >
          <view class="return-order-card__head">
            <view class="return-order-card__identity">
              <text class="return-order-card__title">退货申请</text>
              <text class="return-order-card__time">{{ item.create_at }}</text>
            </view>
            <text
              class="return-order-card__status"
              :style="{ color: getStatusColor(item.status), backgroundColor: getStatusBg(item.status) }"
            >
              {{ item.status_name || getStatusText(item.status) }}
            </text>
          </view>

          <view class="return-order-card__number-row">
            <text class="return-order-card__label">退货单号</text>
            <text class="return-order-card__number">{{ item.order_no }}</text>
          </view>

          <view class="return-order-card__summary">
            <view class="return-order-card__summary-item">
              <up-icon name="gift" size="16" color="#64748b" />
              <text>退回 {{ getDeviceCount(item) }} 台设备</text>
            </view>
            <view v-if="item.express_company || item.express_no" class="return-order-card__summary-item">
              <up-icon name="car" size="16" color="#64748b" />
              <text class="return-order-card__summary-text">
                {{ item.express_company || '退回物流' }}{{ item.express_no ? ` · ${item.express_no}` : '' }}
              </text>
            </view>
          </view>

          <view v-if="item.remark" class="return-order-card__remark">
            <text class="return-order-card__remark-label">备注</text>
            <text class="return-order-card__remark-value">{{ item.remark }}</text>
          </view>

          <view class="return-order-card__footer">
            <text>查看退货进度</text>
            <up-icon name="arrow-right" size="15" color="var(--recycle-brand)" />
          </view>
        </view>
      </view>

      <view v-if="orderList.length === 0 && !loading" class="return-order-empty">
        <up-empty mode="data" text="暂无退货订单" textColor="#8b96a9" textSize="14" />
      </view>
    </mescroll-body>
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import MescrollBody from '@/components/mescroll/mescroll-body/mescroll-body.vue'
import { getReturnOrderList } from '../../api/return_order'
import { getReturnOrderStatusInfo } from '../../utils/theme'
import RecyclePageHeader from '../components/RecyclePageHeader.vue'

const statusTabs = [
  { text: '全部', value: -1 },
  { text: '待处理', value: 0 },
  { text: '退货中', value: 1 },
  { text: '已完成', value: 2 },
  { text: '已取消', value: 3 },
]

const currentStatus = ref(-1)
const orderId = ref<number | null>(null)
const orderList = ref<any[]>([])
const loading = ref(false)
const mescrollRef = ref<any>(null)

const upOption = {
  auto: true,
  page: { num: 0, size: 10 },
  noMoreSize: 3,
  empty: { use: false }
}

const downOption = {
  auto: false,
  textInOffset: '下拉刷新',
  textOutOffset: '释放更新',
  textLoading: '加载中...'
}

const getStatusColor = (status: number) => getReturnOrderStatusInfo(status).color
const getStatusBg = (status: number) => getReturnOrderStatusInfo(status).bgColor
const getStatusText = (status: number) => getReturnOrderStatusInfo(status).text
const getDeviceCount = (item: any) => (item.return_devices || item.returnDevices || []).length

const mescrollDown = (mescroll: any) => {
  mescroll.resetUpScroll()
}

const mescrollUp = async (mescroll: any) => {
  try {
    loading.value = true
    const params: any = { page: mescroll.num, limit: mescroll.size }
    if (currentStatus.value !== -1) params.status = currentStatus.value
    if (orderId.value) params.order_id = orderId.value

    const res: any = await getReturnOrderList(params)
    if (res.code !== 1) {
      mescroll.endErr()
      uni.showToast({ title: res.msg || '加载失败', icon: 'none' })
      return
    }

    const list = res.data?.data || res.data?.list || []
    if (mescroll.num === 1) orderList.value = []
    orderList.value = [...orderList.value, ...list]
    mescroll.endSuccess(list.length)
    if (list.length < mescroll.size) mescroll.endUpScroll(false)
  } catch (error) {
    console.error('加载退货订单列表失败：', error)
    mescroll.endErr()
    uni.showToast({ title: '加载失败', icon: 'none' })
  } finally {
    loading.value = false
  }
}

const refreshList = () => {
  mescrollRef.value?.mescroll?.resetUpScroll()
}

const goToDetail = (id: number) => {
  uni.navigateTo({ url: `/addon/hsx_recycle/pages/return_order/detail?id=${id}` })
}

const handleStatusChange = (status: number) => {
  if (currentStatus.value === status) return
  currentStatus.value = status
  refreshList()
}

onLoad((options?: Record<string, any>) => {
  if (options?.order_id) orderId.value = Number(options.order_id)
})
</script>

<style scoped lang="scss">
.return-order-page {
  min-height: 100vh;
  background: #f5f6f8;
}

.return-order-tabs {
  width: 100%;
  padding: 16rpx 24rpx 12rpx;
  background: #fff;
  border-bottom: 1rpx solid #edf0f4;
  box-sizing: border-box;
  white-space: nowrap;
}

.return-order-tabs__inner {
  display: inline-flex;
  gap: 12rpx;
}

.return-order-tabs__item {
  padding: 13rpx 25rpx;
  border-radius: 999rpx;
  background: #f4f6f8;
  color: #667085;
  font-size: 24rpx;
  line-height: 32rpx;
}

.return-order-tabs__item--active {
  background: var(--recycle-brand);
  color: #fff;
  font-weight: 650;
  box-shadow: 0 8rpx 18rpx rgba(37, 99, 235, 0.16);
}

.return-order-list {
  padding: 18rpx 24rpx 28rpx;
}

.return-order-card {
  margin-bottom: 18rpx;
  overflow: hidden;
  border: 1rpx solid #e8ecf1;
  border-radius: 24rpx;
  background: #fff;
  box-shadow: 0 8rpx 24rpx rgba(31, 41, 55, 0.045);
}

.return-order-card__head,
.return-order-card__number-row,
.return-order-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.return-order-card__head {
  padding: 24rpx 24rpx 18rpx;
}

.return-order-card__identity {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.return-order-card__title {
  color: #172033;
  font-size: 30rpx;
  line-height: 42rpx;
  font-weight: 700;
}

.return-order-card__time {
  margin-top: 3rpx;
  color: #a0a8b5;
  font-size: 21rpx;
  line-height: 30rpx;
}

.return-order-card__status {
  flex-shrink: 0;
  padding: 7rpx 15rpx;
  border-radius: 999rpx;
  font-size: 21rpx;
  line-height: 28rpx;
  font-weight: 650;
}

.return-order-card__number-row {
  margin: 0 24rpx;
  padding: 16rpx 18rpx;
  border-radius: 16rpx;
  background: #f6f8fa;
  gap: 20rpx;
}

.return-order-card__label,
.return-order-card__remark-label {
  flex-shrink: 0;
  color: #8b96a9;
  font-size: 22rpx;
}

.return-order-card__number {
  min-width: 0;
  color: #455268;
  font-size: 23rpx;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.return-order-card__summary {
  padding: 18rpx 24rpx 12rpx;
  display: flex;
  flex-direction: column;
  gap: 12rpx;
}

.return-order-card__summary-item {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 10rpx;
  color: #5f6c80;
  font-size: 23rpx;
  line-height: 34rpx;
}

.return-order-card__summary-text {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.return-order-card__remark {
  margin: 0 24rpx 18rpx;
  padding: 14rpx 16rpx;
  border-radius: 14rpx;
  background: #fff8ed;
  display: flex;
  gap: 14rpx;
}

.return-order-card__remark-value {
  min-width: 0;
  flex: 1;
  color: #7b6340;
  font-size: 22rpx;
  line-height: 32rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.return-order-card__footer {
  padding: 19rpx 24rpx;
  border-top: 1rpx solid #edf0f4;
  color: var(--recycle-brand);
  font-size: 23rpx;
  font-weight: 650;
}

.return-order-empty {
  padding-top: 180rpx;
}
</style>
