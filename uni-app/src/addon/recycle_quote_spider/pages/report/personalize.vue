<template>
	<view class="personalize">
		<!-- 报价单切换 -->
		<scroll-view scroll-x class="sheet-tabs">
			<text
				v-for="(s, i) in selectedSheets"
				:key="s.id"
				class="sheet-tab"
				:class="{ active: i === activeIdx }"
				@click="switchSheet(i)"
			>{{ s.name }}</text>
		</scroll-view>

		<!-- 选择型号 -->
		<view class="model-bar" @click="openPicker">
			<text class="model-bar-label">选择型号</text>
			<view class="model-bar-chips">
				<text v-for="m in selectedModels.slice(0, 2)" :key="m" class="mini-chip">{{ m }}</text>
				<text v-if="selectedModels.length > 2" class="mini-chip more">…</text>
				<text v-if="selectedModels.length === 0" class="model-bar-ph">全部型号</text>
			</view>
			<text class="model-bar-arrow">▾</text>
		</view>

		<view v-if="loadingDetail" class="empty">加载中…</view>
		<view v-else-if="!modelGroups.length" class="empty">没有可调整的型号</view>

		<view v-else class="groups">
			<view v-for="group in modelGroups" :key="group.model" class="model-group">
				<!-- 型号头：快捷加减 -->
				<view class="model-head">
					<text class="model-name">{{ group.model }}</text>
					<view class="model-ops">
						<text class="op plus" @click="bumpModel(group, step)">+{{ step }}</text>
						<text class="op minus" @click="bumpModel(group, -step)">−{{ step }}</text>
						<text class="op custom" @click="customStep">⚙ 自定义</text>
					</view>
				</view>

				<!-- 每个内存一卡 -->
				<view v-for="row in group.rows" :key="row.rowId" class="cap-card">
					<view class="cap-title">{{ row.capacity || '默认' }}</view>
					<view v-for="(col, c) in cols" :key="c" class="grade-line">
						<text class="grade-desc">{{ col }}</text>
						<input class="grade-input" type="number" :value="row.prices[c] === '-' ? '' : row.prices[c]" @input="onInput(row, c, $event)" />
						<text class="grade-mark" :class="{ on: row.marks[c] }" @click="toggleMark(row, c)">{{ row.marks[c] ? '已标优势' : '标记优势' }}</text>
					</view>
				</view>
			</view>
		</view>

		<view class="footer" @touchmove.stop.prevent>
			<view class="footer-btn ghost" @click="goBack">返回</view>
			<view class="footer-btn primary" @click="goPreview">生成报价单</view>
		</view>

		<!-- 型号多选弹层 -->
		<view v-if="pickerVisible" class="picker-mask" @click="pickerVisible = false">
			<view class="picker-panel" @click.stop @touchmove.stop>
				<view class="picker-head">
					<text class="picker-title">选择型号</text>
					<text class="picker-close" @click="pickerVisible = false">✕</text>
				</view>
				<scroll-view scroll-y class="picker-body">
					<view class="picker-flow">
						<text
							v-for="m in allModels"
							:key="m"
							class="pick-chip"
							:class="{ on: draft.includes(m) }"
							@click="toggleDraft(m)"
						>{{ m }}</text>
					</view>
				</scroll-view>
				<view class="picker-foot">
					<view class="pick-btn ghost" @click="draft = []">清空已选</view>
					<view class="pick-btn primary" @click="confirmPicker">确认已选型号</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { useQuoteReport } from '@/addon/recycle_quote_spider/composables/useQuoteReport'

const { sheets, ensureDetail, sheetColumns, sheetRows } = useQuoteReport()

const activeIdx = ref(0)
const loadingDetail = ref(false)
const step = ref(50)
const pickerVisible = ref(false)
const draft = ref<string[]>([])

const selectedSheets = computed(() => sheets.value.filter((s: any) => s.selected))
const activeSheet = computed(() => selectedSheets.value[activeIdx.value] || null)
const cols = computed(() => (activeSheet.value?.detail ? sheetColumns(activeSheet.value.detail) : []))
// 选中型号持久化在 sheet 上（空=全部），出图按它过滤
const selectedModels = computed<string[]>(() => activeSheet.value?.selectedModels || [])

const allRows = computed(() => {
	const s = activeSheet.value
	if (!s || !s.detail) return []
	return sheetRows(s.detail, s.adjustType, s.adjustValue, s.overrides, s.marks)
})
const allModels = computed(() => {
	const seen: string[] = []
	allRows.value.forEach((r: any) => {
		if (!seen.includes(r.model)) seen.push(r.model)
	})
	return seen
})
const modelGroups = computed(() => {
	const sel = new Set(selectedModels.value)
	const map = new Map<string, any>()
	const groups: any[] = []
	allRows.value.forEach((r: any) => {
		if (sel.size && !sel.has(r.model)) return
		let g = map.get(r.model)
		if (!g) {
			g = { model: r.model, rows: [] }
			map.set(r.model, g)
			groups.push(g)
		}
		g.rows.push(r)
	})
	return groups
})

async function ensureActive() {
	const s = activeSheet.value
	if (!s) return
	if (!s.detail) {
		loadingDetail.value = true
		try {
			await ensureDetail(s)
		} finally {
			loadingDetail.value = false
		}
	}
}

function switchSheet(i: number) {
	activeIdx.value = i
	ensureActive()
}

function setOverride(rowId: string, col: string, val: any) {
	const s = activeSheet.value
	if (!s) return
	if (!s.overrides[rowId]) s.overrides[rowId] = {}
	if (val === '' || val === null || val === undefined) delete s.overrides[rowId][col]
	else s.overrides[rowId][col] = val
}
function onInput(row: any, c: number, e: any) {
	setOverride(row.rowId, cols.value[c], e.detail.value)
}
function bumpModel(group: any, delta: number) {
	group.rows.forEach((r: any) => {
		cols.value.forEach((col, c) => {
			if (r.prices[c] === '-') return
			const cur = Number(r.prices[c]) || 0
			setOverride(r.rowId, col, Math.max(0, cur + delta))
		})
	})
}
function toggleMark(row: any, c: number) {
	const s = activeSheet.value
	if (!s) return
	const col = cols.value[c]
	if (!s.marks[row.rowId]) s.marks[row.rowId] = {}
	s.marks[row.rowId][col] = !s.marks[row.rowId][col]
}
function customStep() {
	uni.showModal({
		title: '自定义快捷金额',
		editable: true,
		placeholderText: '如 100',
		content: String(step.value),
		success: r => {
			if (r.confirm) {
				const v = Math.round(Number(r.content))
				if (Number.isFinite(v) && v > 0) step.value = v
			}
		}
	})
}

function openPicker() {
	draft.value = [...selectedModels.value]
	pickerVisible.value = true
}
function toggleDraft(m: string) {
	const i = draft.value.indexOf(m)
	if (i >= 0) draft.value.splice(i, 1)
	else draft.value.push(m)
}
function confirmPicker() {
	if (activeSheet.value) activeSheet.value.selectedModels = [...draft.value]
	pickerVisible.value = false
}
function goBack() {
	uni.navigateBack()
}
function goPreview() {
	uni.navigateTo({ url: '/addon/recycle_quote_spider/pages/report/preview' })
}

onLoad(() => {
	ensureActive()
})
</script>

<style lang="scss" scoped>
.personalize {
	min-height: 100vh;
	padding: 0 0 calc(140rpx + env(safe-area-inset-bottom));
	background: #f5f6f8;
	overflow-x: hidden;
	box-sizing: border-box;
}
/* 统一 box-sizing，避免带 padding/border 的元素超宽导致横向漂移 */
.personalize view,
.personalize input,
.personalize text,
.personalize scroll-view {
	box-sizing: border-box;
}
.sheet-tabs {
	white-space: nowrap;
	background: #fff;
	padding: 16rpx 20rpx;
}
.sheet-tab {
	display: inline-block;
	padding: 10rpx 24rpx;
	margin-right: 12rpx;
	font-size: 26rpx;
	color: #666;
}
.sheet-tab.active {
	color: #1f2937;
	font-weight: 700;
	border-bottom: 4rpx solid #1f2937;
}
.model-bar {
	display: flex;
	align-items: center;
	gap: 16rpx;
	background: #fff;
	margin-top: 2rpx;
	padding: 20rpx 24rpx;
}
.model-bar-label {
	font-size: 26rpx;
	color: #6b7280;
	flex-shrink: 0;
}
.model-bar-chips {
	flex: 1;
	display: flex;
	align-items: center;
	gap: 10rpx;
	overflow: hidden;
}
.mini-chip {
	max-width: 220rpx;
	padding: 6rpx 18rpx;
	font-size: 24rpx;
	color: #374151;
	background: #eef1f4;
	border-radius: 10rpx;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.mini-chip.more {
	padding: 6rpx 14rpx;
}
.model-bar-ph {
	font-size: 24rpx;
	color: #9aa0aa;
}
.model-bar-arrow {
	color: #9aa0aa;
	font-size: 28rpx;
}
.empty {
	text-align: center;
	color: #9aa0aa;
	font-size: 26rpx;
	padding: 80rpx 0;
}
.groups {
	padding: 16rpx 20rpx;
}
.model-group {
	margin-bottom: 24rpx;
}
.model-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12rpx;
	padding: 8rpx 4rpx 16rpx;
}
.model-name {
	font-size: 28rpx;
	font-weight: 700;
	color: #1f2937;
	flex: 1;
	min-width: 0;
}
.model-ops {
	display: flex;
	align-items: center;
	gap: 12rpx;
	flex-shrink: 0;
}
.op {
	padding: 8rpx 22rpx;
	font-size: 24rpx;
	border-radius: 10rpx;
	color: #fff;
	background: #1f2937;
}
.op.custom {
	color: #374151;
	background: #eef1f4;
}
.cap-card {
	background: #fff;
	border-radius: 16rpx;
	padding: 8rpx 20rpx 16rpx;
	margin-bottom: 16rpx;
}
.cap-title {
	text-align: center;
	font-size: 26rpx;
	font-weight: 700;
	color: #1f2937;
	padding: 16rpx 0;
	border-bottom: 1rpx solid #f0f1f3;
	margin-bottom: 8rpx;
}
.grade-line {
	display: flex;
	align-items: center;
	gap: 14rpx;
	padding: 14rpx 0;
	border-bottom: 1rpx solid #f5f6f8;
}
.grade-desc {
	flex: 1;
	min-width: 0;
	font-size: 24rpx;
	line-height: 32rpx;
	color: #4b5563;
}
.grade-input {
	width: 180rpx;
	height: 64rpx;
	text-align: center;
	font-size: 28rpx;
	font-weight: 700;
	color: #1f2937;
	background: #fff;
	border: 1rpx solid #dfe3e8;
	border-radius: 10rpx;
	flex-shrink: 0;
}
.grade-mark {
	width: 150rpx;
	height: 64rpx;
	line-height: 64rpx;
	text-align: center;
	font-size: 24rpx;
	color: #9aa0aa;
	background: #f2f3f5;
	border-radius: 10rpx;
	flex-shrink: 0;
}
.grade-mark.on {
	color: #fff;
	background: #1f2937;
}
.footer {
	position: fixed;
	left: 0;
	right: 0;
	bottom: 0;
	display: flex;
	gap: 20rpx;
	padding: 16rpx 24rpx calc(16rpx + env(safe-area-inset-bottom));
	background: #fff;
	box-shadow: 0 -4rpx 16rpx rgba(0, 0, 0, 0.05);
}
.footer-btn {
	flex: 1;
	height: 88rpx;
	border-radius: 999rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 30rpx;
	font-weight: 600;
}
.footer-btn.ghost {
	background: #f0f1f3;
	color: #374151;
}
.footer-btn.primary {
	background: #1f2937;
	color: #fff;
}
.picker-mask {
	position: fixed;
	inset: 0;
	background: rgba(0, 0, 0, 0.45);
	z-index: 50;
	display: flex;
	align-items: flex-end;
}
.picker-panel {
	width: 100%;
	max-height: 80vh;
	background: #fff;
	border-radius: 24rpx 24rpx 0 0;
	display: flex;
	flex-direction: column;
	padding-bottom: calc(16rpx + env(safe-area-inset-bottom));
}
.picker-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 24rpx 28rpx;
	border-bottom: 1rpx solid #f0f1f3;
}
.picker-title {
	font-size: 30rpx;
	font-weight: 700;
	color: #1f2937;
}
.picker-close {
	font-size: 30rpx;
	color: #9aa0aa;
}
.picker-body {
	flex: 1;
	max-height: 56vh;
	padding: 20rpx 28rpx;
}
.picker-flow {
	display: flex;
	flex-wrap: wrap;
	gap: 16rpx;
}
.pick-chip {
	padding: 12rpx 24rpx;
	font-size: 26rpx;
	color: #374151;
	background: #f2f3f5;
	border-radius: 12rpx;
}
.pick-chip.on {
	color: #fff;
	background: #1f2937;
}
.picker-foot {
	display: flex;
	gap: 20rpx;
	padding: 16rpx 28rpx 0;
}
.pick-btn {
	flex: 1;
	height: 84rpx;
	border-radius: 999rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 28rpx;
	font-weight: 600;
}
.pick-btn.ghost {
	background: #f0f1f3;
	color: #374151;
}
.pick-btn.primary {
	background: #1f2937;
	color: #fff;
}
</style>
