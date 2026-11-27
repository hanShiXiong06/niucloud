<script lang="ts">export default { name: 'HsxBadge', inheritAttrs: false }</script>
<script setup lang="ts">
withDefaults(defineProps<{
    value?: string | number
    max?: number
    dot?: boolean
    hidden?: boolean
    type?: 'primary' | 'success' | 'warning' | 'info' | 'danger'
    offset?: [number, number]
}>(), { max: 99, dot: false, hidden: false, type: 'danger' })
</script>
<template><el-badge v-bind="$attrs" class="hsx-badge" :value="value" :max="max" :is-dot="dot" :hidden="hidden" :type="type" :offset="offset"><slot /></el-badge></template>
<style scoped>.hsx-badge :deep(.el-badge__content) { font-weight: 650; box-shadow: 0 0 0 2px var(--hsx-bg-surface); }</style>
