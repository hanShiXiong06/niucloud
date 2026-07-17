<template>
  <view class="order-progress-card">
    <view class="order-progress-card__summary">
      <view class="order-progress-card__icon" :style="{ backgroundColor: statusBg }">
        <up-icon :name="statusIcon" size="21" :color="statusColor" />
      </view>
      <view class="order-progress-card__copy">
        <view class="order-progress-card__title-row">
          <text class="order-progress-card__title">{{ statusName }}</text>
          <text class="order-progress-card__time">{{ createTime }}</text>
        </view>
        <text class="order-progress-card__description">{{ statusDescription }}</text>
      </view>
    </view>

    <view class="order-progress-card__steps">
      <view v-if="isCancelled" class="order-progress-card__cancelled">
        <up-icon name="close-circle" size="17" color="#8b96a9" />
        <text>流程已结束，不再继续处理</text>
      </view>
      <view v-else class="order-progress">
        <view class="order-progress__track" />
        <view
          class="order-progress__track order-progress__track--active"
          :style="{ backgroundColor: statusColor, transform: `scaleX(${progressRatio})` }"
        />
        <view
          v-for="(step, index) in steps"
          :key="step.name"
          class="order-progress__step"
          :class="{ 'order-progress__step--active': index <= currentStep }"
        >
          <view
            class="order-progress__node"
            :style="index <= currentStep ? { backgroundColor: statusColor, borderColor: statusColor } : {}"
          >
            <up-icon v-if="index < currentStep || (index === currentStep && isCompleted)" name="checkmark" size="11" color="#fff" />
            <text v-else>{{ index + 1 }}</text>
          </view>
          <text class="order-progress__label">{{ step.name }}</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { getOrderStatusInfo } from '../../../utils/theme'

interface Props {
  status: number
  statusName: string
  createTime: string
}

const props = defineProps<Props>()

const steps = [
  { name: '已下单' },
  { name: '待质检' },
  { name: '待确认' },
  { name: '待打款' },
  { name: '已完成' }
]

const isCancelled = computed(() => props.status === 8 || props.status === 9)
const isCompleted = computed(() => props.status === 7)

const statusInfo = computed(() => getOrderStatusInfo(props.status))
const statusColor = computed(() => statusInfo.value.color)
const statusBg = computed(() => statusInfo.value.bgColor)
const statusIcon = computed(() => {
  if (isCancelled.value) return 'close-circle'
  if (isCompleted.value) return 'checkmark-circle'
  if ([2, 3].includes(props.status)) return 'search'
  if ([4, 5].includes(props.status)) return 'order'
  if (props.status === 6) return 'rmb-circle'
  return 'clock'
})

const currentStep = computed(() => {
  switch (props.status) {
    case 1: return 0
    case 2:
    case 3: return 1
    case 4:
    case 5: return 2
    case 6: return 3
    case 7: return 4  // 最后一个索引
    default: return 0
  }
})

// 进度比例 0~1，用于 scaleX
const progressRatio = computed(() => {
  const total = steps.length - 1
  return Math.min(currentStep.value / total, 1)
})

const statusDescription = computed(() => {
  const map: Record<number, string> = {
    1: '您的订单已提交，等待商家确认',
    2: '您的订单已签收，等待商家质检',
    3: '商家正在质检您的设备，请耐心等待',
    4: '设备已完成质检，等待您确认价格',
    5: '您已确认部分设备价格，等待确认剩余设备',
    6: '价格已确认，等待商家打款',
    7: '交易已完成，感谢您的使用',
    9: '订单已取消'
  }
  return map[props.status] || '订单状态未知'
})
</script>

<style scoped lang="scss">
.order-progress-card {
  margin: 18rpx 24rpx;
  overflow: hidden;
  border: 1rpx solid #e9edf2;
  border-radius: 24rpx;
  background: #fff;
  box-shadow: 0 8rpx 24rpx rgba(31, 41, 55, 0.045);
}

.order-progress-card__summary {
  padding: 26rpx 24rpx 22rpx;
  display: flex;
  align-items: center;
  gap: 18rpx;
}

.order-progress-card__icon {
  width: 70rpx;
  height: 70rpx;
  border-radius: 20rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.order-progress-card__copy {
  min-width: 0;
  flex: 1;
}

.order-progress-card__title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16rpx;
}

.order-progress-card__title {
  color: #172033;
  font-size: 32rpx;
  line-height: 44rpx;
  font-weight: 750;
}

.order-progress-card__time {
  color: #a1a9b6;
  font-size: 20rpx;
  line-height: 28rpx;
  white-space: nowrap;
}

.order-progress-card__description {
  display: block;
  margin-top: 5rpx;
  color: #7b8798;
  font-size: 22rpx;
  line-height: 32rpx;
}

.order-progress-card__steps {
  padding: 22rpx 18rpx 24rpx;
  border-top: 1rpx solid #edf0f4;
}

.order-progress-card__cancelled {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10rpx;
  color: #7b8798;
  font-size: 23rpx;
}

.order-progress {
  position: relative;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.order-progress__track {
  position: absolute;
  left: 10%;
  right: 10%;
  top: 20rpx;
  height: 4rpx;
  z-index: 0;
  background: #e8ecf1;
}

.order-progress__track--active {
  transform-origin: left center;
}

.order-progress__step {
  position: relative;
  z-index: 1;
  width: 20%;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.order-progress__node {
  width: 42rpx;
  height: 42rpx;
  border: 4rpx solid #e8ecf1;
  border-radius: 50%;
  background: #fff;
  color: #a2aab6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 19rpx;
  font-weight: 700;
  box-sizing: border-box;
}

.order-progress__label {
  margin-top: 9rpx;
  color: #a1a9b6;
  font-size: 19rpx;
  line-height: 28rpx;
  white-space: nowrap;
}

.order-progress__step--active .order-progress__label {
  color: #4f5c70;
  font-weight: 600;
}
</style>
