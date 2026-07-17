<template>
  <view class="return-detail-page">
    <RecyclePageHeader title="退货详情" :subtitle="orderDetail.order_no || '查看退货处理结果'" />

    <view v-if="loading" class="return-detail-skeleton">
      <view class="skeleton-line return-detail-skeleton__hero" />
      <view class="return-detail-skeleton__card">
        <view class="skeleton-line return-detail-skeleton__title" />
        <view class="skeleton-line return-detail-skeleton__line" />
        <view class="skeleton-line return-detail-skeleton__line return-detail-skeleton__line--short" />
      </view>
    </view>

    <view v-else-if="orderDetail.id" class="return-detail-content">
      <view class="return-progress-card">
        <view class="return-progress-card__summary">
          <view class="return-progress-card__icon" :style="{ backgroundColor: statusInfo.bgColor }">
            <up-icon :name="statusIcon" size="21" :color="statusInfo.color" />
          </view>
          <view class="return-progress-card__copy">
            <view class="return-progress-card__title-row">
              <text class="return-progress-card__title">{{ statusInfo.text }}</text>
              <text class="return-progress-card__time">{{ orderDetail.create_at }}</text>
            </view>
            <text class="return-progress-card__description">{{ statusDesc }}</text>
          </view>
        </view>

        <view class="return-progress-card__steps">
          <view v-if="orderDetail.status === 3" class="return-progress-card__cancelled">
            <up-icon name="close-circle" size="17" color="#8b96a9" />
            <text>退货流程已取消</text>
          </view>
          <view v-else class="return-progress">
            <view class="return-progress__track" />
            <view
              class="return-progress__track return-progress__track--active"
              :style="{ backgroundColor: statusInfo.color, transform: `scaleX(${progressRatio})` }"
            />
            <view
              v-for="(step, index) in returnSteps"
              :key="step"
              class="return-progress__step"
              :class="{ 'return-progress__step--active': index <= currentStep }"
            >
              <view
                class="return-progress__node"
                :style="index <= currentStep ? { backgroundColor: statusInfo.color, borderColor: statusInfo.color } : {}"
              >
                <up-icon v-if="index < currentStep || (index === currentStep && isReturnCompleted)" name="checkmark" size="11" color="#fff" />
                <text v-else>{{ index + 1 }}</text>
              </view>
              <text class="return-progress__label">{{ step }}</text>
            </view>
          </view>
        </view>
      </view>

      <view class="return-detail-card">
        <RecycleSectionHeader title="退货信息" description="退货申请与处理时间" />
        <view class="return-detail-card__body">
          <view class="return-info-row">
            <text class="return-info-row__label">退货单号</text>
            <view class="return-info-row__value-group" @tap="handleCopyOrderNo">
              <text class="return-info-row__value return-info-row__value--strong">{{ orderDetail.order_no }}</text>
              <up-icon name="file-text" size="14" color="#8b96a9" />
            </view>
          </view>
          <view class="return-info-row">
            <text class="return-info-row__label">创建时间</text>
            <text class="return-info-row__value">{{ orderDetail.create_at }}</text>
          </view>
          <view v-if="orderDetail.over_at" class="return-info-row">
            <text class="return-info-row__label">完成时间</text>
            <text class="return-info-row__value">{{ orderDetail.over_at }}</text>
          </view>
          <view v-if="orderDetail.remark" class="return-info-row return-info-row--top">
            <text class="return-info-row__label">申请备注</text>
            <text class="return-info-row__value">{{ orderDetail.remark }}</text>
          </view>
          <view v-if="orderDetail.comment" class="return-info-row return-info-row--top">
            <text class="return-info-row__label">处理说明</text>
            <text class="return-info-row__value">{{ orderDetail.comment }}</text>
          </view>
        </view>
      </view>

      <view v-if="orderDetail.express_company || orderDetail.express_no || orderDetail.return_address" class="return-detail-card">
        <RecycleSectionHeader title="退回物流" description="设备寄回与收件信息">
          <template v-if="orderDetail.express_no" #action>
            <view class="return-detail-card__link" @tap="showExpressTracking = true">
              <text>物流轨迹</text>
              <up-icon name="arrow-right" size="14" color="var(--recycle-brand)" />
            </view>
          </template>
        </RecycleSectionHeader>
        <view class="return-detail-card__body">
          <view v-if="orderDetail.express_company" class="return-info-row">
            <text class="return-info-row__label">快递公司</text>
            <text class="return-info-row__value">{{ orderDetail.express_company }}</text>
          </view>
          <view v-if="orderDetail.express_no" class="return-info-row">
            <text class="return-info-row__label">快递单号</text>
            <view class="return-info-row__value-group" @tap="handleCopyExpressNo">
              <text class="return-info-row__value return-info-row__value--strong">{{ orderDetail.express_no }}</text>
              <up-icon name="file-text" size="14" color="#8b96a9" />
            </view>
          </view>
          <view v-if="orderDetail.return_address" class="return-info-row return-info-row--top">
            <text class="return-info-row__label">退回地址</text>
            <text class="return-info-row__value">{{ orderDetail.return_address }}</text>
          </view>
        </view>
      </view>

      <view class="return-device-section">
        <RecycleSectionHeader title="退回设备" description="核对型号、串号与处理状态" :count="deviceList.length" />

        <view v-if="deviceList.length" class="return-device-list">
          <view v-for="(item, index) in deviceList" :key="item.id || index" class="return-device-card">
            <view class="return-device-card__head">
              <view class="return-device-card__identity">
                <text class="return-device-card__index">{{ index + 1 }}</text>
                <text class="return-device-card__model">{{ item.device?.model || '未知设备' }}</text>
              </view>
              <text
                class="return-device-card__status"
                :style="{ color: getDeviceColor(item), backgroundColor: getDeviceBg(item) }"
              >
                {{ item.status_name || getDeviceText(item) }}
              </text>
            </view>

            <view v-if="item.device" class="return-device-card__body">
              <view class="return-device-card__imei" @tap="item.device.imei && copyIMEI(item.device.imei)">
                <text class="return-device-card__label">IMEI</text>
                <text class="return-device-card__value">{{ item.device.imei || '-' }}</text>
                <up-icon v-if="item.device.imei" name="file-text" size="13" color="#8b96a9" />
              </view>
              <view v-if="item.device.final_price" class="return-device-card__price">
                <text class="return-device-card__label">回收价格</text>
                <text class="return-device-card__price-value">¥{{ Number(item.device.final_price).toFixed(2) }}</text>
              </view>
            </view>
            <view v-else class="return-device-card__missing">设备信息不存在</view>

            <view v-if="item.remark" class="return-device-card__remark">
              <text>设备备注</text>
              <text>{{ item.remark }}</text>
            </view>
          </view>
        </view>

        <view v-else class="return-device-empty">
          <up-empty mode="data" text="暂无退回设备" textColor="#8b96a9" textSize="14" />
        </view>
      </view>
    </view>

    <view v-else class="return-detail-empty">
      <up-empty mode="data" text="退货订单不存在" textColor="#8b96a9" textSize="14" />
    </view>

    <ExpressTrackingModal
      :visible="showExpressTracking"
      :expressNo="orderDetail.express_no || ''"
      :mobile="orderDetail.member_mobile || ''"
      @update:visible="showExpressTracking = $event"
    />
  </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getReturnOrderDetail } from '../../api/return_order'
import { getReturnOrderStatusInfo, getDeviceStatusInfo } from '../../utils/theme'
import { copyOrderNo, copyExpressNo, copyIMEI } from '../../utils/clipboard'
import RecyclePageHeader from '../components/RecyclePageHeader.vue'
import RecycleSectionHeader from '../components/RecycleSectionHeader.vue'
import ExpressTrackingModal from '../order/components/ExpressTrackingModal.vue'

const loading = ref(true)
const orderDetail = ref<any>({})
const deviceList = ref<any[]>([])
const showExpressTracking = ref(false)

const returnSteps = ['待处理', '退货中', '已完成']
const statusInfo = computed(() => getReturnOrderStatusInfo(orderDetail.value.status))
const statusDesc = computed(() => {
  const map: Record<number, string> = {
    0: '商家正在处理您的退货申请',
    1: '退货已确认，请关注设备退回进度',
    2: '设备退回流程已经完成',
    3: '本次退货申请已经取消'
  }
  return map[orderDetail.value.status] || '暂无处理说明'
})
const statusIcon = computed(() => {
  if (orderDetail.value.status === 2) return 'checkmark-circle'
  if (orderDetail.value.status === 3) return 'close-circle'
  if (orderDetail.value.status === 1) return 'car'
  return 'clock'
})
const currentStep = computed(() => {
  if (orderDetail.value.status === 2) return 2
  if (orderDetail.value.status === 1) return 1
  return 0
})
const isReturnCompleted = computed(() => orderDetail.value.status === 2)
const progressRatio = computed(() => Math.min(currentStep.value / (returnSteps.length - 1), 1))

const getDeviceColor = (item: any) => getDeviceStatusInfo(item.device?.status ?? 0).color
const getDeviceBg = (item: any) => getDeviceStatusInfo(item.device?.status ?? 0).bgColor
const getDeviceText = (item: any) => getDeviceStatusInfo(item.device?.status ?? 0).text

const handleCopyOrderNo = () => copyOrderNo(orderDetail.value.order_no)
const handleCopyExpressNo = () => copyExpressNo(orderDetail.value.express_no)

const loadDetail = async (id: number | string) => {
  loading.value = true
  try {
    const res = await getReturnOrderDetail(Number(id))
    if (res.code === 1 && res.data) {
      orderDetail.value = res.data
      deviceList.value = res.data.return_devices || res.data.returnDevices || []
      return
    }
    orderDetail.value = {}
    deviceList.value = []
  } catch (error) {
    console.error('获取退货订单详情失败:', error)
    orderDetail.value = {}
    deviceList.value = []
  } finally {
    loading.value = false
  }
}

onLoad((options?: Record<string, any>) => {
  if (options?.id) loadDetail(options.id)
})
</script>

<style scoped lang="scss">
.return-detail-page {
  min-height: 100vh;
  padding-bottom: 34rpx;
  background: #f5f6f8;
}

.return-detail-content {
  padding-top: 1rpx;
}

.return-progress-card,
.return-detail-card {
  margin: 18rpx 24rpx;
  overflow: hidden;
  border: 1rpx solid #e8ecf1;
  border-radius: 24rpx;
  background: #fff;
  box-shadow: 0 8rpx 24rpx rgba(31, 41, 55, 0.045);
}

.return-progress-card__summary {
  padding: 26rpx 24rpx 22rpx;
  display: flex;
  align-items: center;
  gap: 18rpx;
}

.return-progress-card__icon {
  width: 70rpx;
  height: 70rpx;
  border-radius: 20rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.return-progress-card__copy {
  min-width: 0;
  flex: 1;
}

.return-progress-card__title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16rpx;
}

.return-progress-card__title {
  color: #172033;
  font-size: 32rpx;
  line-height: 44rpx;
  font-weight: 700;
}

.return-progress-card__time {
  color: #a0a8b5;
  font-size: 20rpx;
  line-height: 28rpx;
  white-space: nowrap;
}

.return-progress-card__description {
  display: block;
  margin-top: 5rpx;
  color: #7b8798;
  font-size: 22rpx;
  line-height: 32rpx;
}

.return-progress-card__steps {
  padding: 22rpx 28rpx 24rpx;
  border-top: 1rpx solid #edf0f4;
}

.return-progress-card__cancelled {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10rpx;
  color: #8b96a9;
  font-size: 23rpx;
}

.return-progress {
  position: relative;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.return-progress__track {
  position: absolute;
  top: 23rpx;
  left: 16.67%;
  right: 16.67%;
  height: 3rpx;
  background: #e8ecf1;
  transform-origin: left center;
}

.return-progress__track--active {
  z-index: 1;
}

.return-progress__step {
  position: relative;
  z-index: 2;
  width: 33.33%;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.return-progress__node {
  width: 46rpx;
  height: 46rpx;
  border: 3rpx solid #e8ecf1;
  border-radius: 50%;
  background: #fff;
  color: #a0a8b5;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  font-size: 20rpx;
  font-weight: 700;
}

.return-progress__step--active .return-progress__node {
  color: #fff;
}

.return-progress__label {
  margin-top: 10rpx;
  color: #a0a8b5;
  font-size: 21rpx;
  line-height: 30rpx;
}

.return-progress__step--active .return-progress__label {
  color: #455268;
  font-weight: 600;
}

.return-detail-card {
  padding: 24rpx;
  box-sizing: border-box;
}

.return-detail-card__body {
  margin-top: 18rpx;
  border-top: 1rpx solid #edf0f4;
}

.return-detail-card__link {
  display: flex;
  align-items: center;
  gap: 6rpx;
  color: var(--recycle-brand);
  font-size: 22rpx;
  font-weight: 650;
}

.return-info-row {
  min-height: 76rpx;
  padding: 16rpx 0;
  border-bottom: 1rpx solid #f0f2f5;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24rpx;
  box-sizing: border-box;
}

.return-info-row:last-child {
  border-bottom: 0;
}

.return-info-row--top {
  align-items: flex-start;
}

.return-info-row__label {
  flex-shrink: 0;
  color: #8b96a9;
  font-size: 23rpx;
  line-height: 34rpx;
}

.return-info-row__value,
.return-info-row__value-group {
  min-width: 0;
  color: #455268;
  font-size: 23rpx;
  line-height: 34rpx;
  text-align: right;
}

.return-info-row__value {
  word-break: break-all;
}

.return-info-row__value--strong {
  font-weight: 600;
}

.return-info-row__value-group {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8rpx;
}

.return-device-section {
  margin: 28rpx 24rpx 0;
}

.return-device-list {
  margin-top: 16rpx;
}

.return-device-card {
  margin-bottom: 16rpx;
  overflow: hidden;
  border: 1rpx solid #e8ecf1;
  border-radius: 22rpx;
  background: #fff;
  box-shadow: 0 8rpx 22rpx rgba(31, 41, 55, 0.04);
}

.return-device-card__head {
  padding: 22rpx 22rpx 16rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18rpx;
}

.return-device-card__identity {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.return-device-card__index {
  width: 34rpx;
  height: 34rpx;
  border-radius: 10rpx;
  background: #eff5ff;
  color: var(--recycle-brand);
  font-size: 20rpx;
  line-height: 34rpx;
  text-align: center;
  font-weight: 700;
  flex-shrink: 0;
}

.return-device-card__model {
  min-width: 0;
  color: #172033;
  font-size: 27rpx;
  line-height: 38rpx;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.return-device-card__status {
  flex-shrink: 0;
  padding: 6rpx 13rpx;
  border-radius: 999rpx;
  font-size: 20rpx;
  line-height: 28rpx;
  font-weight: 650;
}

.return-device-card__body {
  margin: 0 22rpx;
  padding: 18rpx;
  border-radius: 16rpx;
  background: #f6f8fa;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20rpx;
}

.return-device-card__imei,
.return-device-card__price {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 8rpx;
}

.return-device-card__label {
  color: #8b96a9;
  font-size: 21rpx;
}

.return-device-card__value {
  color: #455268;
  font-size: 22rpx;
  font-weight: 600;
}

.return-device-card__price-value {
  color: #e26016;
  font-size: 25rpx;
  font-weight: 700;
}

.return-device-card__missing {
  padding: 28rpx;
  color: #a0a8b5;
  font-size: 22rpx;
  text-align: center;
}

.return-device-card__remark {
  margin: 16rpx 22rpx 20rpx;
  padding-top: 15rpx;
  border-top: 1rpx solid #edf0f4;
  display: flex;
  justify-content: space-between;
  gap: 18rpx;
  color: #7b8798;
  font-size: 22rpx;
  line-height: 32rpx;
}

.return-device-empty {
  margin-top: 16rpx;
  padding: 50rpx 0;
  border-radius: 22rpx;
  background: #fff;
}

.return-detail-empty {
  padding-top: 240rpx;
}

.return-detail-skeleton {
  padding: 18rpx 24rpx;
}

.return-detail-skeleton__hero {
  height: 200rpx;
  border-radius: 24rpx;
}

.return-detail-skeleton__card {
  margin-top: 18rpx;
  padding: 28rpx 24rpx;
  border-radius: 24rpx;
  background: #fff;
}

.return-detail-skeleton__title {
  width: 32%;
  height: 30rpx;
}

.return-detail-skeleton__line {
  width: 100%;
  height: 24rpx;
  margin-top: 24rpx;
}

.return-detail-skeleton__line--short {
  width: 70%;
}

.skeleton-line {
  background: linear-gradient(90deg, #f0f2f5 25%, #e5e9ef 50%, #f0f2f5 75%);
  background-size: 200% 100%;
  animation: skeleton-loading 1.5s ease-in-out infinite;
}

@keyframes skeleton-loading {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
