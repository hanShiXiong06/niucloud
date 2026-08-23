<script lang="ts">export default { name: 'HsxChartCard' }</script>
<script setup lang="ts">
import type { EChartsOption } from 'echarts'
import HsxChart from '../HsxChart/index.vue'
import HsxTag from '../HsxTag/index.vue'

withDefaults(defineProps<{
    title: string
    subtitle?: string
    option: EChartsOption
    height?: string | number
    loading?: boolean
    empty?: boolean
    trend?: string | number
    trendTone?: 'neutral' | 'primary' | 'info' | 'success' | 'warning' | 'danger'
}>(), { height: 300, loading: false, empty: false, trendTone: 'success' })
</script>

<template>
    <section class="hsx-chart-card">
        <header class="hsx-chart-card__header">
            <div><h3>{{ title }}</h3><p v-if="subtitle">{{ subtitle }}</p></div>
            <div class="hsx-chart-card__actions">
                <HsxTag v-if="trend !== undefined" :text="trend" :tone="trendTone" size="small" />
                <slot name="extra" />
            </div>
        </header>
        <HsxChart :option="option" :height="height" :loading="loading" :empty="empty" @click="$emit('chart-click', $event)" />
        <slot name="footer" />
    </section>
</template>

<style scoped>
.hsx-chart-card { min-width: 0; padding: 20px; border: 1px solid var(--hsx-border-color); border-radius: var(--hsx-radius-lg); background: var(--hsx-bg-surface); box-shadow: var(--hsx-shadow-card); }
.hsx-chart-card__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 8px; }
.hsx-chart-card h3 { margin: 0; color: var(--hsx-text-primary); font-size: 16px; }
.hsx-chart-card p { margin: 5px 0 0; color: var(--hsx-text-secondary); font-size: 12px; }
.hsx-chart-card__actions { display: flex; flex: none; align-items: center; gap: 8px; }
</style>
