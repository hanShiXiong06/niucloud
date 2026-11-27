<template>
  <view class="recycle-section-header">
    <view class="recycle-section-header__copy">
      <view class="recycle-section-header__title-row">
        <text class="recycle-section-header__title">{{ title }}</text>
        <text v-if="count !== undefined" class="recycle-section-header__count">{{ count }}</text>
      </view>
      <text v-if="description" class="recycle-section-header__description">{{ description }}</text>
    </view>
    <view v-if="$slots.action" class="recycle-section-header__action">
      <slot name="action" />
    </view>
  </view>
</template>

<script setup lang="ts">
defineProps<{
  title: string
  description?: string
  count?: string | number
}>()
</script>

<style scoped lang="scss">
.recycle-section-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20rpx;
}

.recycle-section-header__copy {
  min-width: 0;
  flex: 1;
}

.recycle-section-header__title-row {
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.recycle-section-header__title {
  color: #172033;
  font-size: 30rpx;
  line-height: 42rpx;
  font-weight: 700;
}

.recycle-section-header__count {
  min-width: 32rpx;
  height: 32rpx;
  padding: 0 10rpx;
  border-radius: 999rpx;
  color: var(--recycle-brand);
  background: rgba(59, 130, 246, 0.1);
  font-size: 20rpx;
  line-height: 32rpx;
  text-align: center;
  box-sizing: border-box;
}

.recycle-section-header__description {
  display: block;
  margin-top: 6rpx;
  color: #8b96a9;
  font-size: 22rpx;
  line-height: 32rpx;
}

.recycle-section-header__action {
  flex-shrink: 0;
}
</style>
