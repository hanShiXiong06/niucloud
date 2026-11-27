<template>
  <view class="shop-info-card">
    <view class="shop-info-head">
      <view class="shop-info-title">
        <DeliveryIcon :type="deliveryMode + 1" /><text>{{ deliveryMode === 0 ? '收件信息' : deliveryMode === 1 ? '到店信息' : '门店联系' }}</text>
      </view>
      <OrderUiButton v-if="deliveryMode !== 1" variant="text" @click="handleCopy">复制信息</OrderUiButton>
      <OrderUiButton v-else variant="text" icon="map" @click="handleOpenLocation">导航到店</OrderUiButton>
    </view>

    <view v-if="shopInfo" class="shop-info-body">
      <view class="shop-address-row">
        <text class="shop-address-text">{{ shopInfo.full_address || shopInfo.address || '门店暂未配置地址' }}</text>
        <view v-if="deliveryMode !== 1" class="shop-map-btn" @click="handleOpenLocation">
          <up-icon name="map" size="16" color="var(--recycle-brand)"></up-icon>
          <text>导航</text>
        </view>
      </view>
      <view class="shop-contact-row">
        <text class="shop-name">{{ shopInfo.name || '门店名称待补充' }}</text>
        <button v-if="shopInfo.mobile" class="shop-phone" @tap="callShop">
          <up-icon name="phone" size="15" color="var(--recycle-brand)" />
          <text>{{ shopInfo.mobile }}</text>
        </button>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import DeliveryIcon from './DeliveryIcon.vue'
import OrderUiButton from './OrderUiButton.vue'
import type { ShopInfo } from '../../../types/order'

interface Props {
  shopInfo: ShopInfo
  deliveryMode?: number
}

const props = withDefaults(defineProps<Props>(), { deliveryMode: 0 })
const callShop = () => { if (props.shopInfo.mobile) uni.makePhoneCall({ phoneNumber: props.shopInfo.mobile }) }

const emit = defineEmits<{
  copy: []
  'open-location': []
}>()

const handleCopy = () => {
  emit('copy')
}

const handleOpenLocation = () => {
  emit('open-location')
}
</script>

<style scoped lang="scss">
.shop-info-card {
  margin-top: var(--recycle-order-section-gap, 12px);
  padding: 20px var(--recycle-order-gutter, 24px);
  background: var(--recycle-bg-card);
}

.shop-info-head,
.shop-info-title,
.shop-address-row,
.shop-map-btn {
  display: flex;
  align-items: center;
}

.shop-info-head {
  justify-content: space-between;
  gap: 20rpx;
  min-height: 52rpx;
  margin-bottom: 12px;
}

.shop-info-title {
  gap: 8rpx;
  color: var(--recycle-text-main);
  font-size: 30rpx;
  line-height: 42rpx;
  font-weight: 600;
}

.shop-info-body {
  display: flex;
  flex-direction: column;
  gap: 4rpx;
}

.shop-address-row {
  align-items: flex-start;
  gap: 12rpx;
  padding: 10rpx 0;
}

.shop-address-text {
  flex: 1;
  min-width: 0;
  color: var(--recycle-text-main);
  font-size: 29rpx;
  font-weight: 500;
  line-height: 44rpx;
  overflow-wrap: anywhere;
}
.shop-contact-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8rpx 24rpx; font-size: 25rpx; line-height: 38rpx; color: var(--recycle-text-sub); }
.shop-name { overflow-wrap: anywhere; }
.shop-phone { display: flex; align-items: center; gap: 8rpx; margin: 0; padding: 8rpx 0; min-height: 64rpx; background: transparent; color: var(--recycle-brand); font-size: 25rpx; line-height: 38rpx; }
.shop-phone::after { border: 0; }

.shop-map-btn {
  flex-shrink: 0;
  align-self: center;
  gap: 6rpx;
  min-height: 52rpx;
  margin: -6rpx 0;
  padding: 0 4rpx 0 12rpx;
  color: var(--recycle-brand);
  font-size: 24rpx;
  font-weight: 500;
}
</style>
