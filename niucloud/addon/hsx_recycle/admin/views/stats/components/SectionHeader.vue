<script setup lang="ts">
import { HsxTitle } from '@/addon/hsx_components/core'
defineProps<{ title: string; subtitle?: string; icon?: any; bgClass?: string; iconBgClass?: string; subtitleClass?: string }>()
</script>
<template>
    <HsxTitle :title="title" :subtitle="subtitle" size="card" collapsible-subtitle class="px-4 py-3">
        <template v-if="icon" #prefix><el-icon class="text-[var(--hsx-color-primary)]"><component :is="icon" /></el-icon></template>
        <template v-if="$slots.right" #extra><slot name="right" /></template>
    </HsxTitle>
</template>
