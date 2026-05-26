<template>
  <view
    class="inline-flex  items-center px-2 py-1 rounded text-[22rpx] font-medium"
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
// 使用 Windi CSS，无需额外样式
</style>
