<script lang="ts">
export default { name: 'HsxChartCard' }
</script>

<script setup lang="ts">
import { computed } from 'vue'
import HsxChart from '../HsxChart/index.vue'
import { useAdaptiveContext } from '../../hooks/useAdaptiveLayout'
import type { MobileChartData, MobileChartMode, MobileChartType } from '../../types'

const props = withDefaults(
    defineProps<{
        title: string
        subtitle?: string
        trend?: string
        trendTone?: 'success' | 'danger' | 'neutral'
        type: MobileChartType
        chartData?: MobileChartData
        opts?: Record<string, any>
        mode?: MobileChartMode
        height?: string | number
        loading?: boolean
        empty?: boolean | null
        emptyText?: string
        error?: string
    }>(),
    {
        subtitle: '',
        trend: '',
        trendTone: 'neutral',
        chartData: () => ({ categories: [], series: [] }),
        opts: () => ({}),
        mode: 'light',
        height: '',
        loading: false,
        empty: null,
        emptyText: '暂无图表数据',
        error: ''
    }
)

const emit = defineEmits<{ (event: 'select', payload: any): void }>()
const layout = useAdaptiveContext()
const actualHeight = computed(() => props.height || (layout.isExpanded.value ? '260px' : layout.isMedium.value ? '240px' : '220px'))
</script>

<template>
    <view class="hsx-chart-card">
        <view class="hsx-chart-card__header">
            <view class="hsx-chart-card__heading">
                <text class="hsx-chart-card__title">{{ title }}</text>
                <text v-if="subtitle" class="hsx-chart-card__subtitle">{{ subtitle }}</text>
            </view>
            <slot name="extra">
                <text v-if="trend" class="hsx-chart-card__trend" :class="`hsx-chart-card__trend--${trendTone}`">{{ trend }}</text>
            </slot>
        </view>
        <HsxChart
            :type="type"
            :chart-data="chartData"
            :opts="opts"
            :mode="mode"
            :height="actualHeight"
            :loading="loading"
            :empty="empty"
            :empty-text="emptyText"
            :error="error"
            @select="emit('select', $event)"
        >
            <template v-if="$slots.empty" #empty><slot name="empty" /></template>
        </HsxChart>
        <view v-if="$slots.footer" class="hsx-chart-card__footer"><slot name="footer" /></view>
    </view>
</template>

<style scoped lang="scss">
.hsx-chart-card {
    box-sizing: border-box;
    padding: var(--hsx-mobile-card-padding, 14px);
    border: 1px solid var(--hsx-mobile-border, #e6ebf2);
    border-radius: 14px;
    background: var(--hsx-mobile-bg-surface, #fff);
    box-shadow: 0 7px 18px rgba(22, 42, 83, .08);
}

.hsx-chart-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 0 2px 6px;
}

.hsx-chart-card__heading {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
}

.hsx-chart-card__title {
    color: var(--hsx-mobile-text-primary, #202124);
    font-size: var(--hsx-mobile-font-subtitle, 15px);
    font-weight: 650;
}

.hsx-chart-card__subtitle {
    margin-top: 4px;
    color: var(--hsx-mobile-text-secondary, #8a8f99);
    font-size: var(--hsx-mobile-font-caption, 12px);
}

.hsx-chart-card__trend {
    padding: 3px 7px;
    border-radius: 999px;
    background: var(--hsx-mobile-bg-muted, #f7f8fa);
    color: var(--hsx-mobile-text-secondary, #8a8f99);
    font-size: var(--hsx-mobile-font-caption, 12px);
}

.hsx-chart-card__trend--success { color: var(--hsx-mobile-success, #10b981); }
.hsx-chart-card__trend--danger { color: var(--hsx-mobile-danger, #ef4444); }

.hsx-chart-card__footer {
    padding: 7px 2px 1px;
    color: var(--hsx-mobile-text-secondary, #8a8f99);
    font-size: var(--hsx-mobile-font-caption, 12px);
}
</style>
