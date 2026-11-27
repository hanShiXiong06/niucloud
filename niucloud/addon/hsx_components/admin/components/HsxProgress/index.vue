<script lang="ts">
export default { name: 'HsxProgress', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, useAttrs } from 'vue'
type Tone = 'primary' | 'success' | 'warning' | 'danger'
const props = withDefaults(defineProps<{
    percentage?: number
    label?: string
    description?: string
    tone?: Tone
    autoTone?: boolean
    thresholds?: { warning?: number, success?: number }
    showValue?: boolean
}>(), {
    percentage: 0, label: '', description: '', tone: 'primary', autoTone: false,
    thresholds: () => ({ warning: 40, success: 80 }), showValue: true
})
const attrs = useAttrs()
const normalized = computed(() => Math.max(0, Math.min(100, Number(props.percentage) || 0)))
const actualTone = computed<Tone>(() => {
    if (!props.autoTone) return props.tone
    if (normalized.value >= (props.thresholds.success ?? 80)) return 'success'
    if (normalized.value >= (props.thresholds.warning ?? 40)) return 'warning'
    return 'danger'
})
const color = computed(() => `var(--hsx-color-${actualTone.value})`)
</script>

<template>
    <div class="hsx-progress">
        <div v-if="label || description || showValue" class="hsx-progress__meta">
            <div><strong v-if="label">{{ label }}</strong><span v-if="description">{{ description }}</span></div>
            <b v-if="showValue">{{ normalized }}%</b>
        </div>
        <el-progress v-bind="attrs" :percentage="normalized" :color="color" :show-text="false" />
    </div>
</template>

<style scoped>
.hsx-progress { min-width: 120px; }
.hsx-progress__meta { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--hsx-space-3); margin-bottom: var(--hsx-space-2); }
.hsx-progress__meta > div { display: flex; min-width: 0; flex-direction: column; }
.hsx-progress__meta strong { color: var(--hsx-text-primary); font-size: 14px; font-weight: 600; line-height: 22px; }
.hsx-progress__meta span { overflow: hidden; color: var(--hsx-text-secondary); font-size: 12px; line-height: 18px; text-overflow: ellipsis; white-space: nowrap; }
.hsx-progress__meta b { flex: none; color: var(--hsx-text-regular); font-size: 12px; font-variant-numeric: tabular-nums; }
</style>

