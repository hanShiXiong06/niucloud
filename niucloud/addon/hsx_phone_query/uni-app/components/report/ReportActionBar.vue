<template>
  <view class="action-bar" v-if="shareConfig?.enabled">
    <button class="action-btn secondary" @tap="$emit('copy')">复制结果</button>
    <button class="action-btn primary" v-if="shareConfig?.poster_enabled" @tap="$emit('poster')">生成分享图</button>
  </view>
</template>

<script setup lang="ts">
defineProps<{
  shareConfig: any
}>()

defineEmits(['copy', 'poster'])
</script>

<style lang="scss" scoped>
.action-bar {
  position: sticky;
  bottom: 0;
  z-index: 8;
  display: flex;
  gap: 18rpx;
  padding: 18rpx 24rpx calc(18rpx + env(safe-area-inset-bottom));
  background: rgba(248, 250, 252, 0.94);
  backdrop-filter: blur(12px);
  border-top: 1rpx solid rgba(17, 24, 39, 0.08);
}

.action-btn {
  flex: 1;
  height: 82rpx;
  line-height: 82rpx;
  border-radius: 8rpx;
  font-size: 28rpx;
  font-weight: 600;
}

.primary {
  color: #fff;
  background: #111827;
}

.secondary {
  color: #111827;
  background: #fff;
  border: 1rpx solid #d0d5dd;
}
</style>
