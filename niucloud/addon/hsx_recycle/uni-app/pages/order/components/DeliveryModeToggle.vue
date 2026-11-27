<template>
  <view class="nav-header">
    <view class="tab-list">
      <view
        v-for="tab in tabs"
        :key="tab.value"
        :class="['tab-item', modelValue === tab.value ? 'active' : '']"
        role="tab"
        :aria-selected="modelValue === tab.value"
        @tap="handleSwitch(tab.value)"
      >
        <DeliveryIcon :type="tab.value + 1" />
        <text>{{ tab.label }}</text>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import DeliveryIcon from './DeliveryIcon.vue'
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
  background: var(--recycle-bg-card);
}

.tab-item {
  flex: 1;
  min-width: 0;
  position: relative;
  min-height: 104rpx;
  padding: 20rpx 8rpx 26rpx;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10rpx;
  text-align: center;
  font-size: 28rpx;
  line-height: 38rpx;
  color: var(--recycle-text-sub);
  transition: color .15s;

  &.active {
    color: var(--recycle-brand);
    font-weight: 600;
  }

  &.active::after {
    content: '';
    position: absolute;
    width: 40rpx;
    height: 6rpx;
    border-radius: 3rpx;
    bottom: 10rpx;
    left: calc(50% - 20rpx);
    background: var(--recycle-brand);
  }

  &:active {
    opacity: 0.8;
  }
}
.tab-list { display: flex; width: 100%; gap: 6rpx; }
</style>
