<template>
  <view
    class="order-status-badge"
    :style="badgeStyle"
  >
    <text>{{ text }}</text>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  text: string
  color: string
  bgColor: string
}

const props = defineProps<Props>()

const badgeStyle = computed(() => `color:${props.color};background-color:${props.bgColor}`)
</script>

<style scoped lang="scss">
.order-status-badge {
  height: 42rpx;
  padding: 0 14rpx;
  border-radius: 999rpx;
  display: inline-flex;
  align-items: center;
  font-size: 21rpx;
  line-height: 42rpx;
  font-weight: 600;
  white-space: nowrap;
  box-sizing: border-box;
}
</style>
