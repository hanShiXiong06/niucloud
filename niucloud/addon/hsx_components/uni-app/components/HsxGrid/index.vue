<script lang="ts">export default { name: 'HsxGrid' }</script>
<script setup lang="ts">
import { computed } from 'vue'
const props = withDefaults(defineProps<{ columns?: number, gap?: number, rowGap?: number }>(), { columns: 2, gap: 20 })
const style = computed(() => ({ gridTemplateColumns: `repeat(${Math.max(1, props.columns)}, minmax(0, 1fr))`, columnGap: `${props.gap}rpx`, rowGap: `${props.rowGap ?? props.gap}rpx` }))
</script>
<template><view class="hsx-mobile-grid" :style="style"><slot /></view></template>
<style scoped>.hsx-mobile-grid { display: grid; min-width: 0; box-sizing: border-box; }</style>

