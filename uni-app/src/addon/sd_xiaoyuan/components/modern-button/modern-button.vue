<template>
  <button
    class="modern-button"
    :class="[`button-${type}`, `button-${size}`, { 'button-loading': loading, 'button-disabled': disabled }]"
    :disabled="disabled || loading"
    :hover-class="disabled || loading ? 'none' : 'button-active'"
    @click="handleClick"
  >
    <view v-if="loading" class="button-loading-icon">⏳</view>
    <slot></slot>
  </button>
</template>

<script setup lang="ts">
interface Props {
  type?: 'primary' | 'default' | 'plain'
  size?: 'large' | 'medium' | 'small'
  loading?: boolean
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  type: 'primary',
  size: 'large',
  loading: false,
  disabled: false
})

const emit = defineEmits<{
  click: []
}>()

const handleClick = () => {
  if (!props.loading && !props.disabled) {
    emit('click')
  }
}
</script>

<style lang="scss" scoped>
.modern-button {
  width: 100%;
  border-radius: 44rpx;
  border: none;
  font-weight: 600;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;

  // 类型样式
  &.button-primary {
    // 使用后台配置的主题色
    background: var(--primary-gradient, linear-gradient(135deg, #1890ff 0%, #096dd9 100%));
  }

  &.button-default {
    background: #fff;
    color: #333;
    border: 2rpx solid #e5e5e5;
  }

  &.button-plain {
    background: transparent;
    color: var(--primary-color, #1890ff);
    border: 2rpx solid var(--primary-color, #1890ff);
  }

  // 尺寸样式
  &.button-large {
    height: 88rpx;
    font-size: 32rpx;
  }

  &.button-medium {
    height: 72rpx;
    font-size: 28rpx;
  }

  &.button-small {
    height: 60rpx;
    font-size: 26rpx;
  }

  // 状态样式
  &.button-active {
    transform: scale(0.98);
    opacity: 0.9;
  }

  &.button-disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  &.button-loading {
    opacity: 0.8;
  }
}

.button-loading-icon {
  margin-right: 16rpx;
  animation: rotate 1s linear infinite;
}

@keyframes rotate {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
