<template>
  <view class="report-header">
    <view>
      <text class="eyebrow">{{ brandName }}</text>
      <text class="title">{{ report.title }}</text>
      <text class="subtitle" v-if="report.subtitle">{{ report.subtitle }}</text>
    </view>
    <view class="badge">已生成</view>
  </view>
</template>

<script setup lang="ts">
defineProps<{
  brandName: string
  report: any
}>()
</script>

<style lang="scss" scoped>
.report-header {
  display: flex;
  justify-content: space-between;
  gap: 24rpx;
  padding: 34rpx 30rpx 26rpx;
  background: #111827;
  color: #fff;
}

.eyebrow {
  display: block;
  font-size: 22rpx;
  color: rgba(255, 255, 255, 0.66);
  margin-bottom: 12rpx;
}

.title {
  display: block;
  font-size: 38rpx;
  line-height: 48rpx;
  font-weight: 700;
}

.subtitle {
  display: block;
  margin-top: 10rpx;
  font-size: 24rpx;
  color: rgba(255, 255, 255, 0.72);
  line-height: 34rpx;
}

.badge {
  flex: 0 0 auto;
  align-self: flex-start;
  padding: 8rpx 14rpx;
  border: 1rpx solid rgba(255, 255, 255, 0.22);
  border-radius: 8rpx;
  font-size: 22rpx;
  color: rgba(255, 255, 255, 0.82);
}
</style>
