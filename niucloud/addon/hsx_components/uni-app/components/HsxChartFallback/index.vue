<script lang="ts">
export default { name: 'HsxChartFallback' }
</script>

<script setup lang="ts">
import { computed } from 'vue'
import type { MobileChartData, MobileChartMode, MobileChartType } from '../../types'

const props = withDefaults(defineProps<{
    type: MobileChartType
    chartData?: MobileChartData
    mode?: MobileChartMode
    limit?: number
    label?: string
}>(), {
    chartData: () => ({ categories: [], series: [] }),
    mode: 'light',
    limit: 7,
    label: ''
})

const isCircular = computed(() => ['pie', 'ring', 'rose', 'funnel'].includes(props.type))
const items = computed(() => {
    const raw = props.chartData.series?.[0]?.data || []
    const categories = props.chartData.categories || []
    const normalized = raw.slice(0, Math.max(1, props.limit)).map((item: any, index: number) => {
        const value = Number(typeof item === 'number' ? item : item?.value ?? item?.data ?? 0)
        return {
            label: String(item?.name ?? categories[index] ?? index + 1),
            value: Number.isFinite(value) ? value : 0
        }
    })
    const maximum = Math.max(1, ...normalized.map((item) => Math.abs(item.value)))
    return normalized.map((item) => ({ ...item, percentage: Math.max(5, Math.round(Math.abs(item.value) / maximum * 100)) }))
})
</script>

<template>
    <view class="hsx-chart-fallback" :class="[`hsx-chart-fallback--${mode}`, { 'hsx-chart-fallback--circular': isCircular }]">
        <text v-if="label" class="hsx-chart-fallback__hint">{{ label }}</text>
        <view v-if="isCircular" class="hsx-chart-fallback__rows">
            <view v-for="(item, index) in items" :key="`${item.label}-${index}`" class="hsx-chart-fallback__row">
                <text class="hsx-chart-fallback__dot" :class="`hsx-chart-fallback__dot--${index % 5}`" />
                <text class="hsx-chart-fallback__label">{{ item.label }}</text>
                <view class="hsx-chart-fallback__track"><view class="hsx-chart-fallback__fill" :class="`hsx-chart-fallback__fill--${index % 5}`" :style="{ width: `${item.percentage}%` }" /></view>
                <text class="hsx-chart-fallback__value">{{ item.value }}</text>
            </view>
        </view>
        <view v-else class="hsx-chart-fallback__plot">
            <view v-for="(item, index) in items" :key="`${item.label}-${index}`" class="hsx-chart-fallback__column">
                <view class="hsx-chart-fallback__bar-wrap"><view class="hsx-chart-fallback__bar" :class="`hsx-chart-fallback__bar--${index % 5}`" :style="{ height: `${item.percentage}%` }" /></view>
                <text class="hsx-chart-fallback__axis-label">{{ item.label }}</text>
            </view>
        </view>
    </view>
</template>

<style scoped>
.hsx-chart-fallback { position: absolute; z-index: 0; inset: 0; box-sizing: border-box; padding: 14px 12px 8px; color: var(--hsx-mobile-text-secondary, #64748b); }
.hsx-chart-fallback--dark { color: #94a3b8; }
.hsx-chart-fallback__hint { position: absolute; top: 6px; right: 9px; font-size: 10px; opacity: .62; }
.hsx-chart-fallback__plot { display: flex; height: 100%; align-items: stretch; justify-content: space-around; gap: 8px; border-bottom: 1px dashed var(--hsx-mobile-border, #e6ebf2); }
.hsx-chart-fallback__column { display: flex; min-width: 0; flex: 1; flex-direction: column; align-items: center; }
.hsx-chart-fallback__bar-wrap { display: flex; width: 100%; min-height: 0; flex: 1; align-items: flex-end; justify-content: center; }
.hsx-chart-fallback__bar { width: min(24px, 52%); min-height: 8px; border-radius: 7px 7px 2px 2px; background: linear-gradient(180deg, #60a5fa, #2563eb); }
.hsx-chart-fallback__bar--1 { background: linear-gradient(180deg, #34d399, #059669); }
.hsx-chart-fallback__bar--2 { background: linear-gradient(180deg, #fb923c, #ea580c); }
.hsx-chart-fallback__bar--3 { background: linear-gradient(180deg, #a78bfa, #7c3aed); }
.hsx-chart-fallback__bar--4 { background: linear-gradient(180deg, #22d3ee, #0891b2); }
.hsx-chart-fallback__axis-label { max-width: 100%; margin-top: 6px; overflow: hidden; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }
.hsx-chart-fallback__rows { display: flex; height: 100%; flex-direction: column; justify-content: center; gap: 10px; }
.hsx-chart-fallback__row { display: flex; min-width: 0; align-items: center; gap: 7px; }
.hsx-chart-fallback__dot { width: 7px; height: 7px; flex: none; border-radius: 50%; background: #3b82f6; }
.hsx-chart-fallback__dot--1, .hsx-chart-fallback__fill--1 { background: #10b981; }
.hsx-chart-fallback__dot--2, .hsx-chart-fallback__fill--2 { background: #f97316; }
.hsx-chart-fallback__dot--3, .hsx-chart-fallback__fill--3 { background: #7c3aed; }
.hsx-chart-fallback__dot--4, .hsx-chart-fallback__fill--4 { background: #06b6d4; }
.hsx-chart-fallback__label { width: 74px; overflow: hidden; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
.hsx-chart-fallback__track { height: 7px; min-width: 0; flex: 1; overflow: hidden; border-radius: 99px; background: rgba(148, 163, 184, .18); }
.hsx-chart-fallback__fill { height: 100%; border-radius: inherit; background: #3b82f6; }
.hsx-chart-fallback__value { min-width: 30px; font-size: 11px; text-align: right; }
</style>
