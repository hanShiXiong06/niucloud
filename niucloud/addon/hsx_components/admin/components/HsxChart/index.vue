<script lang="ts">export default { name: 'HsxChart', inheritAttrs: false }</script>
<script setup lang="ts">
import type { ECharts, EChartsOption, ECElementEvent } from 'echarts'
import { computed, ref } from 'vue'
import { useChart } from '../../hooks/useChart'

const props = withDefaults(defineProps<{
    option: EChartsOption
    height?: string | number
    minHeight?: string | number
    loading?: boolean
    empty?: boolean
    emptyText?: string
    renderer?: 'canvas' | 'svg'
    autoResize?: boolean
    observeTheme?: boolean
}>(), {
    height: 320,
    minHeight: 160,
    loading: false,
    empty: false,
    emptyText: '暂无图表数据',
    renderer: 'canvas',
    autoResize: true,
    observeTheme: true
})

const emit = defineEmits<{
    (event: 'ready', instance: ECharts): void
    (event: 'click', params: ECElementEvent): void
    (event: 'finished'): void
}>()

const chartRef = ref<HTMLElement | null>(null)
const optionRef = computed(() => props.option)
const loadingRef = computed(() => props.loading)
const size = (value: string | number) => typeof value === 'number' ? `${value}px` : value
const rootStyle = computed(() => ({ height: size(props.height), minHeight: size(props.minHeight) }))

const chart = useChart(chartRef, optionRef, {
    renderer: props.renderer,
    autoResize: props.autoResize,
    observeTheme: props.observeTheme,
    loading: loadingRef,
    onReady(instance) {
        instance.on('click', (params) => emit('click', params as ECElementEvent))
        instance.on('finished', () => emit('finished'))
        emit('ready', instance)
    }
})

defineExpose({
    instance: chart.instance,
    init: chart.init,
    render: chart.render,
    resize: chart.resize,
    dispose: chart.dispose,
    getDataURL: (options?: Parameters<ECharts['getDataURL']>[0]) => chart.instance.value?.getDataURL(options),
    dispatchAction: (payload: Parameters<ECharts['dispatchAction']>[0]) => chart.instance.value?.dispatchAction(payload)
})
</script>

<template>
    <div v-bind="$attrs" class="hsx-chart" :style="rootStyle">
        <div ref="chartRef" class="hsx-chart__canvas" :aria-hidden="empty" />
        <div v-if="empty" class="hsx-chart__empty">
            <slot name="empty"><span class="hsx-chart__empty-icon">⌁</span><span>{{ emptyText }}</span></slot>
        </div>
    </div>
</template>

<style scoped>
.hsx-chart { position: relative; width: 100%; min-width: 0; }
.hsx-chart__canvas { width: 100%; height: 100%; }
.hsx-chart__empty { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: var(--hsx-text-secondary); background: color-mix(in srgb, var(--hsx-bg-surface) 82%, transparent); font-size: 13px; }
.hsx-chart__empty-icon { font-size: 30px; line-height: 1; opacity: .55; }
</style>
