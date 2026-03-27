<template>
  <view class="modern-empty">
    <view class="empty-animation">
      <image v-if="icon" :src="icon" class="empty-icon" mode="aspectFit"></image>
      <text v-else class="empty-emoji">{{ emoji }}</text>
    </view>
    <text class="empty-title">{{ title }}</text>
    <text v-if="desc" class="empty-desc">{{ desc }}</text>
    <slot name="action"></slot>
  </view>
</template>

<script setup lang="ts">
interface Props {
  icon?: string
  emoji?: string
  title?: string
  desc?: string
}

withDefaults(defineProps<Props>(), {
  icon: '',
  emoji: '📭',
  title: '暂无内容',
  desc: ''
})
</script>

<style lang="scss" scoped>
.modern-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 120rpx 60rpx;
}

.empty-animation {
  animation: float 3s ease-in-out infinite;
}

.empty-icon {
  width: 300rpx;
  height: 300rpx;
  opacity: 0.6;
}

.empty-emoji {
  font-size: 120rpx;
  opacity: 0.6;
}

.empty-title {
  font-size: 32rpx;
  font-weight: 600;
  color: #333;
  margin-top: 40rpx;
}

.empty-desc {
  font-size: 26rpx;
  color: #999;
  margin-top: 16rpx;
  text-align: center;
}

@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-20rpx); }
}
</style>
