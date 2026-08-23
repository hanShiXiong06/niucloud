<template>
    <view class="trend-card">
        <view class="trend-head">
            <view><text class="trend-title">{{ data.model }} {{ data.capacity }}</text><text class="trend-range">{{ shortDate(data.start_date) }} 至 {{ shortDate(data.end_date) }} · 固定7天</text></view>
            <text v-if="latestValue !== null" class="latest-price">¥{{ formatPrice(latestValue) }}</text>
        </view>
        <scroll-view v-if="grades.length > 1" class="grade-scroll" scroll-x :show-scrollbar="false">
            <view class="grade-track"><view v-for="grade in grades" :key="grade" class="grade-chip" :class="{ active: grade === activeGrade }" @click="selectGrade(grade)">{{ grade }}</view></view>
        </scroll-view>
        <view v-if="hasValues" class="chart-wrap">
            <canvas :id="canvasId" :canvas-id="canvasId" class="trend-canvas" :style="{ width: `${canvasWidth}px`, height: `${canvasHeight}px` }" @touchstart="touchStart" @touchmove.stop.prevent="touchMove" @touchend="touchEnd" />
        </view>
        <view v-else class="trend-empty">这个7天区间没有 {{ activeGrade || '该等级' }} 的报价快照</view>
        <view v-if="hasValues" class="trend-stats">
            <text>最高 ¥{{ formatPrice(maxValue) }}</text><text>最低 ¥{{ formatPrice(minValue) }}</text><text :class="changeValue > 0 ? 'up' : (changeValue < 0 ? 'down' : '')">较期初 {{ changeText }}</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, getCurrentInstance, nextTick, onMounted, ref, watch } from 'vue'
import uCharts from '@qiun/ucharts'

const props = defineProps<{ data: any }>()
const instance = getCurrentInstance()
const canvasId = `aiTrend_${Math.random().toString(36).slice(2, 10)}`
const activeGrade = ref(String(props.data?.selected_grade || props.data?.grades?.[0] || ''))
const canvasWidth = ref(320)
const canvasHeight = ref(210)
let chart: any = null
const grades = computed<string[]>(() => Array.isArray(props.data?.grades) ? props.data.grades : [])
const values = computed<Array<number | null>>(() => (props.data?.points || []).map((point: any) => {
    const value = point?.prices?.[activeGrade.value]
    return value === null || value === undefined || value === '' ? null : Number(value)
}))
const validValues = computed(() => values.value.filter((value): value is number => value !== null && Number.isFinite(value)))
const hasValues = computed(() => validValues.value.length > 0)
const latestValue = computed(() => validValues.value.length ? validValues.value[validValues.value.length - 1] : null)
const maxValue = computed(() => hasValues.value ? Math.max(...validValues.value) : 0)
const minValue = computed(() => hasValues.value ? Math.min(...validValues.value) : 0)
const changeValue = computed(() => validValues.value.length > 1 ? validValues.value[validValues.value.length - 1] - validValues.value[0] : 0)
const changeText = computed(() => `${changeValue.value > 0 ? '+' : ''}${formatPrice(changeValue.value)}`)
const shortDate = (value: string) => String(value || '').slice(5).replace('-', '/')
const formatPrice = (value: any) => Number(value || 0).toFixed(0)

const computeSize = () => {
    try {
        const system = uni.getSystemInfoSync()
        canvasWidth.value = Math.max(280, Number(system.windowWidth || 375) - uni.upx2px(92))
    } catch (_) { canvasWidth.value = 320 }
    canvasHeight.value = uni.upx2px(390)
}
const chartData = () => ({
    categories: (props.data?.points || []).map((point: any) => shortDate(point.date)),
    series: [{ name: activeGrade.value, data: values.value }],
})
const render = () => {
    if (!hasValues.value) return
    const data = chartData()
    if (chart) {
        try { chart.updateData(data); return } catch (_) { chart = null }
    }
    void nextTick(() => setTimeout(() => {
        try {
            chart = new uCharts({
                type: 'line', context: uni.createCanvasContext(canvasId, instance?.proxy), width: canvasWidth.value, height: canvasHeight.value,
                categories: data.categories, series: data.series, pixelRatio: 1, animation: true, background: '#FFFFFF', color: ['#2563eb'],
                padding: [14, 12, 4, 12], dataLabel: false, legend: { show: false },
                xAxis: { disableGrid: true, fontColor: '#7c8799', fontSize: 10 },
                yAxis: { gridColor: '#e7ebf0', fontColor: '#7c8799', fontSize: 10, data: [{}] },
                extra: { line: { type: 'curve', width: 2, activeType: 'hollow' }, tooltip: { showCategory: true, bgColor: '#172033', bgOpacity: 0.92, fontColor: '#ffffff' } },
            })
        } catch (_) { chart = null }
    }, 40))
}
const selectGrade = (grade: string) => { activeGrade.value = grade }
const touchStart = (event: any) => chart?.scrollStart(event)
const touchMove = (event: any) => chart?.scroll(event)
const touchEnd = (event: any) => { chart?.scrollEnd(event); chart?.showToolTip(event) }
watch([activeGrade, () => props.data], () => { chart = null; render() }, { deep: true })
onMounted(() => { computeSize(); render() })
</script>

<style lang="scss" scoped>
.trend-card { box-sizing: border-box; width: 100%; padding: 22rpx; border: 1rpx solid #dfe4ea; border-radius: 8rpx; background: #fff; }.trend-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16rpx; }.trend-head > view { display: flex; min-width: 0; flex-direction: column; }.trend-title { overflow: hidden; color: #172033; font-size: 27rpx; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }.trend-range { margin-top: 6rpx; color: #7c8799; font-size: 20rpx; }.latest-price { flex: none; color: #d92d20; font-size: 30rpx; font-weight: 700; }.grade-scroll { width: 100%; margin-top: 17rpx; }.grade-track { display: flex; width: max-content; gap: 9rpx; padding-right: 12rpx; }.grade-chip { display: flex; height: 46rpx; align-items: center; padding: 0 15rpx; border: 1rpx solid #dfe4ea; border-radius: 6rpx; color: #667085; font-size: 20rpx; }.grade-chip.active { border-color: #9db7ee; background: #f2f6ff; color: #1d4ed8; }.chart-wrap { display: flex; width: 100%; justify-content: center; margin-top: 12rpx; overflow: hidden; }.trend-canvas { display: block; }.trend-empty { display: flex; min-height: 180rpx; align-items: center; justify-content: center; color: #98a2b3; font-size: 21rpx; }.trend-stats { display: flex; align-items: center; justify-content: space-between; gap: 10rpx; padding-top: 12rpx; border-top: 1rpx solid #edf0f3; color: #667085; font-size: 19rpx; }.trend-stats .up { color: #d92d20; }.trend-stats .down { color: #087443; }
</style>
