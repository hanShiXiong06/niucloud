<script setup lang="ts">
import { HsxTitle } from '@/addon/hsx_components/core'
defineProps<{ title: string; description?: string }>()
</script>
<template>
    <HsxTitle :title="title" :subtitle="description" size="page" collapsible-subtitle>
        <template v-if="$slots.actions" #extra><slot name="actions" /></template>
    </HsxTitle>
</template>
