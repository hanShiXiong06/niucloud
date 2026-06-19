<template>
    <div ref="chartRef" class="vue-chart" :style="{ height }"></div>
</template>

<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import * as echarts from 'echarts'

const props = withDefaults(defineProps<{
    option: Record<string, any>
    height?: string
}>(), {
    height: '320px',
})

const chartRef = ref<HTMLElement>()
let chart: echarts.ECharts | null = null
let resizeObserver: ResizeObserver | null = null

const render = () => {
    if (!chartRef.value) return
    if (!chart) chart = echarts.init(chartRef.value)
    chart.setOption(props.option || {}, true)
    nextTick(() => chart?.resize())
}

watch(() => props.option, () => render(), { deep: true })

const resize = () => chart?.resize()

onMounted(() => {
    render()
    if (chartRef.value && typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(() => resize())
        resizeObserver.observe(chartRef.value)
    }
    window.addEventListener('resize', resize)
})

onUnmounted(() => {
    window.removeEventListener('resize', resize)
    resizeObserver?.disconnect()
    chart?.dispose()
    chart = null
})
</script>

<style scoped>
.vue-chart {
    width: 100%;
    min-height: 240px;
}
</style>
