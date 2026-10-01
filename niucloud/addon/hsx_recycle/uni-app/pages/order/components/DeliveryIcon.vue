<template>
  <text class="nc-iconfont delivery-icon" :class="icon" aria-hidden="true" />
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ type: number | string }>()
const icons: Record<number, string> = { 1: 'nc-icon-daifahuoV6xx', 2: 'nc-icon-dianpuV6xx', 3: 'nc-icon-daishouhuoV6xx' }
const icon = computed(() => icons[Number(props.type)] || 'nc-icon-daifahuoV6xx')
</script>
<style scoped>
.delivery-icon { display: inline-block; flex-shrink: 0; width: 40rpx; height: 40rpx; font-size: 36rpx; line-height: 40rpx; text-align: center; }
</style>
