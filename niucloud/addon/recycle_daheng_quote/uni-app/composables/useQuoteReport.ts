import { computed, reactive, ref, watch } from 'vue'
import { getQuotationV2Detail, getQuotationV2Types } from '@/addon/recycle_daheng_quote/api/quotation'

// 本地存储读写(水印配置存本地，不进数据库)
function readStore<T>(key: string, def: T): T {
	try {
		const v = uni.getStorageSync(key)
		return v === '' || v === undefined || v === null ? def : v
	} catch (e) {
		return def
	}
}
function writeStore(key: string, val: any) {
	try {
		uni.setStorageSync(key, val)
	} catch (e) {
		/* ignore */
	}
}

/**
 * 生成报价单 —— 单例状态层（多报价单 / 多 sheet）
 * 进入某个报价项后，列出其所属「一级分类」下的所有报价项，每个为一张报价单，可单独勾选/调价
 */

export interface ReportTheme {
	key: string
	name: string
	bannerBg: string // 纯色兜底
	bannerGrad?: [string, string] // 渐变两端色(可选)
	bannerText: string
	headBg: string
	headText: string
	bodyBg: string
	bodyText: string
	subText: string
	priceText: string
	lineColor: string
	noticeText: string
	watermarkColor: string
}

// 9 套自有配色(部分渐变 banner);bannerBg 支持纯色或 linear-gradient
export const REPORT_THEMES: ReportTheme[] = [
	{ key: 'midnight', name: '极夜', bannerBg: '#1a1d27', bannerGrad: ['#262b3a', '#0f1117'], bannerText: '#ffffff', headBg: '#2b3040', headText: '#ffffff', bodyBg: '#ffffff', bodyText: '#1f2937', subText: '#6b7280', priceText: '#11161f', lineColor: '#e6e8ec', noticeText: '#e0a23c', watermarkColor: 'rgba(0,0,0,0.05)' },
	{ key: 'aurora', name: '极光', bannerBg: '#4a5fe0', bannerGrad: ['#3a8ffe', '#7b5cff'], bannerText: '#eef4ff', headBg: '#4a6cf0', headText: '#eef4ff', bodyBg: '#fbfdff', bodyText: '#1f2937', subText: '#5a7196', priceText: '#3a5bd0', lineColor: '#dbe6f8', noticeText: '#3f7ad6', watermarkColor: 'rgba(58,91,208,0.06)' },
	{ key: 'sunset', name: '日落', bannerBg: '#ff6a52', bannerGrad: ['#ff8a3c', '#ff5b6a'], bannerText: '#fff6ee', headBg: '#ff7a4a', headText: '#fff6ee', bodyBg: '#fffdf9', bodyText: '#3a241c', subText: '#a07b6a', priceText: '#e6452e', lineColor: '#f8ddd0', noticeText: '#e6552e', watermarkColor: 'rgba(230,69,46,0.06)' },
	{ key: 'jade', name: '碧野', bannerBg: '#18a88f', bannerGrad: ['#2bb9a3', '#149e7e'], bannerText: '#effaf6', headBg: '#23a98f', headText: '#effaf6', bodyBg: '#fbfefb', bodyText: '#23362f', subText: '#6f8a80', priceText: '#147a63', lineColor: '#d4ebe2', noticeText: '#1e9b7e', watermarkColor: 'rgba(20,122,99,0.06)' },
	{ key: 'amethyst', name: '紫晶', bannerBg: '#7a52e0', bannerGrad: ['#9a6cf0', '#6a3fd6'], bannerText: '#f3eeff', headBg: '#7e54e0', headText: '#f3eeff', bodyBg: '#fdfbff', bodyText: '#2c2440', subText: '#7a6e96', priceText: '#6a3fd6', lineColor: '#e6ddf6', noticeText: '#7a4fd6', watermarkColor: 'rgba(106,63,214,0.06)' },
	{ key: 'sakura', name: '樱绯', bannerBg: '#ff6f9c', bannerGrad: ['#ff86b3', '#ff5b8a'], bannerText: '#fff3f7', headBg: '#ff6f9c', headText: '#fff3f7', bodyBg: '#fffbfc', bodyText: '#3a2630', subText: '#a07a88', priceText: '#e23b6d', lineColor: '#f8d9e4', noticeText: '#e6557f', watermarkColor: 'rgba(226,59,109,0.06)' },
	{ key: 'gold', name: '鎏金', bannerBg: '#9c7a3f', bannerGrad: ['#c79a4e', '#8a6a37'], bannerText: '#fff7e8', headBg: '#a4824a', headText: '#fff7e8', bodyBg: '#fffdf8', bodyText: '#4a3b22', subText: '#9a865f', priceText: '#8a6a37', lineColor: '#ecdcc0', noticeText: '#b8801f', watermarkColor: 'rgba(138,106,55,0.06)' },
	{ key: 'ink', name: '墨蓝', bannerBg: '#1d3a5f', bannerText: '#eaf2fb', headBg: '#274c78', headText: '#eaf2fb', bodyBg: '#ffffff', bodyText: '#1f2a37', subText: '#647387', priceText: '#1d3a5f', lineColor: '#dde6ef', noticeText: '#2f6fb0', watermarkColor: 'rgba(29,58,95,0.05)' },
	{ key: 'plain', name: '简白', bannerBg: '#f2f3f5', bannerText: '#1f2937', headBg: '#f7f8fa', headText: '#1f2937', bodyBg: '#ffffff', bodyText: '#1f2937', subText: '#6b7280', priceText: '#2563eb', lineColor: '#e5e7eb', noticeText: '#f59e0b', watermarkColor: 'rgba(0,0,0,0.045)' }
]

const DEFAULT_NOTICE = '温馨提示：报价仅供参考，最终价格以质检结果为准'

export interface ReportSheet {
	id: number | string
	name: string
	brand: string // 一级分类(品牌)名,出图标头用
	selected: boolean
	adjustType: 'none' | 'down' | 'up' | 'ratioDown' | 'ratioUp'
	adjustValue: number
	detail: any | null
	// 个性化:按「行id -> 等级名」覆盖价格 / 标记优势
	overrides: Record<string, Record<string, number | string>>
	marks: Record<string, Record<string, boolean>>
	// 选中的型号(空数组=全部);出图时按它过滤
	selectedModels: string[]
}

const loading = ref(false)
const source = ref('spider')
const entryItemId = ref<number | string>(0)
const watermark = ref<string>(readStore('qr_dh_watermark', ''))
// 水印样式（lime-painter 不支持真旋转，angle 用斜向错位平铺近似）；本地持久化
const wm = reactive(
	Object.assign({ size: 34, opacity: 8, color: '#000000', angle: 30 }, readStore('qr_dh_wm', {}) as Record<string, any>)
)
const themeKey = ref(readStore('qr_dh_theme', 'midnight'))

// 改动即写本地，下次进来自动带出
watch(watermark, v => writeStore('qr_dh_watermark', v))
watch(wm, () => writeStore('qr_dh_wm', { size: wm.size, opacity: wm.opacity, color: wm.color, angle: wm.angle }), { deep: true })
watch(themeKey, v => writeStore('qr_dh_theme', v))
const sheets = ref<ReportSheet[]>([])

const theme = computed(() => REPORT_THEMES.find(t => t.key === themeKey.value) || REPORT_THEMES[0])

/* ---------------- 纯函数：基于某个 detail + 调价 计算出图数据 ---------------- */

export function sheetColumns(detail: any): string[] {
	const cols = Array.isArray(detail?.columns) ? detail.columns : []
	if (cols.length) return cols.map((c: any) => String(c || '').trim()).filter(Boolean)
	const firstRow = (detail?.rows || [])[0]
	return Array.isArray(firstRow?.columns) ? firstRow.columns.map((c: any) => String(c || '').trim()) : []
}

function round10(n: number): number {
	return Math.round(n / 10) * 10
}
function applyAdjust(raw: number, type: string, value: number): number {
	if (!Number.isFinite(raw)) return raw
	const v = Number(value || 0)
	if (type === 'down') return Math.max(0, raw - v)
	if (type === 'up') return raw + v
	// 按比例:按百分比上调/下调，结果取 10 的整数
	if (type === 'ratioDown') return Math.max(0, round10(raw * (1 - v / 100)))
	if (type === 'ratioUp') return Math.max(0, round10(raw * (1 + v / 100)))
	return raw
}

export function sheetRows(
	detail: any,
	adjustType = 'none',
	adjustValue = 0,
	overrides: Record<string, Record<string, any>> = {},
	marks: Record<string, Record<string, boolean>> = {}
) {
	const list = Array.isArray(detail?.rows) ? detail.rows : []
	return list.map((row: any) => {
		const rowId = String(row.id)
		const ov = overrides[rowId] || {}
		const mk = marks[rowId] || {}
		const rowCols = Array.isArray(row.columns) ? row.columns.map((c: any) => String(c || '').trim()) : []
		const cols = rowCols.length ? rowCols : sheetColumns(detail)
		const finals = Array.isArray(row.final_prices) ? row.final_prices : []
		const priceMap: Record<string, string> = {}
		rowCols.forEach((name: string, idx: number) => {
			priceMap[name] = String(finals[idx] ?? '')
		})
		const prices = cols.map(name => {
			// 个性化改价优先
			if (ov[name] !== undefined && ov[name] !== '' && ov[name] !== null) return String(ov[name])
			const raw = Number(priceMap[name])
			if (!priceMap[name] || !Number.isFinite(raw)) return '-'
			return String(applyAdjust(raw, adjustType, adjustValue))
		})
		const markArr = cols.map(name => !!mk[name])
		const remarkColumns = Array.isArray(row.remark_columns) ? row.remark_columns.map((c: any) => String(c || '').trim()).filter(Boolean) : []
		const rawRemarkValues = Array.isArray(row.remark_values) ? row.remark_values : []
		const remarkValues = remarkColumns.map((name: string, idx: number) => String(rawRemarkValues[idx] || ''))
		const remarkText = String(row.remark || '')
		if (!remarkColumns.length && remarkText.trim()) {
			remarkColumns.push('备注')
			remarkValues.push(remarkText)
		}
		const remarkMap: Record<string, string> = {}
		remarkColumns.forEach((name: string, idx: number) => {
			remarkMap[name] = remarkValues[idx] || ''
		})
		return {
			rowId,
			model: String(row.model_name || row.tab || '-'),
			capacity: String(row.capacity_name || row.capacity || ''),
			columns: cols,
			prices,
			marks: markArr,
			remark: remarkText,
			remarkColumns,
			remarkValues,
			remarkMap,
			remarkRowspan: Math.max(1, Number(row.remark_rowspan || row.remarkRowspan || 1)),
			remarkHidden: Number(row.remark_hidden || row.remarkHidden || 0) === 1
		}
	})
}

export function sheetTitle(name: string, brand = '') {
	const n = String(name || '回收报价')
	const b = String(brand || '').trim()
	const head = b && !n.includes(b) ? `${b} ${n}` : n
	return head + '回收报价'
}

export function sheetNoticeLines(detail: any): string[] {
	const text = String(detail?.notice_text || DEFAULT_NOTICE).replace(/\r\n/g, '\n').replace(/\r/g, '\n')
	return text.split('\n').map(s => s.trim()).filter(Boolean)
}

export function sheetPriceDate(detail: any) {
	return String(detail?.price_date || '')
}

/* ---------------- 载入 ---------------- */
// daheng 无分类树:每个「数据集(dataset)」= 一张报价单,列表来自 types()

async function load(id: number | string, src = 'daheng') {
	entryItemId.value = id
	source.value = src
	loading.value = true
	try {
		const detailRes: any = await getQuotationV2Detail({ dataset_id: id })
		const detail = detailRes?.data || {}

		// 列出所有可展示数据集,每个=一张报价单
		let list: any[] = []
		try {
			const typesRes: any = await getQuotationV2Types({ limit: 50 })
			list = Array.isArray(typesRes?.data) ? typesRes.data : []
		} catch (e) {
			list = []
		}
		if (!Array.isArray(list) || !list.length) {
			list = [{ dataset_id: id, id, title: detail?.name || '报价单' }]
		}

		sheets.value = list.map((it: any) => {
			const sid = it.dataset_id || it.id
			return reactive({
				id: sid,
				name: String(it.title || it.dataset_name || it.price_name || it.name || '报价单'),
				brand: '',
				selected: true,
				adjustType: 'none' as const,
				adjustValue: 0,
				detail: String(sid) === String(id) ? detail : null,
				overrides: {},
				marks: {},
				selectedModels: []
			})
		})
	} finally {
		loading.value = false
	}
}

async function ensureDetail(sheet: ReportSheet) {
	if (sheet.detail) return sheet.detail
	const res: any = await getQuotationV2Detail({ dataset_id: sheet.id })
	sheet.detail = res?.data || {}
	return sheet.detail
}

function setTheme(key: string) {
	themeKey.value = key
}

export function useQuoteReport() {
	return {
		loading,
		source,
		entryItemId,
		watermark,
		wm,
		themeKey,
		sheets,
		REPORT_THEMES,
		theme,
		load,
		ensureDetail,
		setTheme,
		// 纯函数
		sheetColumns,
		sheetRows,
		sheetTitle,
		sheetNoticeLines,
		sheetPriceDate
	}
}
