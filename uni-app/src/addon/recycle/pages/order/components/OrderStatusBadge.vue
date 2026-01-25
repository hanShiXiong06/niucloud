<template>
  <view
    class="inline-flex  items-center px-2 py-1 rounded text-[22rpx] font-medium"
    :style="{
      color: color,
      backgroundColor: bgColor
    }"
  >
    <text>{{ text }}</text>
  </view>
</template>

<script setup lang="ts">
interface Props {
  text: string
  color: string
  bgColor: string
}

defineProps<Props>()
</script>

<style scoped lang="scss">
// 使用 Windi CSS，无需额外样式
</style>
