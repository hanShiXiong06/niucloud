<script lang="ts">export default { name: 'HsxOverflow' }</script>
<script setup lang="ts">
import { computed } from 'vue'
const props = withDefaults(defineProps<{ direction?: 'x' | 'y', height?: string | number, width?: string | number, showScrollbar?: boolean, enhanced?: boolean }>(), { direction: 'x', showScrollbar: false, enhanced: true })
function size(value?: string | number) { return typeof value === 'number' ? `${value}rpx` : value }
const style = computed(() => ({ height: size(props.height), width: size(props.width) }))
</script>
<template><scroll-view class="hsx-mobile-overflow" :scroll-x="direction === 'x'" :scroll-y="direction === 'y'" :show-scrollbar="showScrollbar" :enhanced="enhanced" :style="style"><view class="hsx-mobile-overflow__content" :class="`hsx-mobile-overflow__content--${direction}`"><slot /></view></scroll-view></template>
<style scoped>.hsx-mobile-overflow { max-width: 100%; overscroll-behavior: contain; } .hsx-mobile-overflow__content--x { display: inline-flex; min-width: 100%; } .hsx-mobile-overflow__content--y { min-height: 100%; }</style>

