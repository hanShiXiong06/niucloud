<template>
  <view class="order-ui-button" :class="{ 'order-ui-button--block': block }">
    <up-button
      shape="square"
      :size="block ? 'normal' : 'small'"
      :customStyle="buttonStyle"
      :icon="icon"
      :iconColor="foreground"
      :openType="openType"
      :loading="loading"
      :disabled="disabled || loading"
      :loadingText="loadingText"
      :throttleTime="500"
      @click="handleClick"
    ><slot /></up-button>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'danger' | 'text'
  icon?: string
  openType?: string
  loading?: boolean
  disabled?: boolean
  loadingText?: string
  block?: boolean
}>(), { variant: 'secondary', icon: '', openType: '', loading: false, disabled: false, loadingText: '处理中', block: false })
const emit = defineEmits<{ click: [] }>()
const foreground = computed(() => props.variant === 'primary' ? 'var(--recycle-button-text)' : props.variant === 'danger' ? '#dc2626' : props.variant === 'text' ? 'var(--recycle-brand)' : 'var(--recycle-text-main)')
const buttonStyle = computed(() => ({
  width: '100%', margin: '0', minHeight: props.block ? '88rpx' : '68rpx', height: 'auto',
  padding: props.block ? '20rpx 24rpx' : '14rpx 22rpx', borderRadius: '12rpx',
  fontSize: props.block ? '28rpx' : '25rpx', lineHeight: '36rpx', fontWeight: '500',
  color: foreground.value,
  background: props.variant === 'primary' ? 'var(--recycle-button-bg)' : props.variant === 'text' ? 'transparent' : 'var(--recycle-bg-card)',
  border: `1rpx solid ${props.variant === 'primary' ? 'var(--recycle-button-bg)' : props.variant === 'text' ? 'transparent' : 'var(--recycle-line)'}`
}))
const handleClick = () => { if (!props.disabled && !props.loading) emit('click') }
</script>

<style scoped lang="scss">
.order-ui-button { display: inline-flex; min-width: 0; flex-shrink: 0; }
.order-ui-button--block { display: flex; width: 100%; }
.order-ui-button :deep(.u-button__text) { white-space: normal; line-height: 1.4; }
</style>
