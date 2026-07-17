<template>
	<view class="show-price-page" :style="[pageStyleVars, themeStyleVars]">
		<PricePageNavbar
			:title="navbarTitle"
			:date="priceDateDisplay"
			:compact="isScrolled"
			:navbar-style="customNavbarStyle"
			:content-style="navbarContentStyle"
			:side-style="navbarSideStyle"
			:center-style="navbarCenterStyle"
			@back="goBack"
		/>

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

			<PricePageToolbar
				v-if="tableData.length > 0"
				v-model:keyword="keyword"
				:model-count="filteredModelCount"
				:row-count="filteredRowCount"
				:selected-models="selectedModels"
				:series-tabs="seriesTabs"
				:active-series-key="activeSeriesKey"
				:active-series-tab-view-id="activeSeriesTabViewId"
				:show-hot-badge="showSpiderHotBadge"
				:hot-badge-image="hotBadgeImage"
				:hot-badge-box-style="hotBadgeBoxStyle"
				:hot-badge-text-style="hotBadgeTextStyle"
				@filter="showModelFilter = true"
				@type="openTypeSheet"
				@refresh="loadPriceData"
				@remove-model="removeSelectedModel"
				@clear-models="applyModelFilter([])"
				@select-series="selectSeries"
			/>

			<PriceDataSheets v-if="groupedTables.length > 0" :tables="groupedTables" @trend="openTrend" />
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
				<view class="report-btn" @click="goReport">
					生成报价单
					<text class="report-badge">会员免费</text>
				</view>
				<view class="order-btn" @click="goToOrder">去下单</view>
			</view>
		</view>

		<QuoteTypeSheet
			:visible="showTypeSheet"
			:source="source"
			:spider-sheets="spiderSheets"
			:spider-item-id="spiderItemId"
			:quotation-types="quotationTypes"
			:dataset-id="datasetId"
			:price-type-id="priceTypeId"
			@close="showTypeSheet = false"
			@select-spider="switchSpiderSheet"
			@select-quotation="switchQuotation"
		/>
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
		<PriceTrendPopup v-model:visible="trendVisible" :row-id="trendRowId" :title="trendTitle" :active-column="trendColumn" :theme="priceTheme" />
	</view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { onLoad, onPageScroll } from '@dcloudio/uni-app'
import { getQuoteSpiderDetail, getQuoteSpiderCategoryTree, getQuoteSpiderItems, getQuoteSpiderReportPermission, type QuoteSpiderItem, type QuotationPriceData } from '@/addon/recycle_quote_spider/api/quotation'
import { getOrderSubmitConfig } from '@/addon/hsx_recycle/api/order'
import ModelFilterPopup from './components/ModelFilterPopup.vue'
import PriceDataSheets from './components/PriceDataSheets.vue'
import PricePageNavbar from './components/PricePageNavbar.vue'
import PricePageToolbar, { type PriceSeriesTab } from './components/PricePageToolbar.vue'
import PriceTrendPopup from './components/PriceTrendPopup.vue'
import QuoteTypeSheet from './components/QuoteTypeSheet.vue'
import { latestQuoteDate } from './utils/quoteDate'
import {
	calculateTableColumnWidths,
	collectAdjustmentColumns,
	isRecord,
	normalizeModelName,
	normalizePrices,
	resolveRowSeriesKey,
	resolveRowSeriesName,
	type GroupedTable
} from './utils/priceTable'
import { normalizeSpiderRows, resolveSpiderImage } from './utils/spiderQuote'

const DEFAULT_NOTICE_TEXT = '温馨提示：报价仅供参考，最终价格以质检结果为准'

interface QuotationV2Type {
	id: number
	dataset_id: number
	quotation_id: number
	price_name: string
	dataset_name: string
	title: string
	last_sync_at_text: string
	model_count: number
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

async function getQuotationV2Types(_params: any = {}) {
	return { code: 0, data: [], msg: '请安装DH速收报价插件' }
}

async function getQuotationV2PriceList(_params: any) {
	return { code: 0, data: [], msg: '请安装DH速收报价插件' }
}

const ORDER_PAGE_URL = '/addon/hsx_recycle/pages/order/order'

const priceTypeId = ref('')
const datasetId = ref('')
const spiderItemId = ref('')
const spiderCategoryId = ref(0)
const spiderSourceId = ref(0)
const spiderSheets = ref<any[]>([])
const source = ref('')
const priceDate = ref('')
const quoteUpdateAt = ref<number | string>('')
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
const trendVisible = ref(false)
const trendRowId = ref<number | string>(0)
const trendTitle = ref('')
const trendColumn = ref('')

function openTrend(row: Record<string, any>, columnName = '') {
	if (!row?.id) return
	trendRowId.value = row.id
	trendColumn.value = columnName
	trendTitle.value = [row.goods_name, row.capacity, columnName].filter(Boolean).join(' · ')
	trendVisible.value = true
}

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

const seriesTabs = computed<PriceSeriesTab[]>(() => {
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
	// 自动同步会更新主记录，人工调价可能只更新型号行，两种时间都要参与比较。
	const rowDates = tableData.value.flatMap(item => [item.update_at, item.price_date, item.create_at])
	return latestQuoteDate([quoteUpdateAt.value, priceDate.value, ...rowDates]) || '--'
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
			quoteUpdateAt.value = ''
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
			quoteUpdateAt.value = item.last_sync_at || item.price_date || item.update_at || item.update_at_text || item.last_sync_at_text || ''
			spiderCategoryId.value = Number((item as any).category_id || 0)
			spiderSourceId.value = Number((item as any).source_id || 0)
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

async function goReport() {
	if (!spiderItemId.value) {
		uni.showToast({ title: '当前报价不支持生成', icon: 'none' })
		return
	}
	// 会员权益校验:仅开通「报价单生成」的会员可用
	let allowed = false
	try {
		const res = (await getQuoteSpiderReportPermission()) as any
		allowed = res?.code === 1 && Number(res?.data?.allowed || 0) === 1
	} catch (e) {
		allowed = false
	}
	if (!allowed) {
		uni.showModal({
			title: '会员专享',
			content: '「生成报价单」为会员专享功能，开通会员后即可使用',
			confirmText: '去开通',
			cancelText: '取消',
			success: r => {
				if (r.confirm) uni.navigateTo({ url: '/app/pages/member/level' })
			}
		})
		return
	}
	uni.navigateTo({
		url: `/addon/recycle_quote_spider/pages/report/config?id=${encodeURIComponent(spiderItemId.value)}&source=spider`
	})
}

function goBack() {
	uni.navigateBack()
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
	showTypeSheet.value = true
	if (source.value === 'spider') {
		loadSpiderSheets()
		return
	}
	if (quotationTypes.value.length === 0) {
		loadQuotationTypes()
	}
}

// 找一级分类祖先
function topAncestorCat(tree: any[], catId: number): number {
	const map: Record<number, number> = {}
	const walk = (nodes: any[]) => {
		;(nodes || []).forEach(n => {
			map[Number(n.id)] = Number(n.parent_id || 0)
			if (Array.isArray(n.children)) walk(n.children)
		})
	}
	walk(tree)
	let cur = Number(catId || 0)
	let guard = 0
	while (cur && map[cur] && guard < 30) {
		cur = map[cur]
		guard++
	}
	return cur || Number(catId || 0)
}

async function loadSpiderSheets() {
	try {
		const treeRes = (await getQuoteSpiderCategoryTree({ source_id: spiderSourceId.value })) as any
		const tree = Array.isArray(treeRes?.data) ? treeRes.data : []
		const rootId = topAncestorCat(tree, spiderCategoryId.value)
		const res = (await getQuoteSpiderItems({ source_id: spiderSourceId.value, category_id: rootId })) as any
		spiderSheets.value = res?.data?.data || res?.data || []
	} catch (e) {
		spiderSheets.value = []
	}
}

function switchSpiderSheet(it: any) {
	spiderItemId.value = String(it.id)
	pageTitle.value = it.name || it.title || '报价查询'
	showTypeSheet.value = false
	keyword.value = ''
	selectedModels.value = []
	activeSeriesKey.value = 'all'
	syncActiveSeriesTabView('all')
	onlyHotModels.value = false
	loadSpiderPriceData()
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
	if (options?.price_date) {
		priceDate.value = options.price_date
	}
	// 旧参数仍兼容（新入口已不再下发），不传则用默认值
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

	// 数据集 / 其它来源的通用报价：保留原逻辑
	const datasetIdVal = options?.dataset_id || ''
	const quotationIdVal = options?.quotation_id || ''
	const explicitOther = !!options?.source && options.source !== 'spider'
	if (explicitOther || datasetIdVal || quotationIdVal) {
		source.value = options?.source || ''
		datasetId.value = datasetIdVal
		priceTypeId.value = options?.id || quotationIdVal || ''
		if (priceTypeId.value || datasetId.value) {
			pageTitle.value = options?.title ? safeDecode(options.title) : '报价查询'
			loadPriceData()
			loadQuotationTypes()
			return
		}
	}

	// 本插件报价：入口只需 id（兼容旧的 item_id），标题由详情里的名称决定
	const itemId = options?.item_id || options?.id || ''
	if (itemId) {
		source.value = 'spider'
		spiderItemId.value = itemId
		pageTitle.value = options?.title ? safeDecode(options.title) : '报价查询'
		loadPriceData()
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
@import './show-price-page.scss';
</style>
