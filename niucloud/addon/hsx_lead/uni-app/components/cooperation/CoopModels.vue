<template><DemandModels :items="items" :empty="empty" /></template>
<script setup lang="ts">
import DemandModels from './DemandModels.vue'
withDefaults(defineProps<{ items: Record<string, any>[]; empty?: string }>(), { empty: '未填写意向型号' })
</script>
