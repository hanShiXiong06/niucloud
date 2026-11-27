<template>
  <view class="poster-mask" v-if="visible" @tap="$emit('close')">
    <view class="poster-panel" @tap.stop>
      <view class="panel-head">
        <text class="title">分享图</text>
        <text class="close" @tap="$emit('close')">关闭</text>
      </view>
      <view class="poster-card">
        <text class="poster-brand">{{ config?.title || '设备查询报告' }}</text>
        <text class="poster-title">{{ report.title }}</text>
        <text class="poster-code">{{ report.displayCode }}</text>
        <image class="poster-image" v-if="report.image" :src="report.image" mode="aspectFit" />
        <view class="poster-list">
          <view class="poster-row" v-for="item in report.summary" :key="item.label">
            <text>{{ item.label }}</text>
            <text>{{ item.value }}</text>
          </view>
        </view>
        <text class="poster-footer">{{ config?.footer || '报告由系统自动生成' }}</text>
      </view>
      <button class="save-btn" @tap="$emit('save')">保存图片</button>
    </view>
  </view>
</template>

<script setup lang="ts">
defineProps<{
  visible: boolean
  report: any
  config: any
}>()

defineEmits(['close', 'save'])
</script>

<style lang="scss" scoped>
.poster-mask {
  position: fixed;
  inset: 0;
  z-index: 20;
  background: rgba(15, 23, 42, 0.56);
  display: flex;
  align-items: flex-end;
}

.poster-panel {
  width: 100%;
  padding: 24rpx;
  padding-bottom: calc(24rpx + env(safe-area-inset-bottom));
  background: #fff;
  border-radius: 18rpx 18rpx 0 0;
}

.panel-head {
  display: flex;
  justify-content: space-between;
  margin-bottom: 20rpx;
}

.title {
  font-size: 30rpx;
  font-weight: 700;
  color: #111827;
}

.close {
  font-size: 26rpx;
  color: #667085;
}

.poster-card {
  padding: 34rpx;
  border-radius: 14rpx;
  background: #111827;
  color: #fff;
}

.poster-brand,
.poster-footer {
  display: block;
  color: rgba(255, 255, 255, 0.68);
  font-size: 24rpx;
}

.poster-title {
  display: block;
  margin-top: 14rpx;
  font-size: 38rpx;
  font-weight: 700;
  line-height: 48rpx;
}

.poster-code {
  display: block;
  margin-top: 10rpx;
  font-family: monospace;
  color: rgba(255, 255, 255, 0.82);
  font-size: 26rpx;
}

.poster-image {
  width: 100%;
  height: 220rpx;
  margin: 24rpx 0;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 10rpx;
}

.poster-list {
  margin-top: 20rpx;
}

.poster-row {
  display: flex;
  justify-content: space-between;
  gap: 20rpx;
  padding: 12rpx 0;
  font-size: 24rpx;
  border-top: 1rpx solid rgba(255, 255, 255, 0.12);
}

.poster-footer {
  margin-top: 24rpx;
}

.save-btn {
  height: 82rpx;
  line-height: 82rpx;
  margin-top: 22rpx;
  border-radius: 8rpx;
  color: #fff;
  background: #111827;
  font-size: 28rpx;
  font-weight: 600;
}
</style>
