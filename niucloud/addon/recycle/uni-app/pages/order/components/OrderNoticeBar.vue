<template>
  <view v-if="visible" class="order-notice">
    <view class="notice-icon">
      <up-icon name="bell" size="14" color="var(--recycle-notice-text)"></up-icon>
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
  background: var(--recycle-notice-bg);
  border: 1px solid rgba(245, 158, 11, 0.24);
  border-radius: 16rpx;
}

.notice-icon {
  flex-shrink: 0;
  width: 40rpx;
  height: 40rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(245, 158, 11, 0.12);
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
  color: var(--recycle-notice-text);
  line-height: 1.4;
}

.notice-text {
  display: block;
  margin-top: 6rpx;
  font-size: 12px;
  color: var(--recycle-text-main);
  line-height: 1.55;
  white-space: pre-wrap;
}
</style>
