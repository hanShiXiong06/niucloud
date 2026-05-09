<template>
  <view class="nav-header">
    <view class="flex gap-3 relative z-10">
      <view
        v-for="(tab, index) in tabs"
        :key="tab.value"
        :class="['tab-item', modelValue === tab.value ? 'active' : '']"
        @tap="handleSwitch(tab.value)"
      >
        {{ tab.label }}
      </view>
    </view>
    <view class="order-link" @tap="handleToOrderList">
      <up-icon color="#fff" name="list" size="18"></up-icon>
      <text class="ml-1">我的订单</text>
    </view>
  </view>
</template>

<script setup lang="ts">
interface DeliveryTab {
  label: string
  value: number
}

interface Props {
  modelValue: number
  tabs?: DeliveryTab[]
}

const props = withDefaults(defineProps<Props>(), {
  tabs: () => [
    { label: '邮寄到店', value: 0 },
    { label: '自送到店', value: 1 }
  ]
})

const emit = defineEmits<{
  'update:modelValue': [value: number]
  'to-order-list': []
}>()

const handleSwitch = (index: number) => {
  if (!props.tabs.some(tab => tab.value === index)) return

  emit('update:modelValue', index)
}

const handleToOrderList = () => {
  emit('to-order-list')
}
</script>

<style scoped lang="scss">
.nav-header {
  background: linear-gradient(100deg, var(--recycle-button-bg) 0%, var(--recycle-brand-deep) 58%, var(--recycle-brand) 100%);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: #fff;
  box-shadow: 0 10rpx 24rpx rgba(31, 41, 55, 0.12);
  position: relative;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg,
      rgba(255, 255, 255, 0.12) 0%,
      rgba(255, 255, 255, 0.06) 50%,
      rgba(255, 255, 255, 0) 100%);
    pointer-events: none;
  }
}

.tab-item {
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 14px;
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(4px);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.1);

  &.active {
    background: #fff;
    color: var(--recycle-brand-deep);
    font-weight: 500;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
    border-color: #fff;
  }

  &:active {
    opacity: 0.8;
  }
}

.order-link {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  padding: 6px 12px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(4px);
  transition: all 0.3s ease;
  position: relative;
  z-index: 1;
  border: 1px solid rgba(255, 255, 255, 0.1);

  &:active {
    transform: translateY(1px);
    background: rgba(255, 255, 255, 0.18);
  }

  text {
    font-weight: 500;
  }
}
</style>
