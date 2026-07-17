<template>
  <view class="order-detail-card">
    <RecycleSectionHeader title="订单信息" description="订单、设备与结算概览" />
    <view class="order-detail-card__body">
      <view class="order-detail-row">
        <text class="order-detail-row__label">订单号</text>
        <view class="order-detail-row__value-wrap">
          <text class="order-detail-row__value order-detail-row__value--number">{{ orderNo }}</text>
          <view class="order-detail-row__copy" @tap="handleCopyOrderNo">
            <up-icon name="file-text" size="12" color="#8b96a9" />
          </view>
        </view>
      </view>
      <view v-if="deviceCount > 0" class="order-detail-row">
        <text class="order-detail-row__label">设备数量</text>
        <text class="order-detail-row__value">{{ deviceCount }} 台</text>
      </view>
      <view v-if="expressNo" class="order-detail-row">
        <text class="order-detail-row__label">物流单号</text>
        <view class="order-detail-row__value-wrap" @tap="handleShowExpressTracking">
          <text class="order-detail-row__value order-detail-row__value--link">{{ expressNo }}</text>
          <up-icon name="arrow-right" size="12" color="var(--recycle-brand)" />
        </view>
      </view>
      <view class="order-detail-total">
        <view>
          <text class="order-detail-total__label">回收总价</text>
          <text class="order-detail-total__tip">以最终确认价格为准</text>
        </view>
        <text class="order-detail-total__value">¥{{ totalPrice }}</text>
      </view>
    </view>

    <!-- 物流跟踪弹窗 -->
    <ExpressTrackingModal
      v-model:visible="showExpressModal"
      :expressNo="expressNo || ''"
      :mobile="mobile"
    />
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { copyOrderNo } from '../../../utils/clipboard'
import ExpressTrackingModal from './ExpressTrackingModal.vue'
import RecycleSectionHeader from '../../components/RecycleSectionHeader.vue'

interface Props {
  orderNo: string
  deviceCount: number
  totalPrice: string
  expressNo?: string
  mobile?: string
}

const props = defineProps<Props>()

const showExpressModal = ref(false)

const handleCopyOrderNo = () => copyOrderNo(props.orderNo)

const handleShowExpressTracking = () => {
  showExpressModal.value = true
}
</script>

<style scoped lang="scss">
.order-detail-card {
  margin: 0 24rpx 18rpx;
  padding: 24rpx;
  border: 1rpx solid #e9edf2;
  border-radius: 24rpx;
  background: #fff;
  box-shadow: 0 8rpx 24rpx rgba(31, 41, 55, 0.045);
}

.order-detail-card__body {
  margin-top: 18rpx;
}

.order-detail-row {
  min-height: 66rpx;
  border-top: 1rpx solid #f0f2f5;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24rpx;
}

.order-detail-row__label {
  flex-shrink: 0;
  color: #8b96a9;
  font-size: 22rpx;
}

.order-detail-row__value-wrap {
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10rpx;
}

.order-detail-row__value {
  min-width: 0;
  color: #4d596c;
  font-size: 23rpx;
  line-height: 32rpx;
  font-weight: 550;
}

.order-detail-row__value--number {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.order-detail-row__value--link {
  color: var(--recycle-brand);
}

.order-detail-row__copy {
  width: 36rpx;
  height: 36rpx;
  border-radius: 9rpx;
  background: #f2f4f7;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.order-detail-total {
  margin-top: 10rpx;
  padding: 20rpx;
  border-radius: 16rpx;
  background: #f7f9fc;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20rpx;
}

.order-detail-total__label {
  display: block;
  color: #4d596c;
  font-size: 23rpx;
  line-height: 32rpx;
  font-weight: 600;
}

.order-detail-total__tip {
  display: block;
  margin-top: 3rpx;
  color: #a1a9b6;
  font-size: 19rpx;
  line-height: 28rpx;
}

.order-detail-total__value {
  color: var(--recycle-price);
  font-size: 34rpx;
  line-height: 44rpx;
  font-weight: 750;
  white-space: nowrap;
}
</style>
