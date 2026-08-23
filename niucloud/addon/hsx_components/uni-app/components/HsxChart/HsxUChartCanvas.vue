<script lang="ts">
export default { name: 'HsxUChartCanvas' }
</script>

<script setup lang="ts">
import { getCurrentInstance, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import UCharts from '../../vendor/qiun-data-charts/js_sdk/u-charts/u-charts.js'

const props = withDefaults(defineProps<{
    type: string
    chartData?: Record<string, any>
    opts?: Record<string, any>
    canvasId?: string
    canvas2d?: boolean
    ontouch?: boolean
    animation?: boolean
    background?: string
    disableScroll?: boolean
}>(), {
    chartData: () => ({ categories: [], series: [] }),
    opts: () => ({}),
    canvasId: '',
    canvas2d: false,
    ontouch: true,
    animation: true,
    background: '#ffffff',
    disableScroll: false
})

const emit = defineEmits([
    'getIndex',
    'complete',
    'error',
    'getTouchStart',
    'getTouchMove',
    'getTouchEnd'
])

const owner = getCurrentInstance()
const canvasId = props.canvasId || `hsx-app-chart-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
const canvasWidth = ref(375)
const canvasHeight = ref(250)
let chart: any = null
let renderTimer: ReturnType<typeof setTimeout> | null = null

const clone = <T,>(value: T): T => JSON.parse(JSON.stringify(value))

const normalizeTouchEvent = (event: any) => {
    const source = event?.changedTouches?.[0] || event?.touches?.[0] || event?.detail || event || {}
    const point = {
        x: Number(source.x ?? source.clientX ?? source.pageX ?? 0),
        y: Number(source.y ?? source.clientY ?? source.pageY ?? 0)
    }
    return { ...event, changedTouches: [point], touches: event?.touches || [point] }
}

function dispose() {
    if (renderTimer) clearTimeout(renderTimer)
    renderTimer = null
    chart = null
}

function draw() {
    nextTick(() => {
        uni.createSelectorQuery()
            .in(owner?.proxy as any)
            .select(`#${canvasId}`)
            .boundingClientRect((rect: any) => {
                if (!rect?.width || !rect?.height) {
                    emit('error', { type: 'error', msg: '图表容器没有可用尺寸', id: canvasId })
                    return
                }
                canvasWidth.value = rect.width
                canvasHeight.value = rect.height
                renderTimer = setTimeout(() => {
                    try {
                        const options = clone(props.opts || {})
                        chart = new (UCharts as any)({
                            ...options,
                            $this: owner?.proxy,
                            canvasId,
                            context: uni.createCanvasContext(canvasId, owner?.proxy as any),
                            type: props.type,
                            categories: clone(props.chartData?.categories || []),
                            series: clone(props.chartData?.series || []),
                            width: rect.width,
                            height: rect.height,
                            animation: props.animation,
                            background: props.background,
                            pixelRatio: 1
                        })
                        emit('complete', { type: 'complete', complete: true, id: canvasId, opts: chart.opts })
                    } catch (error: any) {
                        emit('error', { type: 'error', msg: error?.message || '图表渲染失败', error, id: canvasId })
                    }
                }, 30)
            })
            .exec()
    })
}

function onTap(event: any) {
    if (!chart) return
    const normalized = normalizeTouchEvent(event)
    const currentIndex = chart.getCurrentDataIndex?.(normalized)
    chart.showToolTip?.(normalized)
    emit('getIndex', { type: 'getIndex', event: normalized.changedTouches[0], currentIndex, id: canvasId, opts: chart.opts })
}

function onTouchStart(event: any) {
    if (!chart || !props.ontouch) return
    const normalized = normalizeTouchEvent(event)
    chart.scrollStart?.(normalized)
    emit('getTouchStart', { type: 'touchStart', event: normalized.changedTouches[0], id: canvasId, opts: chart.opts })
}

function onTouchMove(event: any) {
    if (!chart || !props.ontouch) return
    const normalized = normalizeTouchEvent(event)
    chart.scroll?.(normalized)
    emit('getTouchMove', { type: 'touchMove', event: normalized.changedTouches[0], id: canvasId, opts: chart.opts })
}

function onTouchEnd(event: any) {
    if (!chart || !props.ontouch) return
    const normalized = normalizeTouchEvent(event)
    chart.scrollEnd?.(normalized)
    emit('getTouchEnd', { type: 'touchEnd', event: normalized.changedTouches[0], id: canvasId, opts: chart.opts })
}

watch(() => [props.type, props.chartData, props.opts, props.animation, props.background], () => {
    dispose()
    draw()
}, { deep: true })

onMounted(draw)
onBeforeUnmount(dispose)
</script>

<template>
    <view class="hsx-app-chart-canvas">
        <canvas
            :id="canvasId"
            :canvas-id="canvasId"
            class="hsx-app-chart-canvas__inner"
            :style="{ width: `${canvasWidth}px`, height: `${canvasHeight}px`, background }"
            :disable-scroll="disableScroll"
            @tap="onTap"
            @touchstart="onTouchStart"
            @touchmove="onTouchMove"
            @touchend="onTouchEnd"
        />
    </view>
</template>

<style scoped>
.hsx-app-chart-canvas,
.hsx-app-chart-canvas__inner {
    display: block;
    width: 100%;
    height: 100%;
}
</style>
