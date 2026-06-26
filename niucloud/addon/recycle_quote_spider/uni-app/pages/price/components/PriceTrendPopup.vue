<template>
	<view v-if="visible" class="trend-mask" @click="close" @touchmove.stop.prevent catchtouchmove="noop">
		<view class="trend-panel" @click.stop @touchmove.stop>
			<view class="trend-head">
				<view class="trend-title-wrap">
					<text class="trend-title">价格走势</text>
					<text class="trend-sub">{{ title || '' }}</text>
				</view>
				<text class="trend-close" @click="close">✕</text>
			</view>

			<view class="trend-days">
				<text
					v-for="opt in dayOptions"
					:key="opt.value"
					class="trend-day-chip"
					:class="{ active: days === opt.value }"
					@click="days = opt.value"
				>{{ opt.label }}</text>
			</view>

			<scroll-view v-if="hasData && columns.length > 1" scroll-x class="trend-grades">
				<view
					v-for="(col, idx) in columns"
					:key="idx"
					class="grade-chip"
					:class="{ active: selectedCols.includes(col) }"
					@click="toggleCol(col)"
				>
					<text class="grade-dot" :style="{ background: colColor(idx) }"></text>
					<text class="grade-name">{{ col }}</text>
				</view>
			</scroll-view>

			<view v-if="loading" class="trend-empty">加载中...</view>
			<view v-else-if="!hasData" class="trend-empty">暂无历史价格（价格变动后会逐步累积）</view>

			<view v-else class="trend-chart-box">
				<canvas
					canvas-id="priceTrendCanvas"
					id="priceTrendCanvas"
					class="trend-canvas"
					:style="{ width: canvasWidth + 'px', height: canvasHeight + 'px' }"
					@touchstart.stop="onTouchStart"
					@touchmove.stop.prevent="onTouchMove"
					@touchend.stop="onTouchEnd"
				/>
				<text class="trend-tip">点上方等级可切换显示 · 单指拖动看每天明细</text>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { computed, getCurrentInstance, nextTick, ref, watch } from 'vue'
import uCharts from '@qiun/ucharts'
import { getQuoteSpiderPriceHistory } from '@/addon/recycle_quote_spider/api/quotation'

const props = withDefaults(
	defineProps<{
		visible: boolean
		rowId: number | string
		title?: string
		activeColumn?: string
		theme?: Record<string, string>
	}>(),
	{ title: '', activeColumn: '', theme: () => ({}) }
)
const emit = defineEmits<{ (e: 'update:visible', v: boolean): void }>()

const instance = getCurrentInstance()

const dayOptions = [
	{ label: '近7天', value: 7 },
	{ label: '近30天', value: 30 },
	{ label: '近90天', value: 90 }
]

const days = ref(30)
const loading = ref(false)
const columns = ref<string[]>([])
const points = ref<Array<{ date: string; prices: any[] }>>([])
const selectedCols = ref<string[]>([])

let chart: any = null

const hasData = computed(() => points.value.length > 0 && columns.value.length > 0)

// 配色：跟随页面服务端主题，缺省回退到设计稿默认
const palette = computed(() => {
	const t = props.theme || {}
	const brand = t.brand || '#3b82f6'
	const price = t.price || '#2563eb'
	return {
		brand,
		price,
		sub: t.text_sub || '#6b7280',
		line: t.line || '#e5e7eb',
		series: [brand, price, '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#0ea5e9', '#ec4899']
	}
})

function colColor(idx: number) {
	const list = palette.value.series
	return list[idx % list.length]
}

const canvasWidth = ref(320)
const canvasHeight = ref(220)

function computeSize() {
	try {
		const sys = uni.getSystemInfoSync()
		canvasWidth.value = Math.max(280, (sys.windowWidth || 375) - uni.upx2px(56))
	} catch (e) {
		canvasWidth.value = 320
	}
	canvasHeight.value = uni.upx2px(440)
}

function buildChartData() {
	const categories = points.value.map(p => String(p.date).slice(5))
	const series = columns.value.map((col, idx) => ({
		name: col,
		show: selectedCols.value.includes(col),
		data: points.value.map(p => {
			const v = Number(p.prices?.[idx])
			return Number.isFinite(v) ? v : null
		})
	}))
	return { categories, series }
}

// 已创建则更新数据，未创建则初始化
function renderChart() {
	if (!hasData.value) return
	const { categories, series } = buildChartData()
	if (chart) {
		try {
			chart.updateData({ categories, series })
		} catch (e) {
			chart = null
		}
		if (chart) return
	}
	nextTick(() => {
		setTimeout(() => {
			try {
				const ctx = uni.createCanvasContext('priceTrendCanvas', instance?.proxy)
				chart = new uCharts({
					type: 'area',
					context: ctx,
					width: canvasWidth.value,
					height: canvasHeight.value,
					categories,
					series,
					pixelRatio: 1,
					animation: true,
					background: '#FFFFFF',
					color: palette.value.series,
					padding: [16, 16, 6, 16],
					dataLabel: false,
					enableScroll: true,
					legend: { show: false },
					xAxis: {
						disableGrid: true,
						scrollShow: true,
						itemCount: Math.min(7, categories.length || 1),
						fontColor: palette.value.sub,
						fontSize: 10
					},
					yAxis: {
						gridColor: palette.value.line,
						fontColor: palette.value.sub,
						fontSize: 10,
						data: [{ min: undefined, max: undefined }]
					},
					extra: {
						area: { type: 'curve', opacity: 0.2, addLine: true, width: 2, gradient: true, activeType: 'hollow' },
						tooltip: {
							showCategory: true,
							borderColor: palette.value.brand,
							bgColor: '#1f2937',
							bgOpacity: 0.9,
							fontColor: '#ffffff'
						}
					}
				})
			} catch (e) {
				chart = null
			}
		}, 50)
	})
}

function toggleCol(col: string) {
	const i = selectedCols.value.indexOf(col)
	if (i >= 0) {
		if (selectedCols.value.length <= 1) return // 至少保留一个
		selectedCols.value.splice(i, 1)
	} else {
		selectedCols.value.push(col)
	}
	renderChart()
}

function onTouchStart(e: any) {
	chart && chart.scrollStart(e)
}
function onTouchMove(e: any) {
	chart && chart.scroll(e)
}
function onTouchEnd(e: any) {
	if (!chart) return
	chart.scrollEnd(e)
	chart.showToolTip(e)
}

async function load() {
	if (!props.rowId) return
	loading.value = true
	try {
		const res: any = await getQuoteSpiderPriceHistory(props.rowId, days.value)
		columns.value = res?.data?.columns || []
		points.value = res?.data?.points || []
	} finally {
		loading.value = false
	}
	// 默认只看用户点选的等级，没有则看第一个
	const def = props.activeColumn && columns.value.includes(props.activeColumn) ? props.activeColumn : columns.value[0]
	selectedCols.value = def ? [def] : [...columns.value]
	renderChart()
}

function close() {
	chart = null
	emit('update:visible', false)
}

function noop() {}

watch(
	() => props.visible,
	val => {
		if (val) {
			computeSize()
			load()
		} else {
			chart = null
		}
	}
)
watch(days, () => {
	if (props.visible) load()
})
</script>

<style lang="scss" scoped>
.trend-mask {
	position: fixed;
	left: 0;
	top: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.45);
	z-index: 999;
	display: flex;
	align-items: flex-end;
}
.trend-panel {
	width: 100%;
	background: var(--bg-card, #ffffff);
	border-radius: 24rpx 24rpx 0 0;
	padding: 28rpx 28rpx calc(28rpx + env(safe-area-inset-bottom));
	max-height: 84vh;
	box-sizing: border-box;
}
.trend-head {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
}
.trend-title {
	font-size: 32rpx;
	font-weight: 600;
	color: var(--text-main, #1f2937);
}
.trend-sub {
	display: block;
	margin-top: 6rpx;
	font-size: 24rpx;
	color: var(--text-sub, #6b7280);
}
.trend-close {
	font-size: 32rpx;
	color: var(--text-sub, #6b7280);
	padding: 4rpx 8rpx;
}
.trend-days {
	display: flex;
	gap: 16rpx;
	margin: 24rpx 0 12rpx;
}
.trend-day-chip {
	padding: 10rpx 24rpx;
	font-size: 24rpx;
	color: var(--series-inactive-text, #475569);
	background: var(--series-inactive-bg, #f5f5f7);
	border-radius: 999rpx;
}
.trend-day-chip.active {
	color: var(--series-active-text, #ffffff);
	background: var(--brand, #3b82f6);
}
.trend-grades {
	white-space: nowrap;
	margin: 4rpx 0 8rpx;
}
.grade-chip {
	display: inline-flex;
	align-items: center;
	gap: 8rpx;
	padding: 8rpx 20rpx;
	margin-right: 12rpx;
	border-radius: 999rpx;
	background: var(--bg-soft, #f5f5f7);
	opacity: 0.45;
	transition: opacity 0.15s;
}
.grade-chip.active {
	opacity: 1;
	background: var(--bg-soft, #eef2ff);
}
.grade-dot {
	width: 16rpx;
	height: 16rpx;
	border-radius: 50%;
}
.grade-name {
	font-size: 24rpx;
	color: var(--text-main, #1f2937);
}
.trend-empty {
	padding: 96rpx 0;
	text-align: center;
	color: var(--text-sub, #6b7280);
	font-size: 26rpx;
}
.trend-chart-box {
	margin-top: 4rpx;
}
.trend-canvas {
	display: block;
}
.trend-tip {
	display: block;
	margin-top: 10rpx;
	text-align: center;
	font-size: 22rpx;
	color: var(--text-sub, #9ca3af);
}
</style>
