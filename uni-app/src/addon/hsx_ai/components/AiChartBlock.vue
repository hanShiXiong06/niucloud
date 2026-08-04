<template>
    <view class="chart-card">
        <view class="chart-head"><view><text class="chart-title">{{ data.title || '数据图表' }}</text><text v-if="data.subtitle" class="chart-subtitle">{{ data.subtitle }}</text></view><text v-if="data.unit" class="chart-unit">单位：{{ data.unit }}</text></view>
        <view v-if="hasData" class="chart-wrap"><canvas :id="canvasId" :canvas-id="canvasId" class="chart-canvas" :style="{ width: `${canvasWidth}px`, height: `${canvasHeight}px` }" @touchstart="touchStart" @touchmove.stop.prevent="touchMove" @touchend="touchEnd" /></view>
        <view v-else class="chart-empty">暂无可展示数据</view>
        <text v-if="data.note" class="chart-note">{{ data.note }}</text>
    </view>
</template>

<script setup lang="ts">
import { computed, getCurrentInstance, nextTick, onMounted, ref, watch } from 'vue'
import uCharts from '@qiun/ucharts'

const props = defineProps<{ data: any }>()
const instance = getCurrentInstance()
const canvasId = `aiChart_${Math.random().toString(36).slice(2, 10)}`
const canvasWidth = ref(320)
const canvasHeight = ref(220)
let chart: any = null
const categories = computed(() => (Array.isArray(props.data?.categories) ? props.data.categories : []).slice(0, 60).map(String))
const series = computed(() => (Array.isArray(props.data?.series) ? props.data.series : []).slice(0, 6).map((item: any, index: number) => ({ name: String(item?.name || `系列${index + 1}`), data: categories.value.map((_: string, dataIndex: number) => { const value = item?.data?.[dataIndex]; if (value === null || value === undefined || value === '') return null; const number = Number(value); return Number.isFinite(number) ? number : null }) })))
const hasData = computed(() => categories.value.length > 0 && series.value.some((item: any) => item.data.some((value: any) => Number.isFinite(value))))
const chartType = computed(() => ['column', 'bar'].includes(String(props.data?.chart_type || '')) ? 'column' : 'line')
const computeSize = () => { try { canvasWidth.value = Math.max(280, Number(uni.getSystemInfoSync().windowWidth || 375) - uni.upx2px(92)) } catch (_) { canvasWidth.value = 320 }; canvasHeight.value = uni.upx2px(410) }
const render = () => {
    if (!hasData.value) return
    const chartData = { categories: categories.value, series: series.value }
    if (chart) { try { chart.updateData(chartData); return } catch (_) { chart = null } }
    void nextTick(() => setTimeout(() => {
        try {
            chart = new uCharts({
                type: chartType.value, context: uni.createCanvasContext(canvasId, instance?.proxy), width: canvasWidth.value, height: canvasHeight.value,
                categories: chartData.categories, series: chartData.series, pixelRatio: 1, animation: true, background: '#FFFFFF',
                color: ['#2563eb', '#d92d20', '#087443', '#7c3aed', '#d97706', '#0891b2'], padding: [16, 12, 4, 12], dataLabel: false,
                legend: { show: series.value.length > 1, position: 'bottom', fontColor: '#667085', fontSize: 10 },
                xAxis: { disableGrid: true, fontColor: '#7c8799', fontSize: 10 }, yAxis: { gridColor: '#e7ebf0', fontColor: '#7c8799', fontSize: 10, data: [{}] },
                extra: chartType.value === 'line' ? { line: { type: 'curve', width: 2, activeType: 'hollow' }, tooltip: { showCategory: true, bgColor: '#172033', bgOpacity: 0.92, fontColor: '#ffffff' } } : { column: { type: 'group', width: 18 }, tooltip: { showCategory: true, bgColor: '#172033', bgOpacity: 0.92, fontColor: '#ffffff' } },
            })
        } catch (_) { chart = null }
    }, 40))
}
const touchStart = (event: any) => chart?.scrollStart(event)
const touchMove = (event: any) => chart?.scroll(event)
const touchEnd = (event: any) => { chart?.scrollEnd(event); chart?.showToolTip(event) }
watch(() => props.data, () => { chart = null; render() }, { deep: true })
onMounted(() => { computeSize(); render() })
</script>

<style lang="scss" scoped>
.chart-card { width: 100%; box-sizing: border-box; padding: 20rpx 22rpx; border: 1rpx solid #dfe3e8; border-radius: 8rpx; background: #fff; }.chart-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16rpx; }.chart-head > view { display: flex; min-width: 0; flex-direction: column; }.chart-title { color: #172033; font-size: 26rpx; font-weight: 650; }.chart-subtitle { margin-top: 5rpx; color: #7c8799; font-size: 19rpx; }.chart-unit { flex: none; color: #98a2b3; font-size: 18rpx; }.chart-wrap { display: flex; width: 100%; justify-content: center; margin-top: 12rpx; overflow: hidden; }.chart-canvas { display: block; }.chart-empty { display: flex; min-height: 220rpx; align-items: center; justify-content: center; color: #98a2b3; font-size: 21rpx; }.chart-note { display: block; margin-top: 12rpx; padding-top: 12rpx; border-top: 1rpx solid #edf0f3; color: #7c8799; font-size: 19rpx; line-height: 1.5; }
</style>
