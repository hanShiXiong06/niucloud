<template>
	<view class="show-price-page" :style="[pageStyleVars, themeStyleVars]">
		<view class="custom-navbar" :class="{ compact: isScrolled }" :style="customNavbarStyle">
			<view class="navbar-content" :style="navbarContentStyle">
				<view class="navbar-left" :style="navbarSideStyle" @click="goBack">
					<u-icon name="arrow-left" color="#ffffff" size="42rpx"></u-icon>
				</view>
				<view class="navbar-center" :style="navbarCenterStyle">
					<text class="navbar-title">{{ navbarTitle }}</text>
					<text v-if="isScrolled" class="navbar-subtitle">{{ priceDateDisplay }}</text>
				</view>
				<view class="navbar-capsule-space" :style="navbarSideStyle"></view>
			</view>
		</view>

		<!-- 加载状态 -->
		<view v-if="loading" class="loading-container">
			<view class="skeleton-hero"></view>
			<view class="skeleton-card"></view>
			<view class="skeleton-card large"></view>
			<text class="loading-text">报价数据加载中...</text>
		</view>

		<!-- 空状态 -->
		<view v-else-if="tableData.length === 0 && !spiderImageUrl" class="empty-container">
			<view class="empty-badge">暂无数据</view>
			<text class="empty-title">没有可展示的报价</text>
			<text class="empty-desc">你可以点击刷新重新拉取最新报价</text>
			<view class="empty-action" @click="loadPriceData">重新加载</view>
		</view>

		<!-- 报价数据 -->
		<view v-else class="price-content">
			<view class="notice-card">
				<text v-for="line in noticeLines" :key="line" class="notice-line">{{ line }}</text>
			</view>

			<!-- <view v-if="spiderImageUrl" class="image-quote-card">
				<image class="image-quote" :src="spiderImageUrl" mode="widthFix" @click="previewSpiderImage" />
			</view> -->

			<view v-if="tableData.length > 0" class="tool-card">
				<view class="tool-head">
					<view class="data-meta">
						<view v-if="showSpiderHotBadge" class="spider-hot-badge" :style="hotBadgeBoxStyle">
							<image v-if="hotBadgeImage" class="spider-hot-badge__image" :src="img(hotBadgeImage)" mode="aspectFit"></image>
							<text v-else class="spider-hot-badge__text" :style="hotBadgeTextStyle">热门</text>
						</view>
						<text>共 {{ filteredModelCount }} 个型号</text>
						<text class="dot">·</text>
						<text>{{ filteredRowCount }} 条价格</text>
					</view>
					<view class="tool-actions">
						<view class="tool-action" :class="{ active: selectedModels.length > 0 }" @click="showModelFilter = true">
							型号筛选{{ selectedModels.length ? `(${selectedModels.length})` : '' }}
						</view>
						<view class="tool-action" @click="openTypeSheet">切换报价单</view>
						<view class="tool-action primary" @click="loadPriceData">刷新</view>
					</view>
				</view>
				<view class="search-box">
					<text class="iconfont iconsousuo"></text>
					<input v-model="keyword" class="search-input" placeholder="搜索型号 / 容量" placeholder-class="search-placeholder" />
				</view>
				<scroll-view v-if="selectedModels.length > 0" scroll-x class="selected-model-scroll">
					<view class="selected-model-list">
						<view
							v-for="model in selectedModels"
							:key="model"
							class="selected-model-tag"
							@click="removeSelectedModel(model)"
						>
							<text>{{ model }}</text>
							<u-icon name="close" size="20rpx" color="var(--brand)"></u-icon>
						</view>
						<view class="selected-model-clear" @click="applyModelFilter([])">清空</view>
					</view>
				</scroll-view>
				<scroll-view
					v-if="seriesTabs.length > 1"
					scroll-x
					class="series-tab-scroll"
					:scroll-into-view="activeSeriesTabViewId"
					:scroll-with-animation="true"
				>
					<view class="series-tab-list">
						<view
							v-for="item in seriesTabs"
							:key="item.key"
							:id="getSeriesTabDomId(item.key)"
							class="series-tab"
							:class="{ active: activeSeriesKey === item.key }"
							@click="selectSeries(item.key)"
						>
							<text>{{ item.name }}</text>
							<text class="series-tab-count">{{ item.modelCount }}</text>
						</view>
					</view>
				</scroll-view>
			</view>

			<view v-if="groupedTables.length > 0" class="sheet-list">
				<view
					v-for="table in groupedTables"
					:key="table.id"
					:id="`table-${table.id}`"
					:data-series-key="table.seriesKey"
					class="sheet-section"
				>
					<view v-if="groupedTables.length > 1" class="section-title">
						<text>{{ table.title }}</text>
						<text class="section-sub">{{ table.configColumns.join(' / ') }}</text>
					</view>

					<view
						v-for="(modelGroup, modelIdx) in getModelGroups(table.rows)"
						:key="`${table.id}-${modelIdx}`"
						class="model-card"
					>
						<view class="model-head">
							<view class="model-name-block">
								<text class="model-brand">{{ getModelBrand(modelGroup.modelName) }}</text>
								<text class="model-name">{{ modelGroup.modelName }}</text>
							</view>
							<text class="capacity-count">{{ modelGroup.rows.length }} 个容量</text>
						</view>

						<view class="price-table-wrap">
							<view class="mobile-price-table">
								<view class="price-row table-head">
									<view class="price-cell capacity-cell">机身内存</view>
									<view
										v-for="column in table.priceColumns"
										:key="column.key"
										class="price-cell price-head-cell"
										:style="getPriceColumnStyle(column)"
									>
										<text>{{ column.name }}</text>
									</view>
									<view
										v-for="column in table.adjustmentColumns"
										:key="column.key"
										class="price-cell adjustment-head-cell"
										:style="getAdjustmentColumnStyle(column)"
									>
										{{ column.name }}
									</view>
								</view>

								<view class="price-body-wrap">
									<view class="price-main-columns">
										<view
											v-for="(row, rowIdx) in getDisplayRows(modelGroup.rows, table.adjustmentColumns)"
											:key="rowIdx"
											class="price-row"
											:style="{ height: `${row.displayRowHeight || ADJUSTMENT_ROW_HEIGHT_RPX}rpx`, minHeight: `${row.displayRowHeight || ADJUSTMENT_ROW_HEIGHT_RPX}rpx` }"
										>
											<view class="price-cell capacity-cell capacity-body">{{ row.capacity || '--' }}</view>
											<view
												v-for="column in table.priceColumns"
												:key="column.key"
												class="price-cell price-body-cell"
												:style="getPriceColumnStyle(column)"
											>
												<text v-if="hasPriceValue(row.prices?.[column.name])" class="price-value">{{ formatPrice(row.prices?.[column.name]) }}</text>
												<text v-else class="empty-cell">--</text>
											</view>
										</view>
									</view>

									<view
										v-for="column in table.adjustmentColumns"
										:key="column.key"
										class="adjustment-column"
										:style="getAdjustmentColumnStyle(column)"
									>
										<view
											v-for="(row, rowIdx) in getDisplayRows(modelGroup.rows, table.adjustmentColumns)"
											:key="`${column.key}-${rowIdx}`"
											class="adjustment-cell-wrapper"
											:style="{ height: `${row.displayRowHeight || ADJUSTMENT_ROW_HEIGHT_RPX}rpx`, minHeight: `${row.displayRowHeight || ADJUSTMENT_ROW_HEIGHT_RPX}rpx` }"
										>
											<view
												v-if="getAdjustmentCell(row, column.key).show"
												class="price-cell adjustment-body-cell"
												:style="{ ...getAdjustmentColumnStyle(column), height: `${getAdjustmentCell(row, column.key).height || getAdjustmentCell(row, column.key).rowspan * ADJUSTMENT_ROW_HEIGHT_RPX}rpx` }"
											>
												<view v-if="getAdjustmentCell(row, column.key).parts.length" class="adjustment-item-list">
													<text
														v-for="item in getAdjustmentCell(row, column.key).parts"
														:key="item"
														class="adjustment-item-text"
													>{{ item }}</text>
												</view>
												<text v-else class="adjustment-cell-text">/</text>
											</view>
										</view>
									</view>
								</view>
							</view>
						</view>
					</view>
				</view>
			</view>
			<view v-else-if="tableData.length > 0" class="filter-result-empty">
				<text class="filter-empty-title">没有匹配的报价</text>
				<text class="filter-empty-desc">可以调整关键词或清空型号筛选后重新查看</text>
				<view class="filter-empty-actions">
					<view class="filter-empty-btn" @click="keyword = ''">清空搜索</view>
					<view class="filter-empty-btn primary" @click="applyModelFilter([])">查看全部型号</view>
				</view>
			</view>
		</view>

		<view v-if="!loading" class="action-bar">
			<view class="action-bar-inner">
				<text class="action-tip">数据仅供参考，实际价格以最终评估为准</text>
				<view class="order-btn" @click="goToOrder">去下单</view>
			</view>
		</view>

		<view v-if="showTypeSheet" class="sheet-mask" @click="showTypeSheet = false">
			<view class="type-sheet" @click.stop>
				<view class="type-sheet-head">
					<text class="type-title">选择报价单</text>
					<text class="type-close" @click="showTypeSheet = false">关闭</text>
				</view>
				<view v-if="quotationTypes.length === 0" class="type-empty">暂无可切换报价单</view>
				<view
					v-for="item in quotationTypes"
					:key="item.dataset_id || item.quotation_id"
					class="type-item"
					:class="{ active: String(item.dataset_id) === String(datasetId) || String(item.quotation_id) === String(priceTypeId) }"
					@click="switchQuotation(item)"
				>
					<view>
						<text class="type-name">{{ item.title || item.dataset_name || item.price_name }}</text>
						<text class="type-meta">{{ item.last_sync_at_text || '待同步' }} · {{ item.model_count || 0 }} 个型号</text>
					</view>
					<text class="type-check">✓</text>
				</view>
			</view>
		</view>
		<ModelFilterPopup
			v-if="showModelFilter"
			:visible="showModelFilter"
			:options="modelFilterOptions"
			:selected="selectedModels"
			:only-hot="onlyHotModels"
			@apply="applyModelFilter"
			@update:only-hot="onlyHotModels = $event"
			@close="showModelFilter = false"
		/>
	</view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { onLoad, onPageScroll } from '@dcloudio/uni-app'
import { getQuoteSpiderDetail, type QuoteSpiderItem } from '@/addon/recycle/api/quotation'
import { getQuotationV2PriceList, getQuotationV2Types, type QuotationPriceData, type QuotationV2Type } from '@/addon/recycle_daheng_quote/api/quotation'
import { getOrderSubmitConfig } from '@/addon/recycle/api/order'
import { img } from '@/utils/common'
import ModelFilterPopup from './components/ModelFilterPopup.vue'

const DEFAULT_NOTICE_TEXT = '温馨提示：报价仅供参考，最终价格以质检结果为准'

interface EnhancedPriceRow extends QuotationPriceData {
	showAdjustment?: boolean
	adjustmentRowspan?: number
	displayAdjustment?: string
	displayAdjustmentParts?: string[]
	displayAdjustmentCells?: Record<string, AdjustmentDisplayCell>
	displayRowHeight?: number
}

interface GroupedTable {
	id: number
	key: string
	title: string
	seriesKey: string
	seriesName: string
	configColumns: string[]
	priceColumns: PriceColumn[]
	adjustmentColumns: AdjustmentColumn[]
	rows: EnhancedPriceRow[]
}

interface PriceColumn {
	key: string
	name: string
	width: number
}

interface AdjustmentColumn {
	key: string
	name: string
	sort: number
	width?: number
}

interface AdjustmentDisplayCell {
	show: boolean
	rowspan: number
	parts: string[]
	text: string
	height: number
}

interface ModelGroup {
	modelName: string
	isHot: boolean
	rows: EnhancedPriceRow[]
}

interface SeriesTab {
	key: string
	name: string
	modelCount: number
	rowCount: number
}

interface ModelFilterOption {
	name: string
	rowCount: number
	capacityCount: number
	isHot: boolean
}

interface PricePageOptions {
	id?: string
	quotation_id?: string
	dataset_id?: string
	item_id?: string
	source?: string
	title?: string
	price_date?: string
	show_hot_badge?: string
	hot_badge_image?: string
	hot_badge_size?: string
}

interface PriceListResponse {
	code: number
	msg?: string
	data?: QuotationPriceData[]
}

const ORDER_PAGE_URL = '/addon/recycle/pages/order/order'
const ADJUSTMENT_ROW_HEIGHT_RPX = 68
const ADJUSTMENT_TEXT_LINE_HEIGHT_RPX = 20
const ADJUSTMENT_TEXT_GAP_RPX = 2
const ADJUSTMENT_CELL_PADDING_RPX = 12
const TABLE_TOTAL_WIDTH_RPX = 750
const CAPACITY_COLUMN_WIDTH_RPX = 76
const CAPACITY_TEXT_LINE_HEIGHT_RPX = 28
const CAPACITY_CELL_PADDING_RPX = 16
const PRICE_COLUMN_WIDTH_CONFIG = {
	minWhenMany: 76,
	minWhenNormal: 82,
	minWhenFew: 120,
	maxWhenMany: 94,
	maxWhenNormal: 108,
	maxWhenFew: 128
}
const ADJUSTMENT_COLUMN_WIDTH_CONFIG = {
	minWhenMany: 96,
	minWhenTwo: 118,
	minWhenOne: 150,
	maxWhenMany: 280,
	maxWhenTwo: 380,
	maxWhenOne: 460
}

const priceTypeId = ref('')
const datasetId = ref('')
const spiderItemId = ref('')
const source = ref('')
const priceDate = ref('')
const pageTitle = ref('报价查询')
const loading = ref(false)
const tableData = ref<QuotationPriceData[]>([])
const priceTypeName = ref('')
const keyword = ref('')
const isScrolled = ref(false)
const showTypeSheet = ref(false)
const showModelFilter = ref(false)
const quotationTypes = ref<QuotationV2Type[]>([])
const spiderImageUrl = ref('')
const spiderIsHot = ref(false)
const showHotBadge = ref(true)
const hotBadgeImage = ref('')
const hotBadgeSize = ref(38)
const selectedModels = ref<string[]>([])
const activeSeriesKey = ref('all')
const activeSeriesTabViewId = ref('')
const isSeriesClickScrolling = ref(false)
let seriesClickTimer: ReturnType<typeof setTimeout> | null = null
let seriesScrollMeasurePending = false
const onlyHotModels = ref(false)
const currentScrollTop = ref(0)
const TOP_SERIES_RESET_THRESHOLD = 80
const priceTheme = ref<Record<string, string>>({})
const systemInfo = uni.getSystemInfoSync()
const menuButtonInfo = (() => {
	try {
		// #ifdef MP-WEIXIN || MP-BAIDU || MP-TOUTIAO || MP-QQ
		return uni.getMenuButtonBoundingClientRect()
		// #endif
	} catch (error) {
		return null
	}
	return null
})()
const navStatusTopPx = Number(menuButtonInfo?.top ?? systemInfo.statusBarHeight ?? 0)
const navCapsuleHeightPx = Number(menuButtonInfo?.height ?? 44)
const navCapsuleWidthPx = Number(menuButtonInfo?.width ?? 87)
const navBottomGapPx = 8
const navContentHeightPx = navCapsuleHeightPx
const navBarHeightPx = navStatusTopPx + navContentHeightPx + navBottomGapPx
const navSideWidthRpx = Math.max(1, Math.ceil(navCapsuleWidthPx * 2 + 30))
const pageStyleVars = computed(() => {
	return [
		`--price-navbar-height:${navBarHeightPx}px`,
		`--price-navbar-top:${navStatusTopPx}px`,
		`--price-navbar-content-height:${navContentHeightPx}px`,
		`--price-navbar-side-width:${navSideWidthRpx}rpx`,
		`--price-toolbar-sticky-top:${navBarHeightPx}px`
	].join(';') + ';'
})
const themeStyleVars = computed(() => {
	const map: Record<string, string> = {
		page_bg: '--bg-main',
		card_bg: '--bg-card',
		soft_bg: '--bg-soft',
		line: '--line',
		text_main: '--text-main',
		text_sub: '--text-sub',
		brand: '--brand',
		brand_deep: '--brand-deep',
		price: '--price',
		notice_bg: '--notice-bg',
		notice_text: '--notice-text',
		toolbar_bg: '--toolbar-bg',
		button_bg: '--button-bg',
		button_text: '--button-text',
		series_active_bg: '--series-active-bg',
		series_active_text: '--series-active-text',
		series_inactive_bg: '--series-inactive-bg',
		series_inactive_text: '--series-inactive-text',
		model_head_bg: '--model-head-bg',
		model_brand_bg: '--model-brand-bg',
		model_brand_text: '--model-brand-text'
	}
	const styles: string[] = []
	for (const [key, cssVar] of Object.entries(map)) {
		const value = priceTheme.value[key]
		if (typeof value === 'string' && /^#[0-9a-fA-F]{6}$/.test(value)) {
			styles.push(`${cssVar}:${value}`)
		}
	}
	return styles.length ? styles.join(';') + ';' : ''
})
const customNavbarStyle = computed(() => `height:${navBarHeightPx}px;`)
const navbarContentStyle = computed(() => {
	return [
		`height:${navContentHeightPx}px`,
		`padding-top:${navStatusTopPx}px`,
		`padding-bottom:${navBottomGapPx}px`,
		'padding-left:18rpx',
		'padding-right:18rpx'
	].join(';') + ';'
})
const navbarSideStyle = computed(() => `height:${navContentHeightPx}px;`)
const navbarCenterStyle = computed(() => {
	return `top:${navStatusTopPx}px;height:${navContentHeightPx}px;width:calc(100% - ${navSideWidthRpx * 2}rpx);`
})

const groupedTables = computed<GroupedTable[]>(() => {
	const normalizedData = filterRows(tableData.value).map(row => ({
		...row,
		prices: normalizePrices(row.prices)
	}))
	if (normalizedData.length === 0) return []

	const configGroupMap = new Map<string, { seriesKey: string; seriesName: string; configColumns: string[]; rows: QuotationPriceData[] }>()

	for (const row of normalizedData) {
		if (!row.prices) continue

		// 完全按接口返回顺序渲染，不做前端排序
		const configColumns = Object.keys(row.prices)
		const seriesName = resolveRowSeriesName(row)
		const seriesKey = resolveRowSeriesKey(row)
		const columnKey = configColumns.join('|||')
		const groupKey = `${seriesKey}::${columnKey}`

		if (!configGroupMap.has(groupKey)) {
			configGroupMap.set(groupKey, {
				seriesKey,
				seriesName,
				configColumns,
				rows: []
			})
		}
		configGroupMap.get(groupKey)!.rows.push(row)
	}

	const tables: GroupedTable[] = []
	let idx = 1

	configGroupMap.forEach((group, key) => {
		const normalizedRows = group.rows.map(row => ({ ...row }))
		const configColumns = group.configColumns
		const tableColumns = calculateTableColumnWidths(
			configColumns,
			collectAdjustmentColumns(normalizedRows),
			normalizedRows
		)

		tables.push({
			id: idx++,
			key,
			title: group.seriesName || `价格结构 ${idx - 1}`,
			seriesKey: group.seriesKey,
			seriesName: group.seriesName,
			configColumns,
			priceColumns: tableColumns.priceColumns,
			adjustmentColumns: tableColumns.adjustmentColumns,
			rows: normalizedRows
		})
	})

	return tables
})

const seriesTabs = computed<SeriesTab[]>(() => {
	const map = new Map<string, { name: string; models: Set<string>; rowCount: number }>()
	for (const row of tableData.value) {
		if (onlyHotModels.value && Number(row.is_hot || 0) !== 1) continue
		const key = resolveRowSeriesKey(row)
		const name = resolveRowSeriesName(row)
		if (!map.has(key)) {
			map.set(key, {
				name,
				models: new Set<string>(),
				rowCount: 0
			})
		}
		const item = map.get(key)!
		if (row.goods_name) item.models.add(normalizeModelName(row.goods_name))
		item.rowCount += 1
	}
	const tabs = Array.from(map.entries()).map(([key, item]) => ({
		key,
		name: item.name,
		modelCount: item.models.size,
		rowCount: item.rowCount
	}))
	if (tabs.length <= 1) return tabs
	return [{ key: 'all', name: '全部系列', modelCount: new Set(tableData.value.map(row => normalizeModelName(row.goods_name)).filter(Boolean)).size, rowCount: tableData.value.length }, ...tabs]
})

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

const navbarTitle = computed(() => {
	if (priceTypeName.value && (!pageTitle.value || pageTitle.value === '报价查询' || pageTitle.value === priceTypeName.value)) {
		return priceTypeName.value
	}
	if (priceTypeName.value && pageTitle.value) {
		return pageTitle.value.includes(priceTypeName.value) ? pageTitle.value : `${priceTypeName.value}${pageTitle.value}`
	}
	return pageTitle.value || '报价查询'
})

const quoteNoticeText = ref(DEFAULT_NOTICE_TEXT)
const noticeLines = computed(() => {
	const text = (quoteNoticeText.value || DEFAULT_NOTICE_TEXT).replace(/\r\n/g, '\n').replace(/\r/g, '\n')
	const lines = text.split('\n').map(item => item.trim()).filter(Boolean)
	return lines.length ? lines : [DEFAULT_NOTICE_TEXT]
})

const showSpiderHotBadge = computed(() => source.value === 'spider' && spiderIsHot.value && showHotBadge.value)
const hotBadgeBoxStyle = computed(() => {
	const size = Math.max(24, Math.min(Number(hotBadgeSize.value || 38), 80))
	if (hotBadgeImage.value) {
		return `width:${size}rpx;height:${size}rpx;`
	}
	return ''
})
const hotBadgeTextStyle = computed(() => {
	const size = Math.max(24, Math.min(Number(hotBadgeSize.value || 38), 80))
	const fontSize = Math.max(18, Math.round(size * 0.46))
	return `font-size:${fontSize}rpx;line-height:${Math.max(26, fontSize + 8)}rpx;`
})

const filteredModelCount = computed(() => {
	const modelSet = new Set<string>()
	for (const row of filterRows(tableData.value)) {
		if (row.goods_name) modelSet.add(row.goods_name)
	}
	return modelSet.size
})

const filteredRowCount = computed(() => filterRows(tableData.value).length)

const modelFilterOptions = computed<ModelFilterOption[]>(() => {
	const map = new Map<string, { rowCount: number; capacities: Set<string>; isHot: boolean }>()
	for (const row of tableData.value) {
		const name = normalizeModelName(row.goods_name)
		if (!name) continue
		if (!map.has(name)) {
			map.set(name, {
				rowCount: 0,
				capacities: new Set<string>(),
				isHot: false
			})
		}
		const item = map.get(name)!
		item.rowCount += 1
		item.isHot = item.isHot || Number(row.is_hot || 0) === 1
		const capacity = String(row.capacity || '').trim()
		if (capacity) item.capacities.add(capacity)
	}

	return Array.from(map.entries()).map(([name, item]) => ({
		name,
		rowCount: item.rowCount,
		capacityCount: item.capacities.size || item.rowCount,
		isHot: item.isHot
	}))
})

watch(
	seriesTabs,
	(list) => {
		if (activeSeriesKey.value !== 'all' && !list.some(item => item.key === activeSeriesKey.value)) {
			activeSeriesKey.value = 'all'
		}
		syncActiveSeriesTabView(activeSeriesKey.value)
	},
	{
		immediate: true
	}
)

function getModelGroups(rows: EnhancedPriceRow[]): ModelGroup[] {
	const groups: ModelGroup[] = []
	let currentModelName = ''
	let currentGroup: ModelGroup | null = null

	rows.forEach(row => {
		if (row.goods_name !== currentModelName) {
			if (currentGroup) {
				groups.push(currentGroup)
			}
			currentModelName = row.goods_name
			currentGroup = {
				modelName: row.goods_name,
				isHot: Number(row.is_hot || 0) === 1,
				rows: [row]
			}
		} else {
			currentGroup?.rows.push(row)
		}
	})

	if (currentGroup) {
		groups.push(currentGroup)
	}

	return groups
}

function isRecord(value: unknown): value is Record<string, unknown> {
	return typeof value === 'object' && value !== null
}

function normalizePrices(value: unknown): Record<string, unknown> {
	if (Array.isArray(value)) {
		const result: Record<string, unknown> = {}
		for (const item of value) {
			if (!isRecord(item)) continue
			const name = String(item.name || item.field_name || item.price_name || '').trim()
			if (!name) continue
			result[name] = item
		}
		return result
	}

	if (isRecord(value)) {
		return value
	}

	return {}
}

function filterRows(rows: QuotationPriceData[]): QuotationPriceData[] {
	const word = keyword.value.trim().toLowerCase()
	const selectedSet = new Set(selectedModels.value.map(normalizeModelName))

	return rows.filter(row => {
		if (onlyHotModels.value && Number(row.is_hot || 0) !== 1) {
			return false
		}
		if (selectedSet.size > 0 && !selectedSet.has(normalizeModelName(row.goods_name))) {
			return false
		}
		if (!word) return true
		const model = String(row.goods_name || '').toLowerCase()
		const capacity = String(row.capacity || '').toLowerCase()
		return model.includes(word) || capacity.includes(word)
	})
}

function resolveRowSeriesName(row: Partial<QuotationPriceData>): string {
	const seriesName = String(row.series_name || '').trim()
	if (seriesName) return seriesName
	const groupKey = Number(row.model_group_key || 0)
	return groupKey > 0 ? `系列 ${groupKey}` : '其他系列'
}

function resolveRowSeriesKey(row: Partial<QuotationPriceData>): string {
	const seriesName = String(row.series_name || '').trim()
	if (seriesName) return `series:${seriesName}`
	const groupKey = Number(row.model_group_key || 0)
	return groupKey > 0 ? `group:${groupKey}` : 'series:other'
}

function selectSeries(key: string) {
	const targetKey = key || 'all'
	activeSeriesKey.value = targetKey
	syncActiveSeriesTabView(targetKey)
	scrollToSeries(targetKey)
}

function getSeriesTabDomId(key: string): string {
	const index = seriesTabs.value.findIndex(item => item.key === key)
	return index >= 0 ? `series-tab-${index}` : ''
}

function syncActiveSeriesTabView(key: string) {
	activeSeriesTabViewId.value = getSeriesTabDomId(key)
}

function setActiveSeriesByScroll(key: string) {
	if (!key || key === activeSeriesKey.value) return
	activeSeriesKey.value = key
	syncActiveSeriesTabView(key)
}

function scrollToSeries(key: string) {
	const tables = groupedTables.value
	if (!tables.length) return

	const targetTable = key === 'all' ? tables[0] : tables.find(table => table.seriesKey === key)
	if (!targetTable) return

	isSeriesClickScrolling.value = true
	if (seriesClickTimer) clearTimeout(seriesClickTimer)
	seriesClickTimer = setTimeout(() => {
		isSeriesClickScrolling.value = false
	}, 700)

	const selector = `#table-${targetTable.id}`
	uni.createSelectorQuery()
		.select(selector)
		.boundingClientRect((rect) => {
			if (!rect || Array.isArray(rect)) return
			const top = Number(rect.top || 0)
			const targetTop = Math.max(0, top + currentScrollTop.value - navBarHeightPx - 92)
			uni.pageScrollTo({
				scrollTop: targetTop,
				duration: 240
			})
		})
		.exec()
}

function updateActiveSeriesByScroll() {
	if (isSeriesClickScrolling.value || seriesTabs.value.length <= 1 || groupedTables.value.length <= 1) {
		return
	}
	if (currentScrollTop.value <= TOP_SERIES_RESET_THRESHOLD) {
		setActiveSeriesByScroll('all')
		return
	}
	if (seriesScrollMeasurePending) {
		return
	}
	seriesScrollMeasurePending = true
	setTimeout(() => {
		seriesScrollMeasurePending = false
		const tables = groupedTables.value
		uni.createSelectorQuery()
			.selectAll('.sheet-section')
			.boundingClientRect((rects) => {
				if (!Array.isArray(rects) || rects.length === 0) return
				const anchorTop = navBarHeightPx + 122
				let activeIndex = 0
				for (let index = 0; index < rects.length; index++) {
					const rect = rects[index] as any
					if (Number(rect.top || 0) <= anchorTop) {
						activeIndex = index
					}
				}
				const table = tables[activeIndex]
				if (table) setActiveSeriesByScroll(table.seriesKey)
			})
			.exec()
	}, 80)
}

function toggleHotModels() {
	onlyHotModels.value = !onlyHotModels.value
	if (onlyHotModels.value && activeSeriesKey.value !== 'all' && !seriesTabs.value.some(item => item.key === activeSeriesKey.value)) {
		activeSeriesKey.value = 'all'
		syncActiveSeriesTabView('all')
	}
}

function applyModelFilter(models: string[]) {
	selectedModels.value = Array.from(new Set(models.map(normalizeModelName).filter(Boolean)))
	activeSeriesKey.value = 'all'
	syncActiveSeriesTabView('all')
	showModelFilter.value = false
}

function removeSelectedModel(model: string) {
	const target = normalizeModelName(model)
	selectedModels.value = selectedModels.value.filter(item => normalizeModelName(item) !== target)
}

function normalizeModelName(value: unknown): string {
	return String(value || '').replace(/\s+/g, ' ').trim()
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
	return `${Math.round(num)}`
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
	if (source.value === 'spider') {
		await loadSpiderPriceData()
		return
	}

	if (!priceTypeId.value && !datasetId.value) {
		uni.showToast({ title: '参数错误', icon: 'none' })
		return
	}

	loading.value = true
	try {
		const res = await loadV2PriceData()

		if (res.code === 1 && res.data) {
			quoteNoticeText.value = DEFAULT_NOTICE_TEXT
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

async function loadSpiderPriceData() {
	if (!spiderItemId.value) {
		uni.showToast({ title: '参数错误', icon: 'none' })
		return
	}

	loading.value = true
	try {
		const res = await getQuoteSpiderDetail(spiderItemId.value) as any
		if (res.code === 1 && res.data) {
			const item = res.data as QuoteSpiderItem
			spiderImageUrl.value = resolveSpiderImage(item)
			spiderIsHot.value = Number(item.is_hot || 0) === 1
			quoteNoticeText.value = String(item.notice_text || DEFAULT_NOTICE_TEXT)
			tableData.value = normalizeSpiderRows(item)
			priceTypeName.value = item.title || item.name || ''
			if (!pageTitle.value || pageTitle.value === '报价查询') {
				pageTitle.value = priceTypeName.value || '报价查询'
			}
		} else {
			uni.showToast({ title: res.msg || '加载失败', icon: 'none' })
		}
	} catch (error: unknown) {
		console.error('加载爬虫报价失败:', error)
		uni.showToast({
			title: getErrorMessage(error),
			icon: 'none'
		})
	} finally {
		loading.value = false
	}
}

function resolveSpiderImage(item: QuoteSpiderItem): string {
	return item.bimage || item.image || item.timage || item.icon || ''
}

function previewSpiderImage() {
	if (!spiderImageUrl.value) return
	uni.previewImage({
		urls: [spiderImageUrl.value],
		current: 0
	})
}

function normalizeSpiderRows(item: QuoteSpiderItem): QuotationPriceData[] {
	const title = item.title || item.name || '报价单'
	const rows = Array.isArray(item.rows) ? item.rows : []

	return rows.map(row => {
		const prices = normalizeSpiderPrices(row.final_prices || row.manual_prices || row.source_prices || {}, row.columns || [])
		const seriesName = normalizeText(row.tab || item.tab || '')
		return {
			id: row.id,
			quotation_id: item.id,
			price_name: title,
			goods_id: row.id,
			goods_name: row.model_name || item.name || title,
			model_group_key: 0,
			series_name: seriesName,
			is_hot: Number(item.is_hot || 0),
			capacity: resolveSpiderCapacity(row, seriesName),
			prices,
			add_value_info: 0,
			value_info: row.remark || '',
			adjustment_items: row.remark ? [{ field_name: '备注', content_text: row.remark }] : [],
			adjustment_summary: row.remark || '',
			price_date: '',
			create_at: row.create_at || '',
			update_at: row.update_at || ''
		}
	})
}

function resolveSpiderCapacity(row: Record<string, any>, seriesName = ''): string {
	const raw = isRecord(row.raw_data) ? row.raw_data : {}
	const candidates = [
		raw['内存'],
		raw['容量'],
		raw['规格'],
		raw['存储'],
		row.capacity_name,
		row.capacity,
		raw.capacity_name,
		raw.capacity,
		raw.memory,
		raw.storage,
		raw.rom
	]
	const value = candidates.find(item => isValidSpiderCapacity(item, seriesName))
	return value === undefined ? '--' : normalizeText(value)
}

function isValidSpiderCapacity(value: unknown, seriesName = ''): boolean {
	const text = normalizeText(value)
	if (!text) return false
	if (seriesName && text === seriesName) return false
	if (/分组|系列/.test(text)) return false
	return true
}

function normalizeText(value: unknown): string {
	return String(value ?? '').replace(/\s+/g, ' ').trim()
}

function normalizeSpiderPrices(value: unknown, columns: string[] = []): Record<string, unknown> {
	const result: Record<string, unknown> = {}
	const columnNames = columns.map(column => String(column || '').trim())

	if (isRecord(value)) {
		for (const key of Object.keys(value)) {
			const columnName = resolveSpiderPriceColumnName(key, columnNames)
			if (!columnName) continue
			result[columnName] = {
				price: value[key],
				final: value[key]
			}
		}
	}

	if (Array.isArray(value)) {
		value.forEach((item, index) => {
			if (isRecord(item)) {
				const key = String(item.name || item.field_name || item.label || columnNames[index] || `价格${index + 1}`).trim()
				if (key) result[key] = item
			} else {
				const key = String(columnNames[index] || `价格${index + 1}`)
				result[key] = {
					price: item,
					final: item
				}
			}
		})
	}

	if (Object.keys(result).length === 0 && columnNames.length > 0) {
		for (const column of columnNames) {
			if (!column) continue
			result[column] = ''
		}
	}

	return result
}

function resolveSpiderPriceColumnName(key: string, columns: string[]): string {
	const name = String(key || '').trim()
	if (name === '') return ''
	if (/^\d+$/.test(name)) {
		return columns[Number(name)] || ''
	}
	return name
}

async function loadV2PriceData() {
	const res = (await getQuotationV2PriceList({
		dataset_id: datasetId.value,
		quotation_id: priceTypeId.value,
		price_date: priceDate.value
	})) as PriceListResponse
	source.value = 'v2'
	return res
}

function goToOrder() {
	const queryParts: string[] = []
	if (priceTypeId.value) queryParts.push(`quotation_id=${encodeURIComponent(priceTypeId.value)}`)
	if (datasetId.value) queryParts.push(`dataset_id=${encodeURIComponent(datasetId.value)}`)
	if (spiderItemId.value) queryParts.push(`quote_spider_item_id=${encodeURIComponent(spiderItemId.value)}`)
	const query = queryParts.length ? `?${queryParts.join('&')}` : ''
	uni.navigateTo({ url: `${ORDER_PAGE_URL}${query}` })
}

function goBack() {
	uni.navigateBack()
}

function getModelBrand(modelName: string): string {
	const name = String(modelName || '')
	if (/iphone|ipad|mac|苹果/i.test(name)) return '苹果'
	if (/honor|荣耀/i.test(name)) return '荣耀'
	if (/huawei|华为/i.test(name)) return '华为'
	if (/xiaomi|redmi|小米|红米/i.test(name)) return '小米'
	if (/oppo/i.test(name)) return 'OPPO'
	if (/vivo/i.test(name)) return 'vivo'
	if (/samsung|三星/i.test(name)) return '三星'
	return '型号'
}

function getDisplayRows(rows: EnhancedPriceRow[], adjustmentColumns: AdjustmentColumn[]): EnhancedPriceRow[] {
	const displayRows = rows.map(row => {
		const displayAdjustmentCells = buildRowAdjustmentCells(row, adjustmentColumns)
		return {
			...row,
			showAdjustment: true,
			adjustmentRowspan: 1,
			displayAdjustmentCells,
			displayAdjustmentParts: Object.values(displayAdjustmentCells).flatMap(cell => cell.parts),
			displayAdjustment: Object.values(displayAdjustmentCells).map(cell => cell.text).filter(Boolean).join('；'),
			displayRowHeight: estimateCapacityCellHeight(row.capacity || '--')
		}
	})

	if (displayRows.length === 0 || adjustmentColumns.length === 0) {
		applyDisplayRowHeights(displayRows)
		return displayRows
	}

	for (const column of adjustmentColumns) {
		const uniqueNonEmptyTexts = Array.from(new Set(
			displayRows
				.map(row => row.displayAdjustmentCells?.[column.key]?.text || '')
				.filter(Boolean)
		))

		if (uniqueNonEmptyTexts.length === 1) {
			const sourceCell = displayRows
				.map(row => row.displayAdjustmentCells?.[column.key])
				.find(cell => cell && cell.text === uniqueNonEmptyTexts[0])
			const firstCell = displayRows[0].displayAdjustmentCells?.[column.key]
			if (firstCell) {
				firstCell.show = true
				firstCell.rowspan = displayRows.length
				firstCell.parts = sourceCell?.parts || splitAdjustmentText(uniqueNonEmptyTexts[0])
				firstCell.text = uniqueNonEmptyTexts[0]
			}
			for (let index = 1; index < displayRows.length; index++) {
				const cell = displayRows[index].displayAdjustmentCells?.[column.key]
				if (cell) {
					cell.show = false
					cell.rowspan = 0
				}
			}
			applyAdjustmentCellHeight(displayRows, column, 0, displayRows.length)
			continue
		}

		let startIndex = 0
		let currentText = displayRows[0]?.displayAdjustmentCells?.[column.key]?.text || ''

		for (let index = 1; index <= displayRows.length; index++) {
			const text = displayRows[index]?.displayAdjustmentCells?.[column.key]?.text || ''
			if (index === displayRows.length || text !== currentText) {
				const span = index - startIndex
				const startCell = displayRows[startIndex].displayAdjustmentCells?.[column.key]
				if (startCell) {
					startCell.show = true
					startCell.rowspan = span
				}
				for (let i = startIndex + 1; i < index; i++) {
					const cell = displayRows[i].displayAdjustmentCells?.[column.key]
					if (cell) {
						cell.show = false
						cell.rowspan = 0
					}
				}
				applyAdjustmentCellHeight(displayRows, column, startIndex, span)
				startIndex = index
				currentText = text
			}
		}
	}

	applyDisplayRowHeights(displayRows)

	return displayRows
}

function collectAdjustmentColumns(rows: EnhancedPriceRow[]): AdjustmentColumn[] {
	const columns = new Map<string, AdjustmentColumn>()

	for (const row of rows) {
		for (const item of collectAdjustmentItemRecords(row)) {
			const column = getAdjustmentColumnFromItem(item)
			if (!column || columns.has(column.key)) continue
			columns.set(column.key, column)
		}

		for (const part of splitAdjustmentSummaryText(row.adjustment_summary || row.value_info || '')) {
			const parsed = parseLabeledAdjustmentText(part)
			if (!parsed || columns.has(parsed.key) || Array.from(columns.values()).some(column => column.name === parsed.name)) continue
			columns.set(parsed.key, parsed)
		}
	}

	if (columns.size === 0 && rows.some(row => normalizeAdjustmentParts(row).length > 0)) {
		columns.set('adjustment', {
			key: 'adjustment',
			name: '加/扣钱项',
			sort: 999
		})
	}

	return Array.from(columns.values()).sort((a, b) => (a.sort - b.sort) || a.name.localeCompare(b.name))
}

function calculateTableColumnWidths(priceNames: string[], adjustmentColumns: AdjustmentColumn[], rows: EnhancedPriceRow[]): {
	priceColumns: PriceColumn[]
	adjustmentColumns: AdjustmentColumn[]
} {
	const priceColumns: PriceColumn[] = priceNames.map(name => ({
		key: `price:${name}`,
		name,
		width: 0
	}))
	const columns = [
		...priceColumns.map(column => ({
			type: 'price' as const,
			key: column.key,
			score: getPriceColumnScore(column.name, rows),
			min: getPriceColumnMinWidth(priceColumns.length),
			max: getPriceColumnMaxWidth(priceColumns.length)
		})),
		...adjustmentColumns.map(column => ({
			type: 'adjustment' as const,
			key: column.key,
			score: getAdjustmentColumnScore(column, rows) * 1.35,
			min: getAdjustmentColumnMinWidth(adjustmentColumns.length),
			max: getAdjustmentColumnMaxWidth(adjustmentColumns.length)
		}))
	]

	if (columns.length === 0) {
		return { priceColumns, adjustmentColumns }
	}

	const totalWidth = Math.max(0, TABLE_TOTAL_WIDTH_RPX - CAPACITY_COLUMN_WIDTH_RPX)
	const widthMap = allocateColumnWidths(columns, totalWidth)

	return {
		priceColumns: priceColumns.map(column => ({
			...column,
			width: widthMap[column.key] || getPriceColumnMinWidth(priceColumns.length)
		})),
		adjustmentColumns: adjustmentColumns.map(column => ({
			...column,
			width: widthMap[column.key] || getAdjustmentColumnMinWidth(adjustmentColumns.length)
		}))
	}
}

function allocateColumnWidths(columns: Array<{ key: string; score: number; min: number; max: number }>, totalWidth: number): Record<string, number> {
	const result: Record<string, number> = {}
	const minTotal = columns.reduce((total, column) => total + column.min, 0)
	const scoreTotal = columns.reduce((total, column) => total + Math.max(1, column.score), 0)
	const flexibleWidth = Math.max(0, totalWidth - minTotal)

	for (const column of columns) {
		const weightedWidth = column.min + Math.round(flexibleWidth * (Math.max(1, column.score) / scoreTotal))
		result[column.key] = Math.max(column.min, Math.min(column.max, weightedWidth))
	}

	let remaining = totalWidth - Object.values(result).reduce((total, width) => total + width, 0)
	const sortedColumns = [...columns].sort((a, b) => b.score - a.score)
	let guard = 0
	while (remaining !== 0 && sortedColumns.length > 0 && guard < sortedColumns.length * 1000) {
		for (const column of sortedColumns) {
			if (remaining > 0 && result[column.key] < column.max) {
				result[column.key]++
				remaining--
			} else if (remaining < 0 && result[column.key] > column.min) {
				result[column.key]--
				remaining++
			}
			if (remaining === 0) break
		}
		guard++
		if (
			(remaining > 0 && sortedColumns.every(column => result[column.key] >= column.max))
			|| (remaining < 0 && sortedColumns.every(column => result[column.key] <= column.min))
		) {
			break
		}
	}

	return result
}

function getPriceColumnScore(name: string, rows: EnhancedPriceRow[]): number {
	let maxPriceWeight = 0
	for (const row of rows) {
		const price = formatPrice(row.prices?.[name])
		if (price !== '--') {
			maxPriceWeight = Math.max(maxPriceWeight, getTextDisplayWeight(price))
		}
	}

	return Math.max(3, maxPriceWeight * 1.4 + Math.min(getTextDisplayWeight(name) * 0.16, 4))
}

function getAdjustmentColumnScore(column: AdjustmentColumn, rows: EnhancedPriceRow[]): number {
	let score = getTextDisplayWeight(column.name) * 1.4
	let longestPartWeight = 0
	let totalPartWeight = 0
	let partCount = 0

	for (const row of rows) {
		const cells = buildRowAdjustmentCells(row, [column])
		const parts = cells[column.key]?.parts || []
		for (const part of parts) {
			const weight = getTextDisplayWeight(part)
			longestPartWeight = Math.max(longestPartWeight, weight)
			totalPartWeight += weight
			partCount++
		}
	}

	const averagePartWeight = partCount > 0 ? totalPartWeight / partCount : 0
	score += longestPartWeight * 0.7 + averagePartWeight * 0.3
	return score
}

function getTextDisplayWeight(text: string): number {
	let weight = 0
	for (const char of String(text || '')) {
		weight += /[\x00-\x7F]/.test(char) ? 0.55 : 1
	}
	return weight
}

function getPriceColumnMinWidth(count: number): number {
	if (count >= 4) return PRICE_COLUMN_WIDTH_CONFIG.minWhenMany
	if (count >= 3) return PRICE_COLUMN_WIDTH_CONFIG.minWhenNormal
	return PRICE_COLUMN_WIDTH_CONFIG.minWhenFew
}

function getPriceColumnMaxWidth(count: number): number {
	if (count >= 4) return PRICE_COLUMN_WIDTH_CONFIG.maxWhenMany
	if (count >= 3) return PRICE_COLUMN_WIDTH_CONFIG.maxWhenNormal
	return PRICE_COLUMN_WIDTH_CONFIG.maxWhenFew
}

function getAdjustmentColumnMinWidth(count: number): number {
	if (count >= 3) return ADJUSTMENT_COLUMN_WIDTH_CONFIG.minWhenMany
	if (count === 2) return ADJUSTMENT_COLUMN_WIDTH_CONFIG.minWhenTwo
	return ADJUSTMENT_COLUMN_WIDTH_CONFIG.minWhenOne
}

function getAdjustmentColumnMaxWidth(count: number): number {
	if (count <= 1) return ADJUSTMENT_COLUMN_WIDTH_CONFIG.maxWhenOne
	if (count === 2) return ADJUSTMENT_COLUMN_WIDTH_CONFIG.maxWhenTwo
	return ADJUSTMENT_COLUMN_WIDTH_CONFIG.maxWhenMany
}

function buildRowAdjustmentCells(row: EnhancedPriceRow, adjustmentColumns: AdjustmentColumn[]): Record<string, AdjustmentDisplayCell> {
	const map: Record<string, AdjustmentDisplayCell> = {}
	for (const column of adjustmentColumns) {
		map[column.key] = {
			show: true,
			rowspan: 1,
			parts: [],
			text: '',
			height: ADJUSTMENT_ROW_HEIGHT_RPX
		}
	}

	for (const item of collectAdjustmentItemRecords(row)) {
		const column = getAdjustmentColumnFromItem(item)
		if (!column || !map[column.key]) continue
		map[column.key].parts.push(...getAdjustmentItemContentParts(item))
	}

	for (const part of splitAdjustmentSummaryText(row.adjustment_summary || row.value_info || '')) {
		const parsed = parseLabeledAdjustmentText(part)
		if (!parsed) continue
		const key = map[parsed.key] ? parsed.key : findAdjustmentColumnKeyByName(adjustmentColumns, parsed.name)
		if (!key || !map[key]) continue
		map[key].parts.push(...splitAdjustmentText(parsed.content))
	}

	if (adjustmentColumns.length === 1 && Object.values(map)[0] && Object.values(map)[0].parts.length === 0) {
		Object.values(map)[0].parts = normalizeAdjustmentParts(row)
	}

	for (const column of adjustmentColumns) {
		const parts = uniqueAdjustmentParts(map[column.key].parts)
		map[column.key].parts = parts
		map[column.key].text = parts.join('；')
	}

	return map
}

function applyAdjustmentCellHeight(rows: EnhancedPriceRow[], column: AdjustmentColumn, startIndex: number, rowspan: number) {
	const cell = rows[startIndex]?.displayAdjustmentCells?.[column.key]
	if (!cell) return

	const contentHeight = estimateAdjustmentCellHeight(cell.parts, getAdjustmentColumnWidth(column))
	const baseHeight = rowspan * ADJUSTMENT_ROW_HEIGHT_RPX
	cell.height = Math.max(baseHeight, contentHeight)
}

function applyDisplayRowHeights(rows: EnhancedPriceRow[]) {
	const requiredHeights = rows.map(row => Math.max(
		ADJUSTMENT_ROW_HEIGHT_RPX,
		estimateCapacityCellHeight(row.capacity || '--'),
		Number(row.displayRowHeight || 0)
	))

	rows.forEach((row, rowIndex) => {
		Object.values(row.displayAdjustmentCells || {}).forEach(cell => {
			if (!cell.show || cell.rowspan <= 0) return
			const rowSpan = Math.max(1, cell.rowspan)
			const averageHeight = Math.ceil((cell.height || ADJUSTMENT_ROW_HEIGHT_RPX * rowSpan) / rowSpan)
			for (let index = rowIndex; index < Math.min(rows.length, rowIndex + rowSpan); index++) {
				requiredHeights[index] = Math.max(requiredHeights[index], averageHeight)
			}
		})
	})

	rows.forEach((row, index) => {
		row.displayRowHeight = requiredHeights[index]
	})

	rows.forEach((row, rowIndex) => {
		Object.values(row.displayAdjustmentCells || {}).forEach(cell => {
			if (!cell.show || cell.rowspan <= 0) return
			let height = 0
			for (let index = rowIndex; index < Math.min(rows.length, rowIndex + cell.rowspan); index++) {
				height += rows[index].displayRowHeight || ADJUSTMENT_ROW_HEIGHT_RPX
			}
			cell.height = Math.max(cell.height || 0, height)
		})
	})
}

function estimateCapacityCellHeight(value: unknown): number {
	const lines = estimateTextLineCount(String(value || '--'), CAPACITY_COLUMN_WIDTH_RPX)
	return Math.max(ADJUSTMENT_ROW_HEIGHT_RPX, lines * CAPACITY_TEXT_LINE_HEIGHT_RPX + CAPACITY_CELL_PADDING_RPX)
}

function estimateAdjustmentCellHeight(parts: string[], columnWidth: number): number {
	if (parts.length === 0) return ADJUSTMENT_ROW_HEIGHT_RPX

	const contentLines = parts.reduce((total, part) => {
		return total + estimateTextLineCount(part, columnWidth)
	}, 0)
	const gapHeight = Math.max(0, parts.length - 1) * ADJUSTMENT_TEXT_GAP_RPX

	return Math.max(
		ADJUSTMENT_ROW_HEIGHT_RPX,
		contentLines * ADJUSTMENT_TEXT_LINE_HEIGHT_RPX + gapHeight + ADJUSTMENT_CELL_PADDING_RPX
	)
}

function estimateTextLineCount(text: string, columnWidth: number): number {
	const normalizedText = String(text || '').trim()
	if (!normalizedText) return 1

	const availableWidth = Math.max(64, columnWidth - 12)
	const charsPerLine = Math.max(4, Math.floor(availableWidth / 14))
	let weight = 0
	for (const char of normalizedText) {
		weight += /[\x00-\x7F]/.test(char) ? 0.55 : 1
	}

	return Math.max(1, Math.ceil(weight / charsPerLine))
}

function normalizeAdjustmentParts(row: EnhancedPriceRow): string[] {
	const parts = collectAdjustmentItemRecords(row).flatMap(item => {
		const column = getAdjustmentColumnFromItem(item)
		const content = getAdjustmentItemContentParts(item).join(' ')
		return column && content ? [`${column.name}：${content}`] : splitAdjustmentText(content)
	})

	parts.push(
		String(row.adjustment_summary || ''),
		String(row.value_info || ''),
		String((row as Record<string, unknown>).remark_text || ''),
		String((row as Record<string, unknown>).remark || '')
	)

	return mergeAdjustmentTextParts(parts)
}

function formatAdjustmentItems(value: unknown): string[] {
	if (typeof value === 'string') {
		return splitAdjustmentText(value)
	}

	const items = Array.isArray(value)
		? value
		: isRecord(value)
			? hasAdjustmentContent(value) ? [value] : Object.values(value)
			: []

	const parts: string[] = []
	for (const item of items) {
		if (typeof item === 'string') {
			parts.push(...splitAdjustmentText(item))
			continue
		}
		if (!isRecord(item)) continue

		const content = String(item.content_text || item.remark_text || item.content || item.text || item.value || item.remark || '').trim()
		if (!content) continue

		const fieldName = String(item.field_name || item.name || item.title || item.label || '').trim()
		const text = fieldName ? `${fieldName}：${content}` : content
		if (!parts.includes(text)) {
			parts.push(text)
		}
	}

	return parts
}

function collectAdjustmentItemRecords(row: EnhancedPriceRow): Record<string, unknown>[] {
	const sources = [
		row.adjustment_items,
		(row as Record<string, unknown>).adjustments,
		(row as Record<string, unknown>).notes,
		(row as Record<string, unknown>).note_items,
		(row as Record<string, unknown>).remark_items,
		(row as Record<string, unknown>).deduction_items,
		(row as Record<string, unknown>).deductionConfig
	]
	const records: Record<string, unknown>[] = []

	for (const source of sources) {
		if (Array.isArray(source)) {
			for (const item of source) {
				if (isRecord(item)) records.push(item)
			}
			continue
		}
		if (isRecord(source)) {
			if (hasAdjustmentContent(source)) {
				records.push(source)
			} else {
				for (const item of Object.values(source)) {
					if (isRecord(item)) records.push(item)
				}
			}
		}
	}

	return records
}

function getAdjustmentColumnFromItem(item: Record<string, unknown>): AdjustmentColumn | null {
	const name = String(item.field_name || item.name || item.title || item.label || '').trim()
	if (!name) return null
	const rawKey = item.field_id !== undefined && item.field_id !== null && item.field_id !== ''
		? `field:${String(item.field_id)}`
		: `label:${name}`

	return {
		key: rawKey,
		name,
		sort: Number(item.field_sort || item.sort || 999)
	}
}

function findAdjustmentColumnKeyByName(columns: AdjustmentColumn[], name: string): string {
	return columns.find(column => column.name === name)?.key || ''
}

function parseLabeledAdjustmentText(value: unknown): (AdjustmentColumn & { content: string }) | null {
	const text = String(value || '').trim()
	const separatorIndex = text.indexOf('：')
	if (separatorIndex <= 0) return null
	const name = text.slice(0, separatorIndex).trim()
	const content = text.slice(separatorIndex + 1).trim()
	if (!name || !content) return null

	return {
		key: `label:${name}`,
		name,
		sort: 999,
		content
	}
}

function getAdjustmentItemContentParts(item: Record<string, unknown>): string[] {
	const htmlParts = getHtmlTextParts(String(item.content_html || item.html || ''))
	if (htmlParts.length > 1) return htmlParts

	const content = String(item.content_text || item.remark_text || item.content || item.text || item.value || item.remark || '').trim()
	return splitAdjustmentText(content || htmlParts.join(' '))
}

function getHtmlTextParts(html: string): string[] {
	if (!html.trim()) return []

	const parts: string[] = []
	const paragraphPattern = /<p[^>]*>(.*?)<\/p>/gi
	let match: RegExpExecArray | null
	while ((match = paragraphPattern.exec(html)) !== null) {
		const text = decodeHtmlText(match[1])
		if (text) parts.push(text)
	}

	if (parts.length > 0) return parts

	const text = decodeHtmlText(html)
	return text ? [text] : []
}

function decodeHtmlText(value: string): string {
	return value
		.replace(/<[^>]+>/g, ' ')
		.replace(/&nbsp;/gi, ' ')
		.replace(/&amp;/gi, '&')
		.replace(/&lt;/gi, '<')
		.replace(/&gt;/gi, '>')
		.replace(/&quot;/gi, '"')
		.replace(/&#39;/gi, "'")
		.replace(/\s+/g, ' ')
		.trim()
}

function hasAdjustmentContent(value: Record<string, unknown>): boolean {
	return ['content_text', 'remark_text', 'content', 'text', 'value', 'remark'].some(key => {
		const content = value[key]
		return typeof content === 'string' && content.trim() !== ''
	})
}

function mergeAdjustmentTextParts(parts: string[]): string[] {
	const result: string[] = []
	const seen = new Set<string>()

	for (const part of parts) {
		for (const text of splitAdjustmentText(part)) {
			addUniqueAdjustmentPart(result, seen, text)
		}
	}

	return result
}

function uniqueAdjustmentParts(parts: string[]): string[] {
	const result: string[] = []
	const seen = new Set<string>()
	for (const part of parts) {
		for (const text of splitAdjustmentText(part)) {
			addUniqueAdjustmentPart(result, seen, text)
		}
	}
	return result
}

function addUniqueAdjustmentPart(result: string[], seen: Set<string>, text: string) {
	const key = text.replace(/\s+/g, '')
	if (seen.has(key)) return
	seen.add(key)
	result.push(text)
}

function getAdjustmentColumnWidth(column: AdjustmentColumn): number {
	return column.width || 128
}

function getAdjustmentColumnStyle(column: AdjustmentColumn): Record<string, string> {
	const width = getAdjustmentColumnWidth(column)
	return {
		flex: `0 0 ${width}rpx`,
		width: `${width}rpx`,
		minWidth: `${width}rpx`,
		maxWidth: `${width}rpx`
	}
}

function getPriceColumnStyle(column: PriceColumn): Record<string, string> {
	const width = column.width || 64
	return {
		flex: `0 0 ${width}rpx`,
		width: `${width}rpx`,
		minWidth: `${width}rpx`,
		maxWidth: `${width}rpx`
	}
}

function getAdjustmentCell(row: EnhancedPriceRow, key: string): AdjustmentDisplayCell {
	return row.displayAdjustmentCells?.[key] || {
		show: false,
		rowspan: 1,
		parts: [],
		text: '',
		height: ADJUSTMENT_ROW_HEIGHT_RPX
	}
}

function splitAdjustmentText(value: unknown): string[] {
	return String(value || '')
		.replace(/\u00a0/g, ' ')
		.replace(/\s*\n+\s*/g, '；')
		.split(/[；;\s]+/)
		.map(item => item.trim())
		.filter(item => item !== '' && item !== '--')
}

function splitAdjustmentSummaryText(value: unknown): string[] {
	return String(value || '')
		.replace(/\u00a0/g, ' ')
		.replace(/\s*\n+\s*/g, '；')
		.split(/[；;]/)
		.map(item => item.trim())
		.filter(item => item !== '' && item !== '--')
}

async function loadQuotationTypes() {
	try {
		const res = await getQuotationV2Types({ limit: 30 }) as any
		quotationTypes.value = res.code === 1 && Array.isArray(res.data) ? res.data : []
	} catch (error) {
		quotationTypes.value = []
	}
}

async function loadPriceTheme() {
	try {
		const res = await getOrderSubmitConfig() as any
		const colors = res?.data?.price_detail_theme?.colors
		priceTheme.value = colors && typeof colors === 'object' ? colors : {}
	} catch (error) {
		priceTheme.value = {}
	}
}

function openTypeSheet() {
	if (source.value === 'spider') {
		uni.showToast({ title: '当前报价暂不支持切换', icon: 'none' })
		return
	}
	showTypeSheet.value = true
	if (quotationTypes.value.length === 0) {
		loadQuotationTypes()
	}
}

function switchQuotation(item: QuotationV2Type) {
	datasetId.value = String(item.dataset_id || '')
	priceTypeId.value = String(item.quotation_id || '')
	source.value = 'v2'
	pageTitle.value = item.title || item.dataset_name || item.price_name || '报价查询'
	showTypeSheet.value = false
	keyword.value = ''
	selectedModels.value = []
	activeSeriesKey.value = 'all'
	syncActiveSeriesTabView('all')
	onlyHotModels.value = false
	loadPriceData()
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
	loadPriceTheme()
	if (options?.source) {
		source.value = options.source
	}
	if (options?.dataset_id) {
		datasetId.value = options.dataset_id
	}
	if (options?.item_id) {
		spiderItemId.value = options.item_id
	}
	if (options?.price_date) {
		priceDate.value = options.price_date
	}
	if (options?.show_hot_badge !== undefined) {
		showHotBadge.value = String(options.show_hot_badge) !== '0'
	}
	if (options?.hot_badge_image) {
		hotBadgeImage.value = safeDecode(options.hot_badge_image)
	}
	if (options?.hot_badge_size) {
		const size = Number(options.hot_badge_size)
		if (Number.isFinite(size)) hotBadgeSize.value = Math.max(24, Math.min(size, 80))
	}
	if (source.value === 'spider' && spiderItemId.value) {
		pageTitle.value = options?.title ? safeDecode(options.title) : '报价查询'
		loadPriceData()
		return
	}
	const id = options?.id || options?.quotation_id || ''
	if (id || datasetId.value) {
		priceTypeId.value = id
		pageTitle.value = options?.title ? safeDecode(options.title) : '报价查询'
		loadPriceData()
		loadQuotationTypes()
		return
	}

	uni.showToast({
		title: '缺少参数',
		icon: 'none'
	})
})

onPageScroll((event) => {
	const scrollTop = Number(event.scrollTop || 0)
	currentScrollTop.value = scrollTop
	isScrolled.value = scrollTop > 80
	updateActiveSeriesByScroll()
})
</script>

<style lang="scss" scoped>
@import './price-theme.scss';

.show-price-page {
	--radius: 20rpx;
	--shadow: 0 10rpx 24rpx rgba(31, 41, 55, 0.08);
	--table-font-size: 22rpx;
	--table-price-font-size: 24rpx;
	--table-remark-font-size: 22rpx;
	--table-row-height: 88rpx;
	--table-cell-padding-y: 8rpx;
	--table-cell-padding-x: 2rpx;
	--table-remark-padding: 8rpx 12rpx;
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
	background: linear-gradient(180deg, var(--bg-soft) 0%, var(--bg-main) 70%, rgba(243, 244, 246, 0) 100%);
	z-index: 0;
	pointer-events: none;
}

.custom-navbar {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	z-index: 999;
	backdrop-filter: blur(8rpx);
	background: var(--toolbar-bg);
	border-bottom: 1rpx solid var(--line);

	.navbar-content {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: space-between;
		box-sizing: content-box;
	}

	.navbar-left,
	.navbar-right {
		flex-shrink: 0;
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
			background: var(--bg-soft);
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
	padding: calc(var(--price-navbar-height) + 18rpx) 6rpx calc(160rpx + env(safe-area-inset-bottom));
}

.loading-container {
	display: flex;
	flex-direction: column;
	gap: 20rpx;

	.skeleton-card,
	.skeleton-table {
		border-radius: var(--radius);
		background: linear-gradient(100deg, var(--bg-soft) 30%, var(--bg-card) 45%, var(--bg-soft) 60%);
		background-size: 260% 100%;
		animation: skeleton-shimmer 1.2s linear infinite;
		border: 1rpx solid var(--line);
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
	padding-top: calc(var(--price-navbar-height) + 120rpx);

	.empty-badge {
		font-size: 22rpx;
		color: var(--brand-deep);
		padding: 10rpx 22rpx;
		border-radius: 999rpx;
		background: var(--bg-soft);
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
		background: var(--button-bg);
		color: var(--button-text);
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
	background: var(--bg-card);
	box-shadow: var(--shadow);
	border: 1rpx solid var(--line);

	animation: rise-in 320ms ease-out;

	.summary-top {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.summary-tag {
		padding: 8rpx 20rpx;
		border-radius: 999rpx;
		background: var(--bg-soft);
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
	border: 1rpx solid var(--line);
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
	background: var(--model-head-bg);
	border-bottom: 1rpx solid var(--line);
	position: sticky;
	top: 0;
	z-index: 9;
}

.table-body .model-group {
	border-bottom: 1rpx solid var(--line);
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
	border-bottom: 1rpx solid var(--line);
	background: var(--bg-card);
}

.data-row:nth-child(2n) {
	background: var(--bg-soft);
}

.data-row:last-child {
	border-bottom: none;
}

.remark-column {
	width: 180rpx;
	min-width: 180rpx;
	max-width: 180rpx;
	flex-shrink: 0;
	display: flex;
	flex-direction: column;
	position: relative;

	background: var(--bg-card);
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
	border-right: 1rpx solid var(--line);
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
	color: var(--text-main);
}

.body-cell {
	color: var(--text-sub);
	min-height: var(--table-row-height);
	height: auto;
}

.data-row > .body-cell {
	height: 100%;
	min-height: 100%;
}

.col-model {
	flex-shrink: 0;
	background: var(--bg-soft);
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
	width: 180rpx;
	min-width: 180rpx;
	max-width: 180rpx;
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
	border-bottom: 1rpx solid var(--line);
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
	color: var(--text-sub);
	font-size: 24rpx;
}

.remark-text {
	font-size: var(--table-remark-font-size);
	line-height: 1.45;
	color: var(--text-sub);
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
	background: var(--toolbar-bg);
	backdrop-filter: blur(8rpx);
	border-top: 1rpx solid var(--line);
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
	color: var(--button-text);
	border-radius: 999rpx;
	background: var(--button-bg);
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

.custom-navbar {
	background: var(--button-bg);
	border-bottom: 1rpx solid var(--line);
	color: var(--button-text);

	.navbar-content {
		position: relative;
	}

	.navbar-left {
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;

		:deep(.u-icon) {
			display: flex;
			align-items: center;
			justify-content: center;
		}
	}

	.navbar-center {
		position: absolute;
		left: 50%;
		transform: translateX(-50%);
		min-width: 260rpx;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding: 0 10rpx;
		box-sizing: border-box;
		pointer-events: none;
	}

	.navbar-title {
		display: block;
		width: 100%;
		font-size: 30rpx;
		line-height: 40rpx;
		font-weight: 700;
		text-align: center;
		color: var(--button-text);
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.navbar-subtitle {
		margin-top: 2rpx;
		font-size: 20rpx;
		line-height: 28rpx;
		color: var(--button-text);
	}

	.navbar-capsule-space {
		flex-shrink: 0;
	}
}

.custom-navbar.compact {
	background: var(--button-bg);
}

.price-content,
.loading-container,
.empty-container {
	padding-top: calc(var(--price-navbar-height) + 18rpx);
}

.skeleton-hero {
	height: 220rpx;
	border-radius: 0;
	background: linear-gradient(100deg, var(--button-bg) 30%, var(--brand) 45%, var(--button-bg) 60%);
	background-size: 260% 100%;
	animation: skeleton-shimmer 1.2s linear infinite;
}

.skeleton-card.large {
	height: 520rpx;
}

.quotation-cover {
	position: relative;
	min-height: 230rpx;
	margin: -18rpx -6rpx 0;
	padding: 42rpx 28rpx 26rpx;
	background: linear-gradient(100deg, var(--button-bg) 0%, var(--brand-deep) 58%, var(--brand) 100%);
	overflow: hidden;
}

.cover-main {
	position: relative;
	z-index: 2;
	display: flex;
	align-items: flex-end;
	justify-content: space-between;
	gap: 24rpx;
	min-height: 150rpx;
}

.cover-title {
	flex: 1;
	font-size: 42rpx;
	line-height: 56rpx;
	font-weight: 800;
	color: var(--button-text);
	letter-spacing: 1rpx;
}

.cover-time {
	flex-shrink: 0;
	font-size: 22rpx;
	line-height: 32rpx;
	color: var(--button-text);
	padding-bottom: 8rpx;
}

.watermark {
	position: absolute;
	z-index: 1;
	font-size: 34rpx;
	line-height: 44rpx;
	color: rgba(255, 255, 255, 0.1);
	transform: rotate(-28deg);
	white-space: nowrap;
}

.watermark-a {
	left: 30rpx;
	top: 36rpx;
}

.watermark-b {
	right: -30rpx;
	bottom: 36rpx;
}

.notice-card {
	margin: 0 -6rpx 12rpx;
	padding: 24rpx 18rpx;
	background: var(--notice-bg);
	border-bottom: 1rpx solid var(--line);
	display: flex;
	flex-direction: column;
	align-items: center;
}

.notice-line {
	font-size: 27rpx;
	line-height: 42rpx;
	font-weight: 700;
	color: var(--notice-text);
	text-align: center;
}

.image-quote-card {
	margin-bottom: 12rpx;
	padding: 12rpx;
	background: var(--bg-card);
	border: 1rpx solid var(--line);
	border-radius: 10rpx;
}

.image-quote {
	width: 100%;
	display: block;
	border-radius: 8rpx;
}

.tool-card {
	position: sticky;
	top: var(--price-toolbar-sticky-top);
	z-index: 20;
	margin-bottom: 10rpx;
	padding: 12rpx;
	border-radius: 10rpx;
	background: var(--toolbar-bg);
	border: 1rpx solid var(--line);
	box-shadow: 0 10rpx 24rpx rgba(15, 23, 42, 0.08);
}

.tool-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12rpx;
	margin-bottom: 12rpx;
}

.tool-actions {
	flex-shrink: 0;
	display: flex;
	align-items: center;
	gap: 8rpx;
}

.tool-action {
	height: 52rpx;
	line-height: 52rpx;
	padding: 0 16rpx;
	border-radius: 8rpx;
	box-sizing: border-box;
	border: 1rpx solid var(--line);
	background: var(--bg-soft);
	color: var(--text-main);
	font-size: 22rpx;
	font-weight: 700;
	white-space: nowrap;
}

.tool-action.primary {
	border-color: var(--button-bg);
	background: var(--button-bg);
	color: var(--button-text);
}

.tool-action.active {
	border-color: var(--brand);
	background: var(--warning-bg);
	color: var(--brand);
}

.search-box {
	height: 70rpx;
	border-radius: 35rpx;
	background: var(--bg-soft);
	display: flex;
	align-items: center;
	padding: 0 22rpx;
}

.search-box .iconfont {
	font-size: 26rpx;
	color: var(--text-sub);
	margin-right: 10rpx;
}

.search-input {
	flex: 1;
	height: 70rpx;
	font-size: 26rpx;
	color: var(--text-main);
}

.search-placeholder {
	color: var(--text-sub);
}

.selected-model-scroll {
	width: 100%;
	margin-top: 12rpx;
	white-space: nowrap;
}

.selected-model-list {
	display: inline-flex;
	align-items: center;
	gap: 10rpx;
	min-width: 100%;
}

.selected-model-tag {
	height: 52rpx;
	padding: 0 14rpx;
	border-radius: 26rpx;
	background: var(--warning-bg);
	border: 1rpx solid var(--brand);
	color: var(--brand);
	font-size: 23rpx;
	font-weight: 700;
	display: inline-flex;
	align-items: center;
	gap: 6rpx;
	max-width: 360rpx;
	box-sizing: border-box;
}

.selected-model-tag text {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.selected-model-clear {
	height: 52rpx;
	line-height: 52rpx;
	padding: 0 16rpx;
	border-radius: 26rpx;
	background: var(--bg-soft);
	color: var(--text-sub);
	font-size: 23rpx;
	font-weight: 700;
	display: inline-block;
}

.data-meta {
	flex: 1;
	min-width: 0;
	display: flex;
	align-items: center;
	justify-content: flex-start;
	font-size: 23rpx;
	line-height: 32rpx;
	color: var(--text-sub);
	white-space: nowrap;
	overflow: hidden;
}

.spider-hot-badge {
	flex-shrink: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 10rpx;
}

.spider-hot-badge__image {
	display: block;
	width: 100%;
	height: 100%;
}

.spider-hot-badge__text {
	display: block;
	padding: 2rpx 10rpx;
	border-radius: 999rpx;
	background: var(--notice-bg);
	color: var(--notice-text);
	font-weight: 700;
	white-space: nowrap;
	box-shadow: 0 4rpx 10rpx rgba(245, 158, 11, 0.12);
}

.dot {
	margin: 0 10rpx;
}

.sheet-list {
	display: flex;
	flex-direction: column;
	gap: 10rpx;
}

.sheet-section {
	display: flex;
	flex-direction: column;
	gap: 10rpx;
}

.filter-result-empty {
	margin: 20rpx 0;
	padding: 58rpx 28rpx;
	border-radius: 18rpx;
	background: var(--bg-card);
	border: 1rpx solid var(--line);
	display: flex;
	flex-direction: column;
	align-items: center;
	text-align: center;
}

.filter-empty-title {
	font-size: 30rpx;
	line-height: 42rpx;
	font-weight: 800;
	color: var(--text-main);
}

.filter-empty-desc {
	margin-top: 8rpx;
	font-size: 24rpx;
	line-height: 36rpx;
	color: var(--text-sub);
}

.filter-empty-actions {
	margin-top: 24rpx;
	display: flex;
	align-items: center;
	gap: 14rpx;
}

.filter-empty-btn {
	height: 62rpx;
	line-height: 62rpx;
	padding: 0 22rpx;
	border-radius: 31rpx;
	background: var(--bg-soft);
	color: var(--text-main);
	font-size: 24rpx;
	font-weight: 800;
}

.filter-empty-btn.primary {
	background: var(--button-bg);
	color: var(--button-text);
}

.section-title {
	margin: 6rpx 0 2rpx;
	padding: 18rpx 20rpx;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 18rpx;
	border-radius: 12rpx;
	background: var(--bg-soft);
	border: 1rpx solid var(--line);
	font-size: 26rpx;
	line-height: 36rpx;
	font-weight: 700;
	color: var(--text-main);
}

.section-sub {
	flex-shrink: 0;
	max-width: 430rpx;
	font-size: 22rpx;
	font-weight: 400;
	color: var(--text-sub);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.model-card {
	background: var(--bg-card);
	border: none;
	border-radius: 0;
	overflow: hidden;
	box-shadow: none;
}

.model-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 14rpx 10rpx 12rpx;
	background: var(--model-head-bg);
	border-bottom: 1rpx solid var(--line);
}

.model-name-block {
	min-width: 0;
	display: flex;
	align-items: center;
	gap: 10rpx;
}

.model-brand {
	flex-shrink: 0;
	height: 32rpx;
	line-height: 32rpx;
	padding: 0 9rpx;
	border-radius: 4rpx;
	background: var(--model-brand-bg);
	color: var(--model-brand-text);
	font-size: 19rpx;
	font-weight: 700;
}

.series-tab-scroll {
	width: 100%;
	margin-top: 12rpx;
	white-space: nowrap;
}

.series-tab-list {
	display: flex;
	align-items: center;
	gap: 10rpx;
	width: max-content;
	min-width: max-content;
	padding-right: 20rpx;
}

.series-tab {
	flex-shrink: 0;
	height: 56rpx;
	padding: 0 18rpx;
	border-radius: 28rpx;
	background: var(--series-inactive-bg);
	border: 1rpx solid var(--line);
	color: var(--series-inactive-text);
	font-size: 24rpx;
	font-weight: 800;
	display: inline-flex;
	align-items: center;
	gap: 8rpx;
	box-sizing: border-box;
	white-space: nowrap;
}

.series-tab.active {
	background: var(--series-active-bg);
	border-color: var(--series-active-bg);
	color: var(--series-active-text);
	box-shadow: 0 8rpx 18rpx rgba(15, 23, 42, 0.16);
}

.series-tab-count {
	min-width: 30rpx;
	height: 30rpx;
	line-height: 30rpx;
	padding: 0 8rpx;
	border-radius: 15rpx;
	background: rgba(148, 163, 184, 0.16);
	text-align: center;
	font-size: 20rpx;
}

.series-tab.active .series-tab-count {
	background: rgba(255, 255, 255, 0.18);
}

.model-name {
	font-size: 27rpx;
	line-height: 36rpx;
	font-weight: 800;
	color: var(--text-main);
	text-align: left;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.capacity-count {
	flex-shrink: 0;
	font-size: 20rpx;
	line-height: 28rpx;
	color: var(--text-sub);
	margin-left: 16rpx;
}

.price-table-wrap {
	width: 100%;
	overflow: hidden;
}

.mobile-price-table {
	width: 100%;
}

.price-row {
	display: flex;
	height: 78rpx;
	min-height: 78rpx;
	border-bottom: 1rpx solid var(--line);
}

.price-row:last-child {
	border-bottom: none;
}

.table-head {
	height: 90rpx;
	min-height: 90rpx;
	background: var(--button-bg);
	color: var(--button-text);
}

.price-cell {
	box-sizing: border-box;
	min-width: 0;
	padding: 4rpx 2rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	border-right: 1rpx solid var(--line);
	text-align: center;
	word-break: break-word;
}

.price-cell:last-child {
	border-right: none;
}

.capacity-cell {
	flex: 0 0 82rpx;
	width: 82rpx;
	min-width: 82rpx;
	max-width: 82rpx;
	font-size: 18rpx;
	font-weight: 400;
	background: inherit;
}

.capacity-body {
	background: var(--bg-soft);
	color: var(--text-main);
	font-size: 22rpx;
	line-height: 28rpx;
	white-space: normal;
	overflow-wrap: anywhere;
}

.price-head-cell {
	flex: 1 1 0;
	font-size: 17rpx;
	line-height: 22rpx;
	font-weight: 700;
	color: var(--button-text);
	overflow: hidden;
}

.price-head-cell text {
	display: -webkit-box;
	-webkit-line-clamp: 4;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.adjustment-head-cell {
	flex-shrink: 0;
	width: 228rpx;
	min-width: 228rpx;
	max-width: 228rpx;
	font-size: 18rpx;
	line-height: 24rpx;
	font-weight: 800;
	color: var(--button-text);
	background: var(--brand-deep);
}

.price-body-wrap {
	display: flex;
	align-items: stretch;
}

.price-main-columns {
	flex: 1;
	min-width: 0;
}

.price-body-cell {
	flex: 1 1 0;
	background: var(--bg-soft);
	overflow: hidden;
}

.price-row:nth-child(2n + 1) .price-body-cell {
	background: var(--bg-card);
}

.price-value {
	font-size: 22rpx;
	line-height: 28rpx;
	font-weight: 800;
	color: var(--price);
	white-space: nowrap;
}

.empty-cell {
	font-size: 22rpx;
	color: var(--text-sub);
}

.adjustment-column {
	position: relative;
	flex-shrink: 0;
	width: 228rpx;
	min-width: 228rpx;
	max-width: 228rpx;
	background: var(--bg-card);
}

.adjustment-cell-wrapper {
	position: relative;
	height: 68rpx;
	min-height: 68rpx;
	border-bottom: 1rpx solid var(--line);
	box-sizing: border-box;
}

.adjustment-cell-wrapper:last-child {
	border-bottom: none;
}

.adjustment-body-cell {
	position: absolute;
	left: 0;
	right: 0;
	top: 0;
	z-index: 2;
	width: 100%;
	min-width: 228rpx;
	max-width: 228rpx;
	padding: 3rpx 5rpx;
	background: var(--bg-card);
	border-right: none;
	border-bottom: 1rpx solid var(--line);
	align-items: center;
	justify-content: center;
}

.adjustment-item-list {
	width: 100%;
	display: flex;
	flex-direction: column;
	align-items: stretch;
	justify-content: center;
	gap: 2rpx;
}

.adjustment-item-text {
	display: block;
	width: 100%;
	font-size: 15rpx;
	line-height: 19rpx;
	font-weight: 600;
	color: var(--text-main);
	text-align: center;
	white-space: pre-wrap;
	word-break: break-word;
}

.adjustment-cell-text {
	width: 100%;
	font-size: 15rpx;
	line-height: 19rpx;
	font-weight: 600;
	color: var(--text-main);
	text-align: center;
	white-space: pre-wrap;
	word-break: break-word;
}

.adjustment-box {
	padding: 18rpx 20rpx 22rpx;
	background: var(--bg-card);
	border-top: 1rpx solid var(--line);
}

.adjustment-title {
	margin-bottom: 12rpx;
	font-size: 25rpx;
	line-height: 34rpx;
	font-weight: 800;
	color: var(--text-main);
}

.adjustment-row {
	display: flex;
	align-items: flex-start;
	gap: 14rpx;
	padding: 10rpx 0;
	border-top: 1rpx dashed var(--line);
}

.adjustment-row:first-of-type {
	border-top: none;
}

.adjustment-label {
	flex-shrink: 0;
	min-width: 116rpx;
	font-size: 23rpx;
	line-height: 34rpx;
	font-weight: 700;
	color: var(--text-main);
}

.adjustment-text {
	flex: 1;
	font-size: 24rpx;
	line-height: 36rpx;
	font-weight: 600;
	color: var(--text-main);
	white-space: pre-wrap;
	word-break: break-word;
}

.sheet-mask {
	position: fixed;
	left: 0;
	right: 0;
	top: 0;
	bottom: 0;
	z-index: 2000;
	background: rgba(0, 0, 0, 0.42);
	display: flex;
	align-items: flex-end;
}

.type-sheet {
	width: 100%;
	max-height: 1100rpx;
	padding: 28rpx 24rpx calc(28rpx + env(safe-area-inset-bottom));
	background: var(--bg-card);
	border-radius: 28rpx 28rpx 0 0;
	box-sizing: border-box;
	overflow-y: auto;
}

.type-sheet-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 18rpx;
}

.type-title {
	font-size: 32rpx;
	line-height: 44rpx;
	font-weight: 800;
	color: var(--text-main);
}

.type-close {
	font-size: 24rpx;
	color: var(--text-sub);
	padding: 10rpx 18rpx;
	background: var(--bg-soft);
	border-radius: 22rpx;
}

.type-empty {
	padding: 50rpx 0;
	text-align: center;
	font-size: 26rpx;
	color: var(--text-sub);
}

.type-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 22rpx 8rpx;
	border-top: 1rpx solid var(--line);
}

.type-name {
	display: block;
	font-size: 29rpx;
	line-height: 40rpx;
	font-weight: 700;
	color: var(--text-main);
}

.type-meta {
	display: block;
	margin-top: 6rpx;
	font-size: 23rpx;
	line-height: 32rpx;
	color: var(--text-sub);
}

.type-check {
	display: none;
	font-size: 30rpx;
	color: var(--brand);
	font-weight: 800;
}

.type-item.active .type-check {
	display: block;
}

</style>
