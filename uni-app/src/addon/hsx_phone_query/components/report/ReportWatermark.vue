<template>
  <view class="watermark-layer" v-if="config?.enabled">
    <view
      v-for="index in 18"
      :key="index"
      class="watermark-item"
      :style="itemStyle"
    >
      {{ config.text }}
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  config: any
}>()

const itemStyle = computed(() => ({
  color: props.config?.color || 'rgba(18, 24, 38, 0.06)',
  fontSize: `${props.config?.size || 88}rpx`,
  opacity: props.config?.opacity ?? 0.72,
  transform: `rotate(${props.config?.rotate ?? -24}deg)`,
}))
</script>

<style lang="scss" scoped>
.watermark-layer {
  pointer-events: none;
  position: fixed;
  inset: 0;
  z-index: 2;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  align-content: space-around;
  overflow: hidden;
}

.watermark-item {
  text-align: center;
  white-space: nowrap;
  font-weight: 600;
}
</style>
