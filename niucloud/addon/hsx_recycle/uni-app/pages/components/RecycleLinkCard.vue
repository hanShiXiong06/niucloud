<template>
  <button
    v-if="button"
    class="recycle-link-card"
    :class="{ 'recycle-link-card--disabled': disabled }"
    :disabled="disabled"
    :open-type="openType"
    @tap="handleTap"
  >
    <view class="recycle-link-card__icon" :style="iconStyle">
      <up-icon :name="icon" :size="iconSize" :color="iconColor" />
    </view>
    <view class="recycle-link-card__copy">
      <view class="recycle-link-card__title-row">
        <text class="recycle-link-card__title">{{ title }}</text>
        <text v-if="badge" class="recycle-link-card__badge" :style="badgeStyle">{{ badge }}</text>
      </view>
      <text v-if="description" class="recycle-link-card__description">{{ description }}</text>
    </view>
    <text v-if="actionText" class="recycle-link-card__action" :style="actionStyle">{{ actionText }}</text>
    <up-icon v-else name="arrow-right" size="16" color="#c4cad4" />
  </button>
  <view
    v-else
    class="recycle-link-card"
    :class="{ 'recycle-link-card--disabled': disabled }"
    @tap="handleTap"
  >
    <view class="recycle-link-card__icon" :style="iconStyle">
      <up-icon :name="icon" :size="iconSize" :color="iconColor" />
    </view>
    <view class="recycle-link-card__copy">
      <view class="recycle-link-card__title-row">
        <text class="recycle-link-card__title">{{ title }}</text>
        <text v-if="badge" class="recycle-link-card__badge" :style="badgeStyle">{{ badge }}</text>
      </view>
      <text v-if="description" class="recycle-link-card__description">{{ description }}</text>
    </view>
    <text v-if="actionText" class="recycle-link-card__action" :style="actionStyle">{{ actionText }}</text>
    <up-icon v-else name="arrow-right" size="16" color="#c4cad4" />
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  title: string
  description?: string
  icon?: string
  iconColor?: string
  iconBackground?: string
  iconSize?: string | number
  badge?: string
  badgeColor?: string
  badgeBackground?: string
  actionText?: string
  actionColor?: string
  button?: boolean
  disabled?: boolean
  openType?: string
}>(), {
  description: '',
  icon: 'arrow-right',
  iconColor: '#3b82f6',
  iconBackground: '#eff6ff',
  iconSize: 18,
  badge: '',
  badgeColor: '#64748b',
  badgeBackground: '#f1f5f9',
  actionText: '',
  actionColor: '#3b82f6',
  button: false,
  disabled: false,
  openType: ''
})

const emit = defineEmits<{ tap: [] }>()

const iconStyle = computed(() => `color:${props.iconColor};background:${props.iconBackground};`)
const badgeStyle = computed(() => `color:${props.badgeColor};background:${props.badgeBackground};`)
const actionStyle = computed(() => `color:${props.actionColor};`)
const handleTap = () => {
  if (!props.disabled) emit('tap')
}
</script>

<style scoped lang="scss">
.recycle-link-card {
  width: 100%;
  min-height: 112rpx;
  margin: 0;
  padding: 22rpx 24rpx;
  border: 1rpx solid #edf0f4;
  border-radius: 20rpx;
  background: #fff;
  display: flex;
  align-items: center;
  gap: 18rpx;
  text-align: left;
  line-height: 1;
  box-sizing: border-box;
}

.recycle-link-card::after {
  border: 0;
}

.recycle-link-card:active {
  background: #f8fafc;
}

.recycle-link-card--disabled {
  opacity: 0.58;
}

.recycle-link-card__icon {
  width: 64rpx;
  height: 64rpx;
  border-radius: 18rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.recycle-link-card__copy {
  min-width: 0;
  flex: 1;
}

.recycle-link-card__title-row {
  display: flex;
  align-items: center;
  gap: 10rpx;
}

.recycle-link-card__title {
  color: #172033;
  font-size: 27rpx;
  line-height: 38rpx;
  font-weight: 650;
}

.recycle-link-card__badge {
  padding: 4rpx 10rpx;
  border-radius: 999rpx;
  font-size: 19rpx;
  line-height: 28rpx;
}

.recycle-link-card__description {
  display: block;
  margin-top: 5rpx;
  color: #8490a3;
  font-size: 22rpx;
  line-height: 32rpx;
}

.recycle-link-card__action {
  flex-shrink: 0;
  font-size: 23rpx;
  line-height: 32rpx;
  font-weight: 600;
}
</style>
