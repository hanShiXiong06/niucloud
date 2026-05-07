<template>
  <view v-if="visible" class="order-notice">
    <view class="notice-icon">
      <up-icon name="bell" size="14" color="#8C7575"></up-icon>
    </view>
    <view class="notice-content">
      <text class="notice-title">{{ title }}</text>
      <text class="notice-text">{{ content }}</text>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  enabled: number
  title: string
  content: string
}>()

const visible = computed(() => !!props.enabled && !!String(props.content || '').trim())
</script>

<style scoped lang="scss">
.order-notice {
  display: flex;
  gap: 16rpx;
  padding: 20rpx;
  margin-bottom: 20rpx;
  background: #fff7ed;
  border: 1px solid #fed7aa;
  border-radius: 16rpx;
}

.notice-icon {
  flex-shrink: 0;
  width: 40rpx;
  height: 40rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ffedd5;
  border-radius: 50%;
}

.notice-content {
  flex: 1;
  min-width: 0;
}

.notice-title {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #7c2d12;
  line-height: 1.4;
}

.notice-text {
  display: block;
  margin-top: 6rpx;
  font-size: 12px;
  color: #9a3412;
  line-height: 1.55;
  white-space: pre-wrap;
}
</style>
