<template>
	<view class="show-price-page">
		<view class="page-bg"></view>

		<!-- 顶部导航栏 -->
		<view class="custom-navbar">
			<view class="navbar-content">
				<view class="navbar-left" @click="goBack">
					<text class="iconfont icon-left"></text>
				</view>
				<view class="navbar-title"> {{ priceTypeName }}{{  pageTitle }}</view>
				<view class="navbar-right" @click="loadPriceData">
					<text class="refresh-text">刷新</text>
				</view>
			</view>
		</view>

		<!-- 加载状态 -->
		<view v-if="loading" class="loading-container">
			<view class="skeleton-card"></view>
			<view class="skeleton-card"></view>
			<view class="skeleton-table"></view>
			<text class="loading-text">报价数据加载中...</text>
		</view>

		<!-- 空状态 -->
		<view v-else-if="groupedTables.length === 0" class="empty-container">
			<view class="empty-badge">暂无数据</view>
			<text class="empty-title">没有可展示的报价</text>
			<text class="empty-desc">你可以点击刷新重新拉取最新报价</text>
			<view class="empty-action" @click="loadPriceData">重新加载</view>
		</view>

		<!-- 报价数据 -->
		<view v-else class="price-content">
			<!-- 报价概览卡片 -->
			<view class="summary-card">
				<view class="summary-top">
					<text class="summary-tag">实时价格</text>
					<text class="summary-meta">报价日期 {{ priceDateDisplay }}</text>
				</view>
				
			</view>

			<!-- 多表格展示 -->
			<view class="tables-wrapper">
				<view
					v-for="table in groupedTables"
					:key="table.id"
					:id="`table-${table.id}`"
					class="table-container"
				>
					<scroll-view scroll-x="false" class="table-scroll" show-scrollbar="false">
						<view class="excel-table">
							<view class="table-header">
								<view class="header-cell col-model" :style="{ width: `${fixedColWidths.model}rpx` }">型号</view>
								<view class="header-cell col-capacity" :style="{ width: `${fixedColWidths.capacity}rpx` }">容量</view>
								<view
									v-for="config in table.configColumns"
									:key="config"
									class="header-cell col-price"
								>
									<text class="header-config-text">{{ config }}</text>
								</view>
								<view class="header-cell col-remark">备注</view>
							</view>

							<view class="table-body">
								<view
									v-for="(modelGroup, modelIdx) in getModelGroups(table.rows)"
									:key="modelIdx"
									class="model-group"
								>
									<view class="model-group-row">
										<view class="body-cell col-model model-merged" :style="{ width: `${fixedColWidths.model}rpx` }">
											<text class="cell-text model-name">{{ modelGroup.modelName }}</text>
										</view>

										<view class="data-area">
											<view class="data-columns">
												<view
													v-for="(row, rowIdx) in modelGroup.rows"
													:key="rowIdx"
													class="data-row"
												>
													<view class="body-cell col-capacity" :style="{ width: `${fixedColWidths.capacity}rpx` }">
														<text class="cell-text">{{ row.capacity || '--' }}</text>
													</view>

													<view
														v-for="config in table.configColumns"
														:key="config"
														class="body-cell col-price"
													>
														<view v-if="hasPriceValue(row.prices?.[config])" class="price-box">
															<text class="price-value">{{ formatPrice(row.prices?.[config]) }}</text>
														</view>
														<text v-else class="empty-cell">--</text>
													</view>
												</view>
											</view>

											<view class="remark-column">
												<view
													v-for="(row, rowIdx) in modelGroup.rows"
													:key="rowIdx"
													class="remark-cell-wrapper"
												>
													<view
														v-if="row.showRemark"
														class="body-cell col-remark remark-merged"
														:style="{ height: `${row.remarkRowspan * ROW_HEIGHT_RPX}rpx` }"
													>
														<text class="remark-text">{{ row.displayRemark || '--' }}</text>
													</view>
												</view>
											</view>
										</view>
									</view>
								</view>
							</view>
						</view>
					</scroll-view>
				</view>
			</view>
		</view>

		<view v-if="!loading" class="action-bar">
			<view class="action-bar-inner">
				<text class="action-tip">数据仅供参考，实际价格以最终评估为准</text>
				<view class="order-btn" @click="goToOrder">去下单</view>
			</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getQuotationPriceList, type QuotationPriceData } from '@/addon/recycle/api/quotation'

interface EnhancedPriceRow extends QuotationPriceData {
	showRemark?: boolean
	remarkRowspan?: number
	displayRemark?: string
}

interface GroupedTable {
	id: number
	configColumns: string[]
	rows: EnhancedPriceRow[]
}

interface PricePageOptions {
	id?: string
	title?: string
}

interface PriceListResponse {
	code: number
	msg?: string
	data?: QuotationPriceData[]
}

const ROW_HEIGHT_RPX = 88
const ORDER_PAGE_URL = '/addon/recycle/pages/order/order'

const priceTypeId = ref('')
const pageTitle = ref('报价查询')
const loading = ref(false)
const tableData = ref<QuotationPriceData[]>([])
const priceTypeName = ref('')

const groupedTables = computed<GroupedTable[]>(() => {
	if (tableData.value.length === 0) return []

	const configGroupMap = new Map<string, QuotationPriceData[]>()

	for (const row of tableData.value) {
		if (!row.prices) continue

		// 完全按接口返回顺序渲染，不做前端排序
		const configColumns = Object.keys(row.prices)
		const groupKey = configColumns.join('|||')

		if (!configGroupMap.has(groupKey)) {
			configGroupMap.set(groupKey, [])
		}
		configGroupMap.get(groupKey)!.push(row)
	}

	const tables: GroupedTable[] = []
	let idx = 1

	configGroupMap.forEach((rows, key) => {
		const normalizedRows = rows.map(row => ({ ...row }))

		tables.push({
			id: idx++,
			configColumns: key ? key.split('|||') : [],
			rows: normalizedRows
		})
	})

	return tables
})

const fixedColWidths = {
	model: 180,
	capacity: 85
}

const priceDateDisplay = computed(() => {
	const dateList = tableData.value
		.map(item => (item.price_date || '').trim())
		.filter(Boolean)

	if (dateList.length > 0) {
		// price_date 为 YYYY-MM-DD，按字符串排序即可得到最新日期
		return [...new Set(dateList)].sort().at(-1) || '--'
	}

	// 兜底：若接口未返回 price_date，则回退到 create_at
	const raw = tableData.value[0]?.create_at
	if (!raw) return '--'
	return formatDate(raw)
})

function getModelGroups(rows: EnhancedPriceRow[]): Array<{ modelName: string; rows: EnhancedPriceRow[] }> {
	const groups: Array<{ modelName: string; rows: EnhancedPriceRow[] }> = []
	let currentModelName = ''
	let currentGroup: { modelName: string; rows: EnhancedPriceRow[] } | null = null

	rows.forEach(row => {
		if (row.goods_name !== currentModelName) {
			if (currentGroup) {
				processRemarkRowspan(currentGroup.rows)
				groups.push(currentGroup)
			}
			currentModelName = row.goods_name
			currentGroup = {
				modelName: row.goods_name,
				rows: [row]
			}
		} else {
			currentGroup?.rows.push(row)
		}
	})

	if (currentGroup) {
		processRemarkRowspan(currentGroup.rows)
		groups.push(currentGroup)
	}

	return groups
}

function processRemarkRowspan(rows: EnhancedPriceRow[]) {
	let currentRemark = ''
	let remarkStartIndex = 0

	rows.forEach((row, index) => {
		const remark = row.value_info?.trim() || ''
		if (remark !== currentRemark) {
			if (remarkStartIndex < index) {
				for (let i = remarkStartIndex; i < index; i++) {
					rows[i].remarkRowspan = index - remarkStartIndex
				}
			}
			currentRemark = remark
			remarkStartIndex = index
			row.showRemark = true
			row.remarkRowspan = 1
			row.displayRemark = remark || '--'
		} else {
			row.showRemark = false
			row.remarkRowspan = 0
		}
	})

	if (remarkStartIndex < rows.length) {
		for (let i = remarkStartIndex; i < rows.length; i++) {
			rows[i].remarkRowspan = rows.length - remarkStartIndex
		}
	}
}

function isRecord(value: unknown): value is Record<string, unknown> {
	return typeof value === 'object' && value !== null
}

function parsePriceValue(value: unknown): number | null {
	if (value === null || value === undefined || value === '') return null

	if (typeof value === 'number' || typeof value === 'string') {
		const num = Number(value)
		return Number.isFinite(num) ? num : null
	}

	if (isRecord(value)) {
		const candidates = [value.final, value.price, value.value, value.current, value.original]
		for (const candidate of candidates) {
			if (candidate === null || candidate === undefined || candidate === '') continue
			const num = Number(candidate)
			if (Number.isFinite(num)) return num
		}
	}

	return null
}

function hasPriceValue(value: unknown): boolean {
	return parsePriceValue(value) !== null
}

function formatPrice(value: unknown): string {
	const num = parsePriceValue(value)
	if (num === null) return '--'

	// 按需求去掉小数点，仅显示整数价格
	return `${Math.trunc(num)}`
}

function formatDate(timestamp: number | string): string {
	if (!timestamp) return '--'
	const raw = Number(timestamp)
	const ms = String(raw).length === 10 ? raw * 1000 : raw
	const date = new Date(ms)
	if (Number.isNaN(date.getTime())) return '--'

	const year = date.getFullYear()
	const month = `${date.getMonth() + 1}`.padStart(2, '0')
	const day = `${date.getDate()}`.padStart(2, '0')
	const hour = `${date.getHours()}`.padStart(2, '0')
	const minute = `${date.getMinutes()}`.padStart(2, '0')

	return `${year}-${month}-${day} ${hour}:${minute}`
}

async function loadPriceData() {
	if (!priceTypeId.value) {
		uni.showToast({ title: '参数错误', icon: 'none' })
		return
	}

	loading.value = true
	try {
		const res = (await getQuotationPriceList({
			quotation_id: priceTypeId.value,
			is_current: 1
		})) as PriceListResponse

		if (res.code === 1 && res.data) {
			tableData.value = res.data || []
			if (tableData.value.length > 0) {
				priceTypeName.value = tableData.value[0].price_name || ''
			}
		} else {
			uni.showToast({ title: res.msg || '加载失败', icon: 'none' })
		}
	} catch (error: unknown) {
		console.error('加载报价数据失败:', error)
		uni.showToast({
			title: getErrorMessage(error),
			icon: 'none'
		})
	} finally {
		loading.value = false
	}
}

function goToOrder() {
	const query = priceTypeId.value ? `?quotation_id=${encodeURIComponent(priceTypeId.value)}` : ''
	uni.navigateTo({ url: `${ORDER_PAGE_URL}${query}` })
}

function goBack() {
	uni.navigateBack()
}

function safeDecode(value: string): string {
	try {
		return decodeURIComponent(value)
	} catch (error) {
		return value
	}
}

function getErrorMessage(error: unknown): string {
	if (isRecord(error) && typeof error.msg === 'string' && error.msg.trim()) {
		return error.msg
	}
	return '加载失败'
}

onLoad((options: PricePageOptions) => {
	if (options?.id) {
		priceTypeId.value = options.id
		pageTitle.value = options?.title ? safeDecode(options.title) : '报价查询'
		loadPriceData()
		return
	}

	uni.showToast({
		title: '缺少参数',
		icon: 'none'
	})
})
</script>

<style lang="scss" scoped>
.show-price-page {
	--bg-main: #f3f4f6;
	--bg-card: #ffffff;
	--bg-soft: #f7f7f8;
	--line: #e5e7eb;
	--text-main: #1f2937;
	--text-sub: #6b7280;
	--brand: #3b82f6;
	--brand-deep: #4f46e5;
	--price: #2563eb;
	--warning-bg: #eff6ff;
	--radius: 20rpx;
	--shadow: 0 10rpx 24rpx rgba(31, 41, 55, 0.08);
	--table-font-size: 22rpx;
	--table-price-font-size: 24rpx;
	--table-remark-font-size: 0.44rem;
	--table-row-height: 88rpx;
	--table-cell-padding-y: 8rpx;
	--table-cell-padding-x: 2rpx;
	--table-remark-padding: 0.08rem 0.12rem;
	min-height: 100vh;
	background: var(--bg-main);
	font-family: 'DIN Alternate', 'PingFang SC', 'Helvetica Neue', sans-serif;
	color: var(--text-main);
	position: relative;
}

.page-bg {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	height: 420rpx;
	background: linear-gradient(180deg, #f9fafb 0%, #f3f4f6 70%, rgba(243, 244, 246, 0) 100%);
	z-index: 0;
	pointer-events: none;
}

.custom-navbar {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	z-index: 999;
	backdrop-filter: blur(8px);
	background: rgba(255, 255, 255, 0.9);
	border-bottom: 1rpx solid rgba(59, 130, 246, 0.16);

	.navbar-content {
		display: flex;
		align-items: center;
		justify-content: space-between;
		height: 88rpx;
		padding: env(safe-area-inset-top) 24rpx 0;
	}

	.navbar-left,
	.navbar-right {
		width: 96rpx;
		height: 64rpx;
		display: flex;
		align-items: center;
	}

	.navbar-left {
		.iconfont {
			font-size: 40rpx;
			color: var(--text-main);
		}
	}

	.navbar-right {
		justify-content: flex-end;

		.refresh-text {
			font-size: 24rpx;
			color: var(--brand);
			padding: 10rpx 14rpx;
			background: rgba(59, 130, 246, 0.12);
			border-radius: 999rpx;
		}
	}

	.navbar-title {
		flex: 1;
		text-align: center;
		font-size: 32rpx;
		font-weight: 700;
		letter-spacing: 1rpx;
	}
}

.price-content,
.loading-container,
.empty-container {
	position: relative;
	z-index: 1;
	padding: calc(88rpx + env(safe-area-inset-top) + 24rpx) 20rpx calc(160rpx + env(safe-area-inset-bottom));
}

.loading-container {
	display: flex;
	flex-direction: column;
	gap: 20rpx;

	.skeleton-card,
	.skeleton-table {
		border-radius: var(--radius);
		background: linear-gradient(100deg, #eef1f4 30%, #f8f9fb 45%, #eef1f4 60%);
		background-size: 260% 100%;
		animation: skeleton-shimmer 1.2s linear infinite;
		border: 1rpx solid #e5e7eb;
	}

	.skeleton-card {
		height: 176rpx;
	}

	.skeleton-table {
		height: 720rpx;
	}

	.loading-text {
		font-size: 24rpx;
		color: var(--text-sub);
		text-align: center;
		margin-top: 12rpx;
	}
}

.empty-container {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding-top: calc(88rpx + env(safe-area-inset-top) + 120rpx);

	.empty-badge {
		font-size: 22rpx;
		color: var(--brand-deep);
		padding: 10rpx 22rpx;
		border-radius: 999rpx;
		background: rgba(59, 130, 246, 0.12);
		margin-bottom: 28rpx;
	}

	.empty-title {
		font-size: 36rpx;
		font-weight: 700;
		color: var(--text-main);
		margin-bottom: 10rpx;
	}

	.empty-desc {
		font-size: 26rpx;
		color: var(--text-sub);
	}

	.empty-action {
		margin-top: 36rpx;
		background: linear-gradient(120deg, #4f46e5, #3b82f6, #0ea5e9);
		color: #fff;
		font-size: 28rpx;
		font-weight: 600;
		padding: 18rpx 56rpx;
		border-radius: 999rpx;
		box-shadow: 0 10rpx 20rpx rgba(59, 130, 246, 0.28);
	}
}

.summary-card {
	padding: 26rpx;
	border-radius: 22rpx;
	background: #ffffff;
	box-shadow: var(--shadow);
	border: 1rpx solid rgba(59, 130, 246, 0.16);

	animation: rise-in 320ms ease-out;

	.summary-top {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.summary-tag {
		padding: 8rpx 20rpx;
		border-radius: 999rpx;
		background: rgba(59, 130, 246, 0.12);
		color: var(--brand-deep);
		font-size: 22rpx;
		font-weight: 600;
	}

	.summary-meta {
		font-size: 22rpx;
		color: var(--text-sub);
	}

	.summary-title {
		font-size: 40rpx;
		font-weight: 700;
		line-height: 1.25;
		margin-top: 20rpx;
		color: var(--text-main);
	}

}

.tables-wrapper {
	margin-top: 12rpx;
	display: flex;
	flex-direction: column;
	gap: 10rpx;
}

.table-container {
	background: var(--bg-card);
	border: 1rpx solid rgba(59, 130, 246, 0.14);
	border-radius: 18rpx;
	overflow: hidden;
	box-shadow: 0 8rpx 22rpx rgba(31, 41, 55, 0.06);
	animation: rise-in 360ms ease-out;
}

.table-scroll {
	width: 100%;
}

.excel-table {
	width: 100%;
}

.table-header {
	display: flex;
	background: #eff6ff;
	border-bottom: 1rpx solid var(--line);
	position: sticky;
	top: 0;
	z-index: 9;
}

.table-body .model-group {
	border-bottom: 1rpx solid #eceff3;
}

.table-body .model-group:last-child {
	border-bottom: none;
}

.model-group-row {
	display: flex;
	align-items: stretch;
}

.data-area {
	flex: 1;
	display: flex;
	position: relative;
}

.data-columns {
	flex: 1;
	display: flex;
	flex-direction: column;
}

.data-row {
	display: flex;
	height: var(--table-row-height);
	min-height: var(--table-row-height);
	border-bottom: 1rpx solid #eef1f4;
	background: #fff;
}

.data-row:nth-child(2n) {
	background: #fafbfc;
}

.data-row:last-child {
	border-bottom: none;
}

.remark-column {
	width: 5rem;
	min-width: 5rem;
	max-width: 5rem;
	flex-shrink: 0;
	display: flex;
	flex-direction: column;
	position: relative;

	background: #fff;
}

.remark-cell-wrapper {
	height: var(--table-row-height);
	min-height: var(--table-row-height);
	position: relative;
}

.header-cell,
.body-cell {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: var(--table-cell-padding-y) var(--table-cell-padding-x);
	font-size: var(--table-font-size);
	box-sizing: border-box;
	border-right: 1rpx solid #eceff3;
	word-break: break-word;
	white-space: normal;
	text-align: center;
}

.header-cell:last-child,
.body-cell:last-child {
	border-right: none;
}

.header-cell {
	font-weight: 700;
	font-size: var(--table-font-size);
	color: #374151;
}

.body-cell {
	color: #4b5563;
	min-height: var(--table-row-height);
	height: auto;
}

.data-row > .body-cell {
	height: 100%;
	min-height: 100%;
}

.col-model {
	flex-shrink: 0;
	background: #fafafa;
}

.col-capacity {
	flex-shrink: 0;
}

.col-price {
	flex: 1 1 0;
	min-width: 2.2em;
	max-width: 150rpx;
}

.col-remark {
	width: 5rem;
	min-width: 5rem;
	max-width: 5rem;
	flex-shrink: 0;
	background: var(--warning-bg);
}

.model-merged {
	display: flex;
	align-self: stretch;
	align-items: center;
	justify-content: center;
	height: auto;
	min-height: 100%;
	padding: 0;
}

.model-name {
	display: block;
	width: 100%;
	text-align: center;
	font-weight: 700;
	line-height: 1.35;
}

.remark-merged {
	display: flex;
	align-items: flex-start;
	justify-content: flex-start;
	border-bottom: 1rpx solid #ebe4e2;
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	width: 100%;
	padding: var(--table-remark-padding);
	background: var(--warning-bg);
	box-sizing: border-box;
	overflow: hidden;
}

.cell-text {
	word-break: break-word;
}

.price-box {
	width: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
}

.price-value {
	font-size: var(--table-price-font-size);
	font-weight: 700;
	color: var(--price);
	line-height: 1.2;
	white-space: nowrap;
	word-break: normal;
	text-align: center;
}

.empty-cell {
	color: #94a3b8;
	font-size: 24rpx;
}

.remark-text {
	font-size: var(--table-remark-font-size);
	line-height: 1.45;
	color: #6b7280;
	word-break: break-word;
	white-space: pre-wrap;
	text-align: left;
}

.header-config-text {
	display: block;
	width: 100%;
	min-width: 2em;
	white-space: normal;
	word-break: break-all;
	line-height: 1.3;
	text-align: center;
}

.action-bar {
	position: fixed;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 1000;
	padding: 12rpx 20rpx calc(12rpx + env(safe-area-inset-bottom));
	background: rgba(255, 255, 255, 0.95);
	backdrop-filter: blur(8px);
	border-top: 1rpx solid rgba(59, 130, 246, 0.16);
	box-shadow: 0 -6rpx 20rpx rgba(31, 41, 55, 0.08);
}

.action-bar-inner {
	display: flex;
	align-items: center;
	gap: 16rpx;
}

.action-tip {
	flex: 1;
	font-size: 22rpx;
	color: var(--text-sub);
}

.order-btn {
	flex-shrink: 0;
	min-width: 190rpx;
	height: 72rpx;
	line-height: 72rpx;
	text-align: center;
	font-size: 28rpx;
	font-weight: 700;
	color: #fff;
	border-radius: 999rpx;
	background: linear-gradient(120deg, #4f46e5, #3b82f6, #0ea5e9);
	box-shadow: 0 8rpx 18rpx rgba(59, 130, 246, 0.32);
}

@keyframes skeleton-shimmer {
	0% {
		background-position: 200% 0;
	}
	100% {
		background-position: -60% 0;
	}
}

@keyframes rise-in {
	0% {
		opacity: 0;
		transform: translateY(14rpx);
	}
	100% {
		opacity: 1;
		transform: translateY(0);
	}
}

</style>
