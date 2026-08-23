<script lang="ts">
export default { name: 'HsxOverflow' }
</script>

<script setup lang="ts">
import { computed } from 'vue'
const props = withDefaults(defineProps<{
    direction?: 'x' | 'y' | 'both'
    maxHeight?: string | number
    maxWidth?: string | number
    fade?: boolean
    scrollbar?: 'auto' | 'always' | 'hidden'
    keyboard?: boolean
}>(), { direction: 'x', fade: true, scrollbar: 'auto', keyboard: true })
function size(value?: string | number) { return typeof value === 'number' ? `${value}px` : value }
const rootStyle = computed(() => ({ maxHeight: size(props.maxHeight), maxWidth: size(props.maxWidth) }))
</script>

<template><div class="hsx-overflow" :class="[`hsx-overflow--${direction}`, `hsx-overflow--scrollbar-${scrollbar}`, { 'hsx-overflow--fade': fade }]" :style="rootStyle" :tabindex="keyboard ? 0 : undefined"><div class="hsx-overflow__content"><slot /></div></div></template>

<style scoped>
.hsx-overflow { position: relative; min-width: 0; overscroll-behavior: contain; scrollbar-color: var(--hsx-border-strong) transparent; scrollbar-width: thin; }
.hsx-overflow--x { overflow-x: auto; overflow-y: hidden; }
.hsx-overflow--y { overflow-x: hidden; overflow-y: auto; }
.hsx-overflow--both { overflow: auto; }
.hsx-overflow--scrollbar-hidden { scrollbar-width: none; }
.hsx-overflow--scrollbar-hidden::-webkit-scrollbar { display: none; }
.hsx-overflow--scrollbar-always::-webkit-scrollbar { width: 8px; height: 8px; }
.hsx-overflow--scrollbar-always::-webkit-scrollbar-thumb { border-radius: 999px; background: var(--hsx-border-strong); }
.hsx-overflow--fade { mask-image: linear-gradient(90deg, transparent 0, #000 14px, #000 calc(100% - 14px), transparent 100%); }
.hsx-overflow--y.hsx-overflow--fade { mask-image: linear-gradient(180deg, transparent 0, #000 14px, #000 calc(100% - 14px), transparent 100%); }
.hsx-overflow:focus-visible { border-radius: var(--hsx-radius-sm); outline: 2px solid var(--hsx-color-primary); outline-offset: 2px; }
.hsx-overflow__content { min-width: max-content; }
.hsx-overflow--y .hsx-overflow__content { min-width: 0; }
</style>

