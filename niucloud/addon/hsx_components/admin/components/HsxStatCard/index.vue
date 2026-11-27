<script lang="ts">export default { name: 'HsxStatCard' }</script>
<script setup lang="ts">
import type { EChartsOption } from 'echarts'
import { computed } from 'vue'
import HsxChart from '../HsxChart/index.vue'
import HsxIcon from '../HsxIcon/index.vue'

const props = withDefaults(defineProps<{
    title: string
    value: string | number
    unit?: string
    description?: string
    trend?: string | number
    trendLabel?: string
    trendTone?: 'success' | 'danger' | 'warning' | 'neutral'
    tone?: 'primary' | 'success' | 'warning' | 'danger' | 'info'
    icon?: string
    chartOption?: EChartsOption
    chartHeight?: string | number
    locale?: string
    loading?: boolean
}>(), { unit: '', trendTone: 'success', tone: 'primary', chartHeight: 64, locale: 'zh-CN', loading: false })

const displayValue = computed(() => typeof props.value === 'number' ? new Intl.NumberFormat(props.locale).format(props.value) : props.value)
</script>

<template>
    <article class="hsx-stat-card" :class="`hsx-stat-card--${tone}`">
        <div class="hsx-stat-card__main">
            <div class="hsx-stat-card__copy">
                <span class="hsx-stat-card__title">{{ title }}</span>
                <div v-if="loading" class="hsx-stat-card__skeleton" />
                <div v-else class="hsx-stat-card__value"><slot name="value">{{ displayValue }}<small v-if="unit">{{ unit }}</small></slot></div>
                <div v-if="trend !== undefined || description" class="hsx-stat-card__meta">
                    <span v-if="trend !== undefined" class="hsx-stat-card__trend" :class="`is-${trendTone}`">{{ trend }}</span>
                    <span>{{ trendLabel || description }}</span>
                </div>
            </div>
            <div v-if="icon || $slots.icon" class="hsx-stat-card__icon"><slot name="icon"><HsxIcon :name="icon" :size="22" /></slot></div>
        </div>
        <HsxChart v-if="chartOption" :option="chartOption" :height="chartHeight" :min-height="chartHeight" class="hsx-stat-card__chart" />
        <slot name="footer" />
    </article>
</template>

<style scoped>
.hsx-stat-card { --hsx-stat-tone: var(--hsx-color-primary); position: relative; min-width: 0; overflow: hidden; padding: 18px; border: 1px solid var(--hsx-border-color); border-radius: var(--hsx-radius-lg); background: var(--hsx-bg-surface); box-shadow: var(--hsx-shadow-card); transition: transform var(--hsx-motion-normal) var(--hsx-ease-emphasized), box-shadow var(--hsx-motion-normal) var(--hsx-ease-standard); }
.hsx-stat-card:hover { box-shadow: var(--hsx-shadow-floating); transform: translateY(-2px); }
.hsx-stat-card--success { --hsx-stat-tone: var(--hsx-color-success); }
.hsx-stat-card--warning { --hsx-stat-tone: var(--hsx-color-warning); }
.hsx-stat-card--danger { --hsx-stat-tone: var(--hsx-color-danger); }
.hsx-stat-card--info { --hsx-stat-tone: #06b6d4; }
.hsx-stat-card__main { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.hsx-stat-card__copy { min-width: 0; }
.hsx-stat-card__title { color: var(--hsx-text-secondary); font-size: 13px; }
.hsx-stat-card__value { margin-top: 8px; color: var(--hsx-text-primary); font-size: 28px; font-weight: 720; font-variant-numeric: tabular-nums; letter-spacing: 0; line-height: 1.15; }
.hsx-stat-card__value small { margin-left: 4px; color: var(--hsx-text-secondary); font-size: 13px; font-weight: 500; }
.hsx-stat-card__meta { display: flex; align-items: center; gap: 6px; margin-top: 8px; color: var(--hsx-text-secondary); font-size: 12px; }
.hsx-stat-card__trend { font-weight: 700; }
.hsx-stat-card__trend.is-success { color: var(--hsx-color-success); }
.hsx-stat-card__trend.is-danger { color: var(--hsx-color-danger); }
.hsx-stat-card__trend.is-warning { color: var(--hsx-color-warning); }
.hsx-stat-card__icon { display: grid; width: 48px; height: 48px; flex: none; place-items: center; border-radius: 14px; color: var(--hsx-stat-tone); background: color-mix(in srgb, var(--hsx-stat-tone) 11%, transparent); }
.hsx-stat-card__chart { margin: 8px -4px -8px; }
.hsx-stat-card__skeleton { width: 112px; height: 30px; margin-top: 9px; border-radius: 8px; background: var(--hsx-skeleton-gradient); background-size: 220% 100%; animation: hsx-shimmer 1.4s infinite linear; }
</style>
