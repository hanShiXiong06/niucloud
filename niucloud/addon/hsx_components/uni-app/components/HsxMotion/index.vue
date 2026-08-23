<script lang="ts">export default { name: 'HsxMotion' }</script>
<script setup lang="ts">
import { computed } from 'vue'
const props = withDefaults(defineProps<{ preset?: 'fade' | 'fade-up' | 'scale' | 'slide-left' | 'slide-right', duration?: number, delay?: number }>(), { preset: 'fade-up', duration: 200, delay: 0 })
const style = computed(() => ({ animationDuration: `${props.duration}ms`, animationDelay: `${props.delay}ms` }))
</script>
<template><view class="hsx-mobile-motion" :class="`hsx-mobile-motion--${preset}`" :style="style"><slot /></view></template>
<style scoped lang="scss">
.hsx-mobile-motion { animation-fill-mode: both; animation-timing-function: var(--hsx-mobile-ease); }
.hsx-mobile-motion--fade { animation-name: hsx-mobile-fade; } .hsx-mobile-motion--fade-up { animation-name: hsx-mobile-fade-up; } .hsx-mobile-motion--scale { animation-name: hsx-mobile-scale; } .hsx-mobile-motion--slide-left { animation-name: hsx-mobile-slide-left; } .hsx-mobile-motion--slide-right { animation-name: hsx-mobile-slide-right; }
@keyframes hsx-mobile-fade { from { opacity: 0; } } @keyframes hsx-mobile-fade-up { from { opacity: 0; transform: translateY(16rpx); } } @keyframes hsx-mobile-scale { from { opacity: 0; transform: scale(.97); } } @keyframes hsx-mobile-slide-left { from { opacity: 0; transform: translateX(-20rpx); } } @keyframes hsx-mobile-slide-right { from { opacity: 0; transform: translateX(20rpx); } }
</style>

