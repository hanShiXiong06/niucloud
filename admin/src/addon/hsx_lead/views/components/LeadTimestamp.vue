<template>
    <el-tooltip v-if="parts" :content="parts.full" placement="top" :show-after="400">
        <time class="lead-timestamp" :class="{ 'lead-timestamp--compact': compact }" :datetime="parts.iso" :aria-label="parts.full"><span>{{ parts.date }}</span><small>{{ parts.clock }}</small></time>
    </el-tooltip>
    <span v-else class="lead-timestamp lead-timestamp--empty">{{ empty }}</span>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { timestampParts } from '../../composables/useLeadUi'
const props = withDefaults(defineProps<{ value?: string | number | null; empty?: string; compact?: boolean }>(), { empty: '尚未记录', compact: false })
const parts = computed(() => timestampParts(props.value))
</script>
<style scoped>
.lead-timestamp { display: inline-flex; flex-direction: column; gap: 3px; white-space: nowrap; font-variant-numeric: tabular-nums; font-size: 12px; line-height: 18px; color: var(--hsx-text-primary, #213047); }
.lead-timestamp small, .lead-timestamp--empty { color: var(--hsx-text-secondary, #788698); font-size: 12px; }
.lead-timestamp--compact { flex-direction: row; align-items: baseline; gap: 6px; }
</style>
