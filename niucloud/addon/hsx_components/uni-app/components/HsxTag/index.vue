<script lang="ts">export default { name: 'HsxTag' }</script>
<script setup lang="ts">
withDefaults(defineProps<{ text?: string | number, tone?: 'neutral' | 'primary' | 'info' | 'success' | 'warning' | 'danger', size?: 'small' | 'default' | 'large', round?: boolean, dot?: boolean }>(), { text: '', tone: 'neutral', size: 'default', round: true, dot: false })
const emit = defineEmits<{ (event: 'click'): void }>()
</script>
<template><view class="hsx-mobile-tag" :class="[`hsx-mobile-tag--${tone}`, `hsx-mobile-tag--${size}`, { 'hsx-mobile-tag--round': round }]" @click="emit('click')"><view v-if="dot" class="hsx-mobile-tag__dot" /><text><slot>{{ text }}</slot></text></view></template>
<style scoped lang="scss">
.hsx-mobile-tag { display: inline-flex; min-height: 24px; box-sizing: border-box; align-items: center; gap: 5px; padding: 2px 8px; border: 1px solid var(--hsx-mobile-border); border-radius: 6px; color: var(--hsx-mobile-text-regular); background: var(--hsx-mobile-bg-muted); font-size: var(--hsx-mobile-font-caption, 12px); line-height: 18px; }.hsx-mobile-tag--round { border-radius: 999px; }.hsx-mobile-tag--small { min-height: 20px; padding: 1px 6px; font-size: 11px; }.hsx-mobile-tag--large { min-height: 30px; padding: 4px 10px; font-size: var(--hsx-mobile-font-body, 14px); }
.hsx-mobile-tag--primary { color: var(--hsx-mobile-primary); background: rgba(37, 99, 235, 0.1); }.hsx-mobile-tag--success { color: var(--hsx-mobile-success); }.hsx-mobile-tag--warning { color: var(--hsx-mobile-warning); }.hsx-mobile-tag--danger { color: var(--hsx-mobile-danger); }.hsx-mobile-tag--info { color: var(--hsx-mobile-text-secondary); }.hsx-mobile-tag__dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
</style>
