<template>
  <view class="shop-info-card">
    <view class="shop-info-head">
      <view class="shop-info-title">
        <up-icon name="bag" size="16" color="var(--recycle-brand)"></up-icon>
        <text>商家信息</text>
      </view>
      <view class="shop-copy-btn" @click="handleCopy">
        <up-icon size="14" name="cut" color="var(--recycle-brand)" />
        <text>一键复制</text>
      </view>
    </view>

    <view v-if="shopInfo" class="shop-info-body">
      <view class="shop-info-row">
        <text class="shop-info-label">商家名称</text>
        <text class="shop-info-value">{{ shopInfo.name || '未配置' }}</text>
      </view>
      <view v-if="shopInfo.mobile" class="shop-info-row">
        <text class="shop-info-label">联系电话</text>
        <text class="shop-info-value">{{ shopInfo.mobile }}</text>
      </view>
      <view class="shop-address-row">
        <view class="shop-address-main">
          <text class="shop-info-label">商家地址</text>
          <text class="shop-address-text">{{ shopInfo.full_address || shopInfo.address || '未配置' }}</text>
        </view>
        <view class="shop-map-btn" @click="handleOpenLocation">
          <up-icon name="map" size="18" color="var(--recycle-button-text)"></up-icon>
          <text>导航</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import type { ShopInfo } from '../../../types/order'

interface Props {
  shopInfo: ShopInfo
}

defineProps<Props>()

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
  margin-top: 20rpx;
  padding: 24rpx;
  border-radius: 16rpx;
  background: var(--recycle-bg-card);
  border: 1rpx solid var(--recycle-line);
  border-left: 6rpx solid var(--recycle-brand);
  box-shadow: 0 8rpx 20rpx rgba(31, 41, 55, 0.06);
}

.shop-info-head,
.shop-info-title,
.shop-copy-btn,
.shop-info-row,
.shop-address-row,
.shop-map-btn {
  display: flex;
  align-items: center;
}

.shop-info-head {
  justify-content: space-between;
  gap: 20rpx;
  margin-bottom: 18rpx;
}

.shop-info-title {
  gap: 8rpx;
  color: var(--recycle-brand);
  font-size: 28rpx;
  line-height: 38rpx;
  font-weight: 800;
}

.shop-copy-btn {
  flex-shrink: 0;
  gap: 8rpx;
  height: 52rpx;
  padding: 0 18rpx;
  border-radius: 26rpx;
  background: var(--recycle-bg-soft);
  color: var(--recycle-brand);
  font-size: 24rpx;
  font-weight: 700;
}

.shop-info-body {
  display: flex;
  flex-direction: column;
  gap: 14rpx;
}

.shop-info-row {
  justify-content: space-between;
  gap: 20rpx;
}

.shop-info-label {
  flex-shrink: 0;
  min-width: 128rpx;
  color: var(--recycle-text-sub);
  font-size: 25rpx;
  line-height: 36rpx;
}

.shop-info-value {
  flex: 1;
  min-width: 0;
  color: var(--recycle-text-main);
  font-size: 26rpx;
  line-height: 38rpx;
  text-align: right;
}

.shop-address-row {
  align-items: stretch;
  gap: 16rpx;
}

.shop-address-main {
  flex: 1;
  min-width: 0;
  display: flex;
  gap: 12rpx;
}

.shop-address-text {
  flex: 1;
  min-width: 0;
  color: var(--recycle-text-main);
  font-size: 24rpx;
  line-height: 36rpx;
}

.shop-map-btn {
  flex-shrink: 0;
  align-self: center;
  gap: 6rpx;
  height: 58rpx;
  padding: 0 18rpx;
  border-radius: 29rpx;
  background: var(--recycle-button-bg);
  color: var(--recycle-button-text);
  font-size: 24rpx;
  font-weight: 800;
}
</style>
