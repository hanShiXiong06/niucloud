<template>
  <view class="nav-header">
    <view class="tab-list">
      <view
        v-for="(tab, index) in tabs"
        :key="tab.value"
        :class="['tab-item', modelValue === tab.value ? 'active' : '']"
        @tap="handleSwitch(tab.value)"
      >
        {{ tab.label }}
      </view>
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
}>()

const handleSwitch = (index: number) => {
  if (!props.tabs.some(tab => tab.value === index)) return

  emit('update:modelValue', index)
}

</script>

<style scoped lang="scss">
.nav-header {
  background: linear-gradient(100deg, var(--recycle-button-bg) 0%, var(--recycle-brand-deep) 58%, var(--recycle-brand) 100%);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  justify-content: center;
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
  flex: 1;
  min-width: 0;
  padding: 7px 10px;
  text-align: center;
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
.tab-list { position: relative; z-index: 1; display: flex; width: 100%; gap: 10px; }
</style>
