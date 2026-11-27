<template>
	<view class="report-config">
		<view class="cfg-block">
			<view class="cfg-row">
				<text class="cfg-label">报价单水印</text>
				<input v-model="watermark" class="cfg-input" placeholder="如店名/手机号，留空则不加水印" />
			</view>
			<view class="wm-settings">
				<view class="wm-row">
					<text class="wm-label">大小</text>
					<slider class="wm-slider" :value="wm.size" :min="20" :max="60" :step="2" show-value @changing="wm.size = $event.detail.value" @change="wm.size = $event.detail.value" />
				</view>
				<view class="wm-row">
					<text class="wm-label">深浅</text>
					<slider class="wm-slider" :value="wm.opacity" :min="2" :max="30" :step="1" show-value @changing="wm.opacity = $event.detail.value" @change="wm.opacity = $event.detail.value" />
				</view>
				<view class="wm-row">
					<text class="wm-label">角度</text>
					<slider class="wm-slider" :value="wm.angle" :min="0" :max="60" :step="5" show-value @changing="wm.angle = $event.detail.value" @change="wm.angle = $event.detail.value" />
				</view>
				<view class="wm-row">
					<text class="wm-label">颜色</text>
					<view class="wm-colors">
						<view v-for="c in wmColors" :key="c" class="wm-dot" :class="{ on: wm.color === c }" :style="{ background: c }" @click="wm.color = c"></view>
					</view>
				</view>
			</view>
		</view>

		<view class="cfg-block">
			<text class="cfg-label">报价单颜色</text>
			<view class="theme-grid">
				<view
					v-for="t in REPORT_THEMES"
					:key="t.key"
					class="theme-chip"
					:class="{ active: themeKey === t.key }"
					:style="{ background: t.bannerGrad ? `linear-gradient(135deg, ${t.bannerGrad[0]}, ${t.bannerGrad[1]})` : t.bannerBg, color: t.bannerText }"
					@click="setTheme(t.key)"
				>
					<text>{{ t.name }}</text>
					<text v-if="themeKey === t.key" class="theme-check">✓</text>
				</view>
			</view>
		</view>

		<view v-if="loading" class="cfg-loading">加载报价单中…</view>

		<view v-for="(sheet, i) in sheets" :key="sheet.id" class="cfg-block sheet-card">
			<view class="sheet-head" @click="sheet.selected = !sheet.selected">
				<view class="sheet-check" :class="{ on: sheet.selected }">{{ sheet.selected ? '✓' : '' }}</view>
				<view class="sheet-head-info">
					<text class="sheet-title">{{ sheet.brand && !sheet.name.includes(sheet.brand) ? sheet.brand + ' · ' + sheet.name : sheet.name }}</text>
					<text class="sheet-sub">{{ sheetMeta(sheet) }}</text>
				</view>
			</view>
			<view class="cfg-row" :class="{ disabled: !sheet.selected }">
				<text class="cfg-label">整单修改</text>
				<view class="adjust-line">
					<picker mode="selector" :range="adjustOptions" :value="adjustIndex(sheet)" @change="onAdjustType(sheet, $event)">
						<view class="picker-box">{{ adjustOptions[adjustIndex(sheet)] }}</view>
					</picker>
					<input
						v-if="sheet.adjustType !== 'none'"
						v-model.number="sheet.adjustValue"
						type="digit"
						class="adjust-input"
						:placeholder="isRatio(sheet) ? '百分比' : '金额'"
					/>
					<text v-if="sheet.adjustType !== 'none'" class="adjust-unit">{{ isRatio(sheet) ? '%' : '元' }}</text>
					<text v-if="isRatio(sheet)" class="adjust-tip">按比例上调 / 下调，结果均自动取 10 的整数</text>
				</view>
			</view>
		</view>

		<view class="cfg-footer" @touchmove.stop.prevent>
			<view class="footer-btn ghost" @click="goPersonalize">个性化调整</view>
			<view class="footer-btn primary" :class="{ disabled: selectedCount === 0 }" @click="goPreview">生成报价单({{ selectedCount }})</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { useQuoteReport } from '@/addon/recycle_quote_spider/composables/useQuoteReport'

const { watermark, wm, themeKey, sheets, loading, REPORT_THEMES, setTheme, load } = useQuoteReport()
const wmColors = ['#000000', '#ffffff', '#e6402d', '#2c6dd2', '#8a6a37', '#149e7e']

const adjustOptions = ['不调整', '按金额下调', '按金额上调', '按比例下调', '按比例上调']
const adjustTypeMap = ['none', 'down', 'up', 'ratioDown', 'ratioUp'] as const

function isRatio(sheet: any) {
	return sheet.adjustType === 'ratioDown' || sheet.adjustType === 'ratioUp'
}
function adjustIndex(sheet: any) {
	return Math.max(0, adjustTypeMap.indexOf(sheet.adjustType))
}
function onAdjustType(sheet: any, e: any) {
	const idx = Number(e.detail.value || 0)
	sheet.adjustType = adjustTypeMap[idx] || 'none'
	sheet.adjustValue = 0
}
function sheetMeta(sheet: any) {
	if (!sheet.detail) return '点「生成报价单」时加载'
	const rows = Array.isArray(sheet.detail.rows) ? sheet.detail.rows.length : 0
	return `${rows} 个型号`
}

const selectedCount = computed(() => sheets.value.filter((s: any) => s.selected).length)

function goPreview() {
	if (selectedCount.value === 0) {
		uni.showToast({ title: '请至少选择一个报价单', icon: 'none' })
		return
	}
	uni.navigateTo({ url: '/addon/recycle_quote_spider/pages/report/preview' })
}
function goPersonalize() {
	if (selectedCount.value === 0) {
		uni.showToast({ title: '请至少选择一个报价单', icon: 'none' })
		return
	}
	uni.navigateTo({ url: '/addon/recycle_quote_spider/pages/report/personalize' })
}

onLoad((options: any) => {
	const id = options?.id || options?.item_id || 0
	if (id) load(id, options?.source || 'spider')
})
</script>

<style lang="scss" scoped>
.report-config {
	min-height: 100vh;
	padding: 24rpx 24rpx calc(160rpx + env(safe-area-inset-bottom));
	background: #f5f6f8;
	box-sizing: border-box;
}
.cfg-block {
	background: #fff;
	border-radius: 20rpx;
	padding: 24rpx;
	margin-bottom: 20rpx;
}
.cfg-loading {
	text-align: center;
	color: #9aa0aa;
	font-size: 26rpx;
	padding: 20rpx;
}
.cfg-label {
	font-size: 28rpx;
	font-weight: 600;
	color: #1f2937;
}
.cfg-row {
	display: flex;
	align-items: center;
	gap: 16rpx;
}
.cfg-row.disabled {
	opacity: 0.4;
}
.cfg-input {
	flex: 1;
	height: 64rpx;
	padding: 0 20rpx;
	background: #f5f6f8;
	border-radius: 12rpx;
	font-size: 26rpx;
}
.wm-row {
	display: flex;
	align-items: center;
	gap: 16rpx;
	margin-top: 16rpx;
}
.wm-label {
	width: 72rpx;
	font-size: 24rpx;
	color: #6b7280;
	flex-shrink: 0;
}
.wm-slider {
	flex: 1;
	margin: 0 8rpx;
}
.wm-colors {
	display: flex;
	gap: 16rpx;
	align-items: center;
}
.wm-dot {
	width: 44rpx;
	height: 44rpx;
	border-radius: 50%;
	border: 2rpx solid #e5e7eb;
	box-sizing: border-box;
}
.wm-dot.on {
	outline: 4rpx solid rgba(31, 41, 55, 0.35);
}
.theme-grid {
	display: flex;
	flex-wrap: wrap;
	gap: 16rpx;
	margin-top: 18rpx;
}
.theme-chip {
	position: relative;
	width: calc((100% - 32rpx) / 3);
	height: 72rpx;
	border-radius: 14rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 26rpx;
	font-weight: 600;
	box-sizing: border-box;
}
.theme-chip.active {
	outline: 4rpx solid rgba(31, 41, 55, 0.35);
}
.theme-check {
	position: absolute;
	top: 6rpx;
	right: 10rpx;
	font-size: 20rpx;
}
.sheet-head {
	display: flex;
	align-items: center;
	gap: 16rpx;
	margin-bottom: 18rpx;
}
.sheet-check {
	width: 40rpx;
	height: 40rpx;
	border-radius: 50%;
	border: 2rpx solid #c7ccd4;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #fff;
	font-size: 24rpx;
	flex-shrink: 0;
}
.sheet-check.on {
	background: #1f2937;
	border-color: #1f2937;
}
.sheet-head-info {
	min-width: 0;
}
.sheet-title {
	font-size: 30rpx;
	font-weight: 700;
	color: #1f2937;
}
.sheet-sub {
	display: block;
	margin-top: 4rpx;
	font-size: 24rpx;
	color: #9aa0aa;
}
.adjust-line {
	flex: 1;
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 12rpx;
}
.adjust-tip {
	width: 100%;
	margin-top: 4rpx;
	font-size: 22rpx;
	color: #9aa0aa;
}
.picker-box {
	height: 64rpx;
	line-height: 64rpx;
	padding: 0 20rpx;
	background: #f5f6f8;
	border-radius: 12rpx;
	font-size: 26rpx;
	color: #1f2937;
}
.adjust-input {
	width: 160rpx;
	height: 64rpx;
	padding: 0 20rpx;
	background: #f5f6f8;
	border-radius: 12rpx;
	font-size: 26rpx;
}
.adjust-unit {
	font-size: 26rpx;
	color: #6b7280;
}
.cfg-footer {
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
.footer-btn.disabled {
	opacity: 0.5;
}
</style>
