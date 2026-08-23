<script lang="ts">
export default { name: 'HsxChart' }
</script>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
// #ifdef APP-VUE
import HsxAppDataCharts from './HsxUChartCanvas.vue'
// #endif
// #ifndef APP-VUE
import QiunDataCharts from '../../vendor/qiun-data-charts/components/qiun-data-charts/qiun-data-charts.vue'
// #endif
import HsxChartFallback from '../HsxChartFallback/index.vue'
import { createMobileChartOptions, hasMobileChartData, mergeMobileChartOptions } from '../../charts'
import type { MobileChartData, MobileChartMode, MobileChartType } from '../../types'

const props = withDefaults(
    defineProps<{
        type: MobileChartType
        chartData?: MobileChartData
        opts?: Record<string, any>
        mode?: MobileChartMode
        height?: string | number
        minHeight?: string | number
        canvasId?: string
        canvas2d?: boolean
        ontouch?: boolean
        animation?: boolean
        background?: string
        loading?: boolean
        empty?: boolean | null
        emptyText?: string
        error?: string
        disableScroll?: boolean
        fallback?: boolean
        fallbackLabel?: string
    }>(),
    {
        chartData: () => ({ categories: [], series: [] }),
        opts: () => ({}),
        mode: 'light',
        height: '220px',
        minHeight: '180px',
        canvasId: '',
        canvas2d: true,
        ontouch: true,
        animation: true,
        background: 'rgba(0,0,0,0)',
        loading: false,
        empty: null,
        emptyText: '暂无图表数据',
        error: '',
        disableScroll: false,
        fallback: true,
        fallbackLabel: ''
    }
)

const emit = defineEmits<{
    (event: 'select', payload: any): void
    (event: 'complete', payload: any): void
    (event: 'error', payload: any): void
    (event: 'touch-start', payload: any): void
    (event: 'touch-move', payload: any): void
    (event: 'touch-end', payload: any): void
}>()

const sizeValue = (value: string | number) => typeof value === 'number' ? `${value}rpx` : value
const rootStyle = computed(() => ({ height: sizeValue(props.height), minHeight: sizeValue(props.minHeight) }))
const resolvedEmpty = computed(() => props.empty === null ? !hasMobileChartData(props.chartData) : props.empty)
const resolvedBackground = computed(() => {
    if (props.background !== 'rgba(0,0,0,0)') return props.background
    return props.mode === 'dark' ? '#0f172a' : '#ffffff'
})
const resolvedOptions = computed(() => mergeMobileChartOptions(
    createMobileChartOptions(props.type, {}, props.mode),
    props.opts
))
const canvasReady = ref(false)
watch(() => [props.type, props.chartData, props.opts, props.mode], () => { canvasReady.value = false }, { deep: true })
function handleComplete(payload: any) {
    canvasReady.value = true
    emit('complete', payload)
}
function handleError(payload: any) {
    canvasReady.value = false
    emit('error', payload)
}
</script>

<template>
    <view class="hsx-chart" :class="`hsx-chart--${mode}`" :style="rootStyle">
        <view v-if="loading" class="hsx-chart__state">
            <slot name="loading"><u-loading-icon mode="circle" text="图表加载中" /></slot>
        </view>
        <view v-else-if="error" class="hsx-chart__state hsx-chart__state--error">
            <slot name="error" :message="error"><text>{{ error }}</text></slot>
        </view>
        <view v-else-if="resolvedEmpty" class="hsx-chart__state">
            <slot name="empty"><text class="hsx-chart__empty-text">{{ emptyText }}</text></slot>
        </view>
        <HsxChartFallback
            v-else-if="fallback && !canvasReady"
            :type="type"
            :chart-data="chartData"
            :mode="mode"
            :label="fallbackLabel"
        />
        <!-- #ifndef APP-VUE -->
        <QiunDataCharts
            v-if="!loading && !error && !resolvedEmpty"
            class="hsx-chart__canvas"
            :type="type"
            :chart-data="chartData"
            :opts="resolvedOptions"
            :canvas-id="canvasId"
            :canvas2d="canvas2d"
            :ontouch="ontouch"
            :animation="animation"
            :background="resolvedBackground"
            :disable-scroll="disableScroll"
            @getIndex="emit('select', $event)"
            @complete="handleComplete"
            @error="handleError"
            @getTouchStart="emit('touch-start', $event)"
            @getTouchMove="emit('touch-move', $event)"
            @getTouchEnd="emit('touch-end', $event)"
        />
        <!-- #endif -->
        <!-- #ifdef APP-VUE -->
        <HsxAppDataCharts
            v-if="!loading && !error && !resolvedEmpty"
            class="hsx-chart__canvas"
            :type="type"
            :chart-data="chartData"
            :opts="resolvedOptions"
            :canvas-id="canvasId"
            :canvas2d="canvas2d"
            :ontouch="ontouch"
            :animation="animation"
            :background="resolvedBackground"
            :disable-scroll="disableScroll"
            @getIndex="emit('select', $event)"
            @complete="handleComplete"
            @error="handleError"
            @getTouchStart="emit('touch-start', $event)"
            @getTouchMove="emit('touch-move', $event)"
            @getTouchEnd="emit('touch-end', $event)"
        />
        <!-- #endif -->
    </view>
</template>

<style scoped lang="scss">
.hsx-chart {
    position: relative;
    width: 100%;
    box-sizing: border-box;
    overflow: hidden;
}

.hsx-chart__canvas {
    position: relative;
    z-index: 1;
    display: block;
    width: 100%;
    height: 100%;
}

.hsx-chart__state {
    position: relative;
    z-index: 2;
    display: flex;
    width: 100%;
    height: 100%;
    align-items: center;
    justify-content: center;
    color: var(--hsx-mobile-text-secondary, #8a8f99);
    font-size: 24rpx;
}

.hsx-chart__state--error {
    color: var(--hsx-mobile-danger, #ef4444);
}

.hsx-chart__empty-text {
    color: var(--hsx-mobile-text-secondary, #8a8f99);
}
</style>
