<template>
  <view class="consignment-detail-page">
    <RecyclePageHeader title="代卖详情" :subtitle="detail.consignment_no || '查看代卖订单进度'" />

    <view v-if="loading" class="loading-wrap">
      <view class="skeleton-card"></view>
      <view class="skeleton-card small"></view>
      <view class="skeleton-card small"></view>
    </view>

    <view v-else-if="!detail.id" class="empty-wrap">
      <up-empty mode="data" icon="http://cdn.uviewui.com/uview/empty/data.png" text="代卖订单不存在" textColor="#999999" textSize="15"></up-empty>
    </view>

    <view v-else class="content-wrap">
      <view class="hero-card">
        <view class="hero-top">
          <view>
            <text class="status-name">{{ detail.status_name }}</text>
            <text class="status-desc">{{ detail.progress_text }}</text>
          </view>
          <view class="status-badge" :class="statusClass(detail.status)">{{ detail.pay_status_name }}</view>
        </view>
        <view class="hero-meta">
          <view>
            <text>代卖单号</text>
            <strong>{{ detail.consignment_no }}</strong>
          </view>
          <view>
            <text>创建时间</text>
            <strong>{{ detail.create_time_text || '-' }}</strong>
          </view>
        </view>
      </view>

      <view class="section-card">
        <view class="section-title">
          <view class="bar blue"></view>
          <text>设备信息</text>
        </view>
        <view class="device-box">
          <view class="device-icon">
            <up-icon name="phone" size="20" color="#2563eb"></up-icon>
          </view>
          <view class="device-main">
            <text class="device-model">{{ detail.device_model || detail.sourceDevice?.model || '未知设备' }}</text>
            <text class="device-imei">{{ detail.device_imei || detail.sourceDevice?.imei || '-' }}</text>
          </view>
        </view>
        <view class="info-list">
          <view v-if="detail.sourceDevice?.capacity">
            <text>容量</text>
            <strong>{{ detail.sourceDevice.capacity }}</strong>
          </view>
          <view v-if="detail.sourceDevice?.color">
            <text>颜色</text>
            <strong>{{ detail.sourceDevice.color }}</strong>
          </view>
          <view>
            <text>来源订单</text>
            <view class="link-text" @tap="goSourceOrder">{{ detail.source_order_no || '-' }}</view>
          </view>
        </view>
      </view>

      <view class="section-card">
        <view class="section-title">
          <view class="bar green"></view>
          <text>金额进度</text>
        </view>
        <view class="amount-list">
          <view>
            <text>原回收报价</text>
            <strong>¥{{ detail.quote_price_text || money(detail.quote_price) }}</strong>
          </view>
          <view>
            <text>客户期望价</text>
            <strong>¥{{ detail.expected_price_text || money(detail.expected_price) }}</strong>
          </view>
          <view>
            <text>最低结算价</text>
            <strong>¥{{ detail.min_settlement_price_text || money(detail.min_settlement_price) }}</strong>
          </view>
          <view>
            <text>挂牌价</text>
            <strong>¥{{ detail.listing_price_text || money(detail.listing_price) }}</strong>
          </view>
          <view>
            <text>成交价</text>
            <strong>¥{{ detail.sold_price_text || money(detail.sold_price) }}</strong>
          </view>
          <view>
            <text>客户结算</text>
            <strong class="green">¥{{ detail.settlement_amount_text || money(detail.settlement_amount) }}</strong>
          </view>
          <view v-if="detail.service_fee_text">
            <text>服务收益</text>
            <strong>¥{{ detail.service_fee_text }}</strong>
          </view>
        </view>
      </view>

      <view class="section-card">
        <view class="section-title">
          <view class="bar orange"></view>
          <text>关键时间</text>
        </view>
        <view class="info-list">
          <view>
            <text>上架时间</text>
            <strong>{{ detail.listed_time_text || '-' }}</strong>
          </view>
          <view>
            <text>售出时间</text>
            <strong>{{ detail.sold_time_text || '-' }}</strong>
          </view>
          <view>
            <text>结算时间</text>
            <strong>{{ detail.settle_time_text || detail.pay_time_text || '-' }}</strong>
          </view>
        </view>
      </view>

      <view class="section-card">
        <view class="section-title">
          <view class="bar slate"></view>
          <text>处理记录</text>
        </view>
        <view v-if="(detail.logs || []).length" class="timeline">
          <view v-for="log in detail.logs" :key="log.id" class="timeline-item">
            <view class="dot"></view>
            <view class="timeline-main">
              <view class="timeline-head">
                <text>{{ log.action_name || log.new_status_name || '状态更新' }}</text>
                <strong>{{ log.create_time_text || '-' }}</strong>
              </view>
              <view class="timeline-desc">{{ log.remark || `${log.old_status_name || ''} → ${log.new_status_name || ''}` }}</view>
            </view>
          </view>
        </view>
        <view v-else class="empty-log">暂无处理记录</view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import RecyclePageHeader from '../components/RecyclePageHeader.vue'
import { getConsignmentOrderDetail } from '../../api/consignment'

const id = ref(0)
const loading = ref(false)
const detail = ref<any>({})

const money = (value: any) => Number(value || 0).toFixed(2)

const statusClass = (status: any) => {
  const value = Number(status)
  if (value === 4) return 'success'
  if ([1, 2, 3].includes(value)) return 'warning'
  if ([5, 6].includes(value)) return 'muted'
  return 'primary'
}

const loadDetail = async () => {
  if (!id.value) return
  loading.value = true
  try {
    const res: any = await getConsignmentOrderDetail(id.value)
    if (res.code === 1) {
      detail.value = res.data || {}
      return
    }
    uni.showToast({ title: res.msg || '加载失败', icon: 'none' })
  } catch (error) {
    console.error('加载代卖详情失败', error)
    uni.showToast({ title: '加载失败', icon: 'none' })
  } finally {
    loading.value = false
  }
}

const goSourceOrder = () => {
  if (!detail.value?.source_order_id) return
  uni.navigateTo({ url: `/addon/hsx_recycle/pages/order/detail?id=${detail.value.source_order_id}` })
}

onLoad((options?: Record<string, any>) => {
  id.value = Number(options?.id || 0)
  loadDetail()
})
</script>

<style lang="scss" scoped>
.consignment-detail-page {
  min-height: 100vh;
  background: #f5f7fb;
  padding-bottom: 30rpx;
}

.loading-wrap,
.content-wrap {
  padding: 20rpx 24rpx;
}

.skeleton-card {
  height: 210rpx;
  margin-bottom: 20rpx;
  border-radius: 20rpx;
  background: linear-gradient(90deg, #eef2f7 25%, #f8fafc 37%, #eef2f7 63%);
  background-size: 400% 100%;
  animation: shimmer 1.4s ease infinite;
}

.skeleton-card.small {
  height: 150rpx;
}

@keyframes shimmer {
  0% { background-position: 100% 0; }
  100% { background-position: 0 0; }
}

.empty-wrap {
  padding-top: 220rpx;
}

.hero-card,
.section-card {
  margin-bottom: 20rpx;
  padding: 24rpx;
  border-radius: 20rpx;
  background: #fff;
  box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.hero-card {
  background: linear-gradient(135deg, #0f172a, #2563eb);
  color: #fff;
}

.hero-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20rpx;
}

.status-name {
  display: block;
  font-size: 36rpx;
  font-weight: 800;
}

.status-desc {
  display: block;
  margin-top: 10rpx;
  color: rgba(255, 255, 255, 0.82);
  font-size: 25rpx;
  line-height: 1.5;
}

.status-badge {
  flex-shrink: 0;
  padding: 8rpx 14rpx;
  border-radius: 999rpx;
  background: rgba(255, 255, 255, 0.16);
  color: #fff;
  font-size: 22rpx;
}

.hero-meta {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18rpx;
  margin-top: 28rpx;
}

.hero-meta view {
  min-width: 0;
}

.hero-meta text,
.hero-meta strong {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hero-meta text {
  color: rgba(255, 255, 255, 0.66);
  font-size: 22rpx;
}

.hero-meta strong {
  margin-top: 6rpx;
  font-size: 24rpx;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 10rpx;
  margin-bottom: 20rpx;
  color: #111827;
  font-size: 29rpx;
  font-weight: 700;
}

.bar {
  width: 8rpx;
  height: 30rpx;
  border-radius: 999rpx;
}

.bar.blue { background: #2563eb; }
.bar.green { background: #059669; }
.bar.orange { background: #f97316; }
.bar.slate { background: #475569; }

.device-box {
  display: flex;
  align-items: center;
  gap: 18rpx;
  padding: 18rpx;
  border-radius: 16rpx;
  background: #f8fafc;
}

.device-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 68rpx;
  height: 68rpx;
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
  font-size: 28rpx;
  font-weight: 700;
}

.device-imei {
  margin-top: 6rpx;
  color: #64748b;
  font-size: 23rpx;
}

.info-list,
.amount-list {
  display: grid;
  gap: 18rpx;
}

.device-box + .info-list {
  margin-top: 20rpx;
}

.info-list view,
.amount-list view {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20rpx;
  min-width: 0;
}

.info-list text,
.amount-list text {
  flex-shrink: 0;
  color: #64748b;
  font-size: 25rpx;
}

.info-list strong,
.amount-list strong,
.link-text {
  min-width: 0;
  color: #111827;
  font-size: 25rpx;
  font-weight: 600;
  text-align: right;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.amount-list .green {
  color: #059669;
}

.link-text {
  color: #2563eb;
}

.timeline {
  display: grid;
  gap: 18rpx;
}

.timeline-item {
  position: relative;
  display: flex;
  gap: 16rpx;
}

.timeline-item:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 9rpx;
  top: 24rpx;
  bottom: -18rpx;
  width: 2rpx;
  background: #e2e8f0;
}

.dot {
  position: relative;
  z-index: 1;
  flex-shrink: 0;
  width: 20rpx;
  height: 20rpx;
  margin-top: 7rpx;
  border-radius: 50%;
  background: #2563eb;
}

.timeline-main {
  min-width: 0;
  flex: 1;
}

.timeline-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12rpx;
}

.timeline-head text {
  color: #111827;
  font-size: 25rpx;
  font-weight: 700;
}

.timeline-head strong {
  flex-shrink: 0;
  color: #94a3b8;
  font-size: 21rpx;
  font-weight: 400;
}

.timeline-desc,
.empty-log {
  margin-top: 6rpx;
  color: #64748b;
  font-size: 23rpx;
  line-height: 1.5;
}
</style>
