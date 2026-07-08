<template>
	<view class="report-preview">
		<view class="preview-stage">
			<view v-if="allDone" class="result" :class="{ single: imgPaths.length <= 1 }">
				<!-- 顶部缩略图条:点选 / 横滑,与大图联动 -->
				<scroll-view v-if="imgPaths.length > 1" scroll-x class="thumb-strip" :scroll-into-view="'thumb' + activeImg" scroll-with-animation>
					<view
						v-for="(p, i) in imgPaths"
						:key="i"
						:id="'thumb' + i"
						class="thumb"
						:class="{ active: i === activeImg }"
						@click="activeImg = i"
					>
						<image :src="p" mode="aspectFill" class="thumb-img" />
						<text class="thumb-idx">{{ i + 1 }}</text>
					</view>
				</scroll-view>

				<!-- 大图:左右滑切换 -->
				<swiper class="main-swiper" :current="activeImg" @change="onSwiperChange">
					<swiper-item v-for="(p, i) in imgPaths" :key="i">
						<scroll-view scroll-y class="main-scroll">
							<image :src="p" mode="widthFix" class="main-img" />
						</scroll-view>
					</swiper-item>
				</swiper>
			</view>

			<template v-else>
				<l-painter
					v-if="currentBoard"
					ref="painterRef"
					:board="currentBoard"
					is-canvas-to-temp-file-path
					path-type="url"
					file-type="jpg"
					class="preview-canvas"
					@success="onSuccess"
					@done="onSuccess"
					@fail="onFail"
				/>
				<!-- 不透明遮罩盖住正在变化的画布，避免大小跳动 -->
				<view class="gen-overlay">
					<view class="gen-card">
						<text class="loading-pct">{{ imgPaths.length }}/{{ boards.length || 1 }}</text>
						<text class="loading-text">报价单生成中…</text>
						<view class="gen-bar"><view class="gen-bar-fill" :style="{ width: genPct + '%' }"></view></view>
					</view>
				</view>
			</template>
		</view>

		<view class="preview-footer" @touchmove.stop.prevent>
			<view class="footer-btn ghost" :class="{ disabled: !allDone }" @click="saveCurrent">保存本张</view>
			<view class="footer-btn primary" :class="{ disabled: !allDone }" @click="saveAll">全部保存</view>
		</view>
	</view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onUnload } from '@dcloudio/uni-app'
import LPainter from 'lime-painter/components/l-painter/l-painter.vue'
import { useQuoteReport } from '@/addon/recycle_quote_spider/composables/useQuoteReport'

const { sheets, theme, watermark, wm, ensureDetail, sheetColumns, sheetRows, sheetTitle, sheetNoticeLines, sheetPriceDate } = useQuoteReport()

// lime-painter 渐变格式:linear-gradient(,#a 0%, #b 100%)（不支持角度，必须带百分比）
function bannerBgCss(t: any) {
	return t.bannerGrad ? `linear-gradient(,${t.bannerGrad[0]} 0%, ${t.bannerGrad[1]} 100%)` : t.bannerBg
}

function hexToRgba(hex: string, a: number) {
	const h = String(hex || '#000000').replace('#', '')
	const n = h.length === 3 ? h.split('').map(c => c + c).join('') : h
	const r = parseInt(n.slice(0, 2), 16) || 0
	const g = parseInt(n.slice(2, 4), 16) || 0
	const b = parseInt(n.slice(4, 6), 16) || 0
	return `rgba(${r},${g},${b},${a})`
}

const PAGE_W = 1100
// 双列模式:每列宽 + 列间距(整图宽 = COL_W*2 + COL_GAP ≈ 2024)
const COL_W = 1000
const COL_GAP = 24
const MODEL_W = 200
const CAP_W = 130
const LH = 26
const HEAD_LH = 28
const MODEL_LH = 30
const REMARK_LH = 26
const VPAD = 14
const FONT_BODY = 20
const FONT_HEAD = 22
const FONT_MODEL = 22
const FONT_REMARK = 18
const FOOTER_H = 56
const REMARK_W = 220

const boards = ref<any[]>([])
const imgPaths = ref<string[]>([])
const curRender = ref(0)
const painterRef = ref<any>(null)
const activeImg = ref(0)

function onSwiperChange(e: any) {
	activeImg.value = Number(e?.detail?.current || 0)
}

const currentBoard = computed(() => boards.value[curRender.value] || null)
const allDone = computed(() => boards.value.length > 0 && imgPaths.value.length >= boards.value.length)
const genPct = computed(() => (boards.value.length ? Math.round((imgPaths.value.length / boards.value.length) * 100) : 0))

/* ---------------- 文本估算 ---------------- */
function textUnits(seg: string) {
	let u = 0
	for (const ch of seg) u += /[\x00-\xff]/.test(ch) ? 0.55 : 1
	return u
}
function estLines(text: any, widthRpx: number, fontRpx: number) {
	const s = String(text === null || text === undefined ? '' : text)
	const usable = Math.max(fontRpx, widthRpx - 12)
	const perLine = Math.max(1, Math.floor(usable / fontRpx))
	let lines = 0
	s.split('\n').forEach(seg => {
		lines += Math.max(1, Math.ceil(textUnits(seg) / perLine))
	})
	return Math.max(1, lines)
}
function wrapName(name: string) {
	const s = String(name || '')
	if (s.length > 3 && !/[a-zA-Z0-9]/.test(s)) {
		const a: string[] = []
		for (let i = 0; i < s.length; i += 2) a.push(s.slice(i, i + 2))
		return a.join('\n')
	}
	return s
}
function txt(text: any, w: number, extra: Record<string, any> = {}) {
	const v = text === null || text === undefined || text === '' ? '' : String(text)
	return {
		type: 'text',
		text: v || ' ',
		css: Object.assign({ width: w + 'rpx', padding: '0 6rpx', fontSize: FONT_BODY + 'rpx', textAlign: 'center', boxSizing: 'border-box' }, extra)
	}
}

/* ---------------- 单个报价单(ctx) 的排版 ---------------- */
interface Ctx {
	cols: string[]
	rows: any[]
	title: string
	notice: string[]
	priceDate: string
	hasRemark: boolean
	remarkW: number
	gw: number
	colW: number // 单列内容宽(单列模式=PAGE_W,双列模式=COL_W)
	pageW: number // 整图宽
	ncol: number // 1 或 2
}

function makeCtx(sheet: any, ncol = 1): Ctx {
	const detail = sheet.detail || {}
	const cols = sheetColumns(detail)
	let rows = sheetRows(detail, sheet.adjustType, sheet.adjustValue, sheet.overrides, sheet.marks)
	// 只出用户选中的型号(空=全部)
	if (Array.isArray(sheet.selectedModels) && sheet.selectedModels.length) {
		const set = new Set(sheet.selectedModels)
		rows = rows.filter((r: any) => set.has(r.model))
	}
	const colW = ncol === 2 ? COL_W : PAGE_W
	const pageW = ncol === 2 ? COL_W * 2 + COL_GAP : PAGE_W
	const hasRemark = rows.some((r: any) => {
		if (Array.isArray(r.remarkValues) && r.remarkValues.some((v: any) => String(v || '').trim())) return true
		return String(r.remark || '').trim()
	})
	const remarkW = hasRemark ? REMARK_W : 0
	const gw = Math.floor((colW - MODEL_W - CAP_W - remarkW) / Math.max(1, cols.length))
	return {
		cols,
		rows,
		title: sheetTitle(sheet.name, sheet.brand),
		notice: sheetNoticeLines(detail),
		priceDate: sheetPriceDate(detail),
		hasRemark,
		remarkW,
		gw,
		colW,
		pageW,
		ncol
	}
}

function rowHeight(item: any, ctx: Ctx) {
	let lines = estLines(item.capacity || '-', CAP_W, FONT_BODY)
	const gw = Number(item.gw || ctx.gw)
	item.prices.forEach((p: any) => {
		lines = Math.max(lines, estLines(p, gw, FONT_BODY))
	})
	return Math.max(44, lines * LH + VPAD)
}
function modelHeight(model: string) {
	return Math.max(44, estLines(model, MODEL_W, FONT_MODEL) * MODEL_LH + VPAD)
}
function headerHeightFor(cols: string[], gw: number) {
	let lines = 1
	cols.forEach(name => {
		lines = Math.max(lines, estLines(wrapName(name), gw, FONT_HEAD))
	})
	return lines * HEAD_LH + VPAD
}
function headerHeightForGroup(priceCols: string[], priceW: number, remarkCols: string[], remarkW: number) {
	let lines = 1
	priceCols.forEach(name => {
		lines = Math.max(lines, estLines(wrapName(name), priceW, FONT_HEAD))
	})
	remarkCols.forEach(name => {
		lines = Math.max(lines, estLines(name, remarkW, FONT_HEAD))
	})
	return lines * HEAD_LH + VPAD
}
function bannerHeightRpx(ctx: Ctx) {
	return ctx.priceDate ? 150 : 116
}
function noticeHeightRpx(ctx: Ctx) {
	return ctx.notice.length ? 32 + ctx.notice.length * 34 : 0
}

function buildGroups(ctx: Ctx) {
	const groups: any[] = []
	const m = new Map<string, any>()
	ctx.rows.forEach((r: any) => {
		const cols = Array.isArray(r.columns) && r.columns.length ? r.columns : ctx.cols
		const signature = cols.join('\u0001')
		const key = `${r.model}\u0002${signature}`
		let g = m.get(key)
		if (!g) {
			const gw = Math.floor((ctx.colW - MODEL_W - CAP_W - ctx.remarkW) / Math.max(1, cols.length))
			g = { model: r.model, cols, signature, gw, headerH: headerHeightFor(cols, gw), capacityLabel: r.capacityLabel || '内存', items: [] }
			m.set(key, g)
			groups.push(g)
		}
		g.items.push(r)
	})
	groups.forEach(g => {
		g.capacityLabel = collectCapacityLabel(g.items)
		g.remarkCols = collectRemarkColumns(g.items)
		g.remarkW = g.remarkCols.length ? REMARK_W : 0
		const remarkTotalW = g.remarkCols.length * g.remarkW
		g.gw = Math.floor((ctx.colW - MODEL_W - CAP_W - remarkTotalW) / Math.max(1, g.cols.length))
		g.headerH = headerHeightForGroup(g.cols, g.gw, g.remarkCols, g.remarkW)
		g.layoutSignature = `${g.signature}\u0002${g.capacityLabel}\u0002${g.remarkCols.join('\u0001')}`
		g.capH = g.items.map((it: any) => rowHeight(Object.assign({}, it, { gw: g.gw }), ctx))
		balanceMergedRemarkHeights(g)
		const sum = g.capH.reduce((a: number, b: number) => a + b, 0)
		const mh = modelHeight(g.model)
		g.height = Math.max(mh, sum)
		if (g.height > sum) g.capH[g.capH.length - 1] += g.height - sum
	})
	return groups
}

function collectCapacityLabel(items: any[]) {
	for (const item of items) {
		const label = String(item?.capacityLabel || '').trim()
		if (label) return label
	}
	return '内存'
}

function collectRemarkColumns(items: any[]) {
	const cols: string[] = []
	items.forEach((item: any) => {
		const list = Array.isArray(item.remarkColumns) ? item.remarkColumns : []
		list.forEach((name: string) => {
			const n = String(name || '').trim()
			if (n && !cols.includes(n)) cols.push(n)
		})
	})
	return cols.filter(col => items.some(item => !item?.remarkHidden && normalizeRemarkText(remarkValue(item, col))))
}

function remarkValue(item: any, col: string) {
	if (item?.remarkMap && item.remarkMap[col] !== undefined) return String(item.remarkMap[col] || '')
	const idx = Array.isArray(item?.remarkColumns) ? item.remarkColumns.indexOf(col) : -1
	return idx >= 0 && Array.isArray(item?.remarkValues) ? String(item.remarkValues[idx] || '') : ''
}

function normalizeRemarkText(value: any) {
	return String(value || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim()
}

function remarkSpanAt(group: any, col: string, index: number) {
	const item = group.items[index] || {}
	const value = remarkValue(item, col)
	const normalized = normalizeRemarkText(value)
	if (!normalized) return { value: '', span: 1 }

	const metaSpan = Math.max(0, Number(item.remarkRowspan || 0))
	if (!item.remarkHidden && metaSpan > 1) {
		return { value, span: Math.min(metaSpan, group.items.length - index) }
	}
	if (item.remarkHidden) return { value: '', span: 1 }

	let span = 1
	while (index + span < group.items.length && normalizeRemarkText(remarkValue(group.items[index + span], col)) === normalized) {
		span++
	}
	return { value, span }
}

function balanceMergedRemarkHeights(group: any) {
	if (!group.remarkCols?.length) return
	group.remarkCols.forEach((col: string) => {
		for (let i = 0; i < group.items.length; i++) {
			const { value, span } = remarkSpanAt(group, col, i)
			if (!normalizeRemarkText(value)) continue
			const required = Math.max(44, estLines(value, group.remarkW, FONT_REMARK) * REMARK_LH + VPAD + 8)
			let current = 0
			for (let j = 0; j < span; j++) current += group.capH[i + j] || 0
			const extra = Math.max(0, required - current)
			if (extra > 0) {
				const each = Math.floor(extra / span)
				let rest = extra - each * span
				for (let j = 0; j < span; j++) {
					group.capH[i + j] += each + (rest > 0 ? 1 : 0)
					if (rest > 0) rest--
				}
			}
			i += span - 1
		}
	})
}

// 单列(表头 + 数据)的视图栈,宽度 = ctx.colW;单列/双列模式复用
function buildColumnStack(groups: any[], ctx: Ctx): { views: any[]; height: number } {
	const t = theme.value
	const colW = ctx.colW
	const rightW = colW - MODEL_W
	const views: any[] = []
	let totalH = 0
	let prevSignature = ''

	function pushHeader(group: any) {
		const headCells = [
			txt('机型', MODEL_W, { color: t.headText, fontWeight: 'bold', fontSize: FONT_HEAD + 'rpx' }),
			txt(group.capacityLabel || '内存', CAP_W, { color: t.headText, fontWeight: 'bold', fontSize: FONT_HEAD + 'rpx' })
		]
		group.cols.forEach((name: string) => headCells.push(txt(wrapName(name), group.gw, { color: t.headText, fontWeight: 'bold', fontSize: FONT_HEAD + 'rpx', lineHeight: HEAD_LH + 'rpx' })))
		group.remarkCols.forEach((name: string) => headCells.push(txt(name, group.remarkW, { color: t.headText, fontWeight: 'bold', fontSize: FONT_HEAD + 'rpx', lineHeight: HEAD_LH + 'rpx' })))
		views.push({
			type: 'view',
			css: { width: colW + 'rpx', height: group.headerH + 'rpx', background: t.headBg, display: 'flex', flexDirection: 'row', alignItems: 'center', boxSizing: 'border-box' },
			views: headCells
		})
		totalH += group.headerH
		prevSignature = group.layoutSignature
	}

	// 数据
	groups.forEach((group, gi) => {
		if (group.layoutSignature !== prevSignature) pushHeader(group)
		const gw = group.gw
		const remarkTotalW = group.remarkCols.length * group.remarkW
		const dataW = rightW - remarkTotalW
		const capRows = group.items.map((it: any, ri: number) => {
			const bg = (gi + ri) % 2 === 0 ? t.bodyBg : t.lineColor
			const h = group.capH[ri]
			const capCells = [txt(it.capacity || '-', CAP_W, { color: t.bodyText })]
			it.prices.forEach((p: any, ci: number) =>
				capCells.push(txt(p, gw, it.marks && it.marks[ci] ? { color: '#e6402d', fontWeight: 'bold' } : { color: t.priceText, fontWeight: 'bold' }))
			)
			return {
				type: 'view',
				css: { width: dataW + 'rpx', height: h + 'rpx', display: 'flex', flexDirection: 'row', alignItems: 'center', background: bg, boxSizing: 'border-box' },
				views: capCells
			}
		})
		const rightViews: any[] = [
			{ type: 'view', css: { width: dataW + 'rpx', display: 'flex', flexDirection: 'column' }, views: capRows }
		]
		group.remarkCols.forEach((col: string) => {
			const remarkRows: any[] = []
			for (let ri = 0; ri < group.items.length; ri++) {
				const { value, span } = remarkSpanAt(group, col, ri)
				let h = 0
				for (let si = 0; si < span; si++) h += group.capH[ri + si] || 0
				const bg = (gi + ri) % 2 === 0 ? t.bodyBg : t.lineColor
				remarkRows.push({
					type: 'view',
					css: { width: group.remarkW + 'rpx', height: h + 'rpx', display: 'flex', alignItems: 'center', justifyContent: 'center', background: bg, boxSizing: 'border-box', borderLeft: `1rpx solid ${t.lineColor}`, padding: '0 4rpx' },
					views: [txt(value || '', group.remarkW - 8, { color: t.subText, fontSize: FONT_REMARK + 'rpx', lineHeight: REMARK_LH + 'rpx' })]
				})
				ri += span - 1
			}
			rightViews.push({ type: 'view', css: { width: group.remarkW + 'rpx', display: 'flex', flexDirection: 'column' }, views: remarkRows })
		})
		views.push({
			type: 'view',
			css: { width: colW + 'rpx', height: group.height + 'rpx', display: 'flex', flexDirection: 'row', borderBottom: `2rpx solid ${hexToRgba(t.headBg, 0.58)}`, boxSizing: 'border-box' },
			views: [
				{
					type: 'view',
					css: { width: MODEL_W + 'rpx', height: group.height + 'rpx', display: 'flex', alignItems: 'center', justifyContent: 'center', borderLeft: `6rpx solid ${t.headBg}`, borderRight: `1rpx solid ${t.lineColor}`, background: t.bodyBg, boxSizing: 'border-box' },
					views: [{ type: 'text', text: group.model, css: { width: MODEL_W - 16 + 'rpx', textAlign: 'center', fontSize: FONT_MODEL + 'rpx', fontWeight: 'bold', color: t.bodyText, lineHeight: MODEL_LH + 'rpx' } }]
				},
				{ type: 'view', css: { width: rightW + 'rpx', display: 'flex', flexDirection: 'row' }, views: rightViews }
			]
		})
		totalH += group.height
	})

	return { views, height: totalH }
}

// columns: 本页的列(单列模式 1 个、双列模式最多 2 个 group 数组)
function buildPageBoard(columns: any[][], pageIndex: number, totalPages: number, ctx: Ctx) {
	const t = theme.value
	const pageW = columns.length <= 1 ? ctx.colW : ctx.pageW
	const views: any[] = []

	// 标题横幅（居中，整图宽,标头含一级分类品牌名）
	const bannerViews: any[] = [
		{ type: 'text', text: ctx.title, css: { width: pageW - 56 + 'rpx', color: t.bannerText, fontSize: '40rpx', fontWeight: 'bold', textAlign: 'center' } }
	]
	if (ctx.priceDate) {
		bannerViews.push({ type: 'text', text: ctx.priceDate, css: { width: pageW - 56 + 'rpx', color: t.bannerText, fontSize: '22rpx', textAlign: 'center', marginTop: '8rpx' } })
	}
	views.push({
		type: 'view',
		css: { width: pageW + 'rpx', background: bannerBgCss(t), padding: '32rpx 28rpx', boxSizing: 'border-box' },
		views: bannerViews
	})

	// 温馨提示
	if (ctx.notice.length) {
		views.push({
			type: 'view',
			css: { width: pageW + 'rpx', background: t.bodyBg, padding: '16rpx 28rpx', boxSizing: 'border-box' },
			views: ctx.notice.map(line => ({ type: 'text', text: line, css: { color: t.noticeText, fontSize: '22rpx', lineHeight: '34rpx' } }))
		})
	}

	// 列横向排布(单列=1 列,双列=2 列中间留间距)
	const stacks = columns.map(g => buildColumnStack(g, ctx))
	const rowViews: any[] = []
	stacks.forEach((cs, i) => {
		rowViews.push({ type: 'view', css: { width: ctx.colW + 'rpx', display: 'flex', flexDirection: 'column' }, views: cs.views })
		if (i < stacks.length - 1) rowViews.push({ type: 'view', css: { width: COL_GAP + 'rpx' } })
	})
	views.push({
		type: 'view',
		css: { width: pageW + 'rpx', display: 'flex', flexDirection: 'row', alignItems: 'flex-start', background: t.bodyBg, boxSizing: 'border-box' },
		views: rowViews
	})

	// 页脚
	const footText = totalPages > 1 ? `${ctx.title} 第 ${pageIndex + 1}/${totalPages} 页` : ctx.title
	views.push({
		type: 'view',
		css: { width: pageW + 'rpx', height: FOOTER_H + 'rpx', background: bannerBgCss(t), display: 'flex', alignItems: 'center', justifyContent: 'center', boxSizing: 'border-box' },
		views: [{ type: 'text', text: footText, css: { width: pageW - 40 + 'rpx', color: t.bannerText, fontSize: '20rpx', textAlign: 'center' } }]
	})

	// 水印：斜向错位平铺(lime-painter 不支持真旋转，用错位近似角度)
	if (watermark.value) {
		const colMax = stacks.reduce((a, s) => Math.max(a, s.height), 0)
		const totalH = bannerHeightRpx(ctx) + noticeHeightRpx(ctx) + colMax + FOOTER_H
		const color = hexToRgba(wm.color, Math.max(0, Math.min(30, Number(wm.opacity))) / 100)
		const fontSize = Math.max(18, Number(wm.size) || 34)
		const wmWordW = Math.max(160, Math.ceil(watermark.value.length * fontSize * 1.1))
		const stepX = 280
		const stepY = 150
		const slant = Math.round(Math.tan((Math.max(0, Math.min(60, Number(wm.angle))) * Math.PI) / 180) * stepY)
		let r = 0
		for (let y = 60; y < totalH; y += stepY, r++) {
			const off = ((r * slant) % stepX) - stepX
			for (let x = off; x < pageW; x += stepX) {
				views.push({
					type: 'text',
					text: watermark.value,
					css: { position: 'absolute', left: Math.round(x) + 'rpx', top: y + 'rpx', width: wmWordW + 'rpx', color, fontSize: fontSize + 'rpx' }
				})
			}
		}
	}

	return { css: { width: pageW + 'rpx', background: t.bodyBg, position: 'relative' }, views }
}

function pageBudgetRpx(ctx: Ctx) {
	let dpr = 2
	let screenW = 375
	try {
		const s = uni.getSystemInfoSync()
		dpr = s.pixelRatio || 2
		screenW = s.windowWidth || 375
	} catch (e) {
		/* ignore */
	}
	const factor = (screenW / 750) * dpr
	const budgetRpx = Math.floor(15000 / factor)
	const chromeRpx = bannerHeightRpx(ctx) + noticeHeightRpx(ctx) + FOOTER_H + 40
	return Math.max(600, budgetRpx - chromeRpx)
}

function groupBlockHeight(prevSignature: string, group: any, isFirst = false) {
	return (isFirst || prevSignature !== group.layoutSignature ? group.headerH : 0) + group.height
}

function columnHeight(groups: any[]) {
	let h = 0
	let prev = ''
	groups.forEach((group, index) => {
		h += groupBlockHeight(prev, group, index === 0)
		prev = group.layoutSignature
	})
	return h
}

function splitBalancedColumns(groups: any[], dataBudget: number) {
	if (groups.length <= 1) return [groups]
	let bestIndex = 1
	let bestScore = Number.MAX_SAFE_INTEGER
	for (let i = 1; i < groups.length; i++) {
		const leftH = columnHeight(groups.slice(0, i))
		const rightH = columnHeight(groups.slice(i))
		const overflow = Math.max(0, leftH - dataBudget) + Math.max(0, rightH - dataBudget)
		const score = overflow * 100000 + Math.abs(leftH - rightH)
		if (score < bestScore) {
			bestScore = score
			bestIndex = i
		}
	}
	return [groups.slice(0, bestIndex), groups.slice(bestIndex)]
}

// 一个报价单 -> 若干页 board（双列按页内高度均衡分布，避免右侧大面积空白）
function buildSheetBoards(ctx: Ctx) {
	const groups = buildGroups(ctx)
	const dataBudget = pageBudgetRpx(ctx)
	const ncol = ctx.ncol === 2 ? 2 : 1

	if (ncol === 1) {
		const colsArr: any[][] = []
		let cur: any[] = []
		let h = 0
		groups.forEach(group => {
			const prev = cur.length ? cur[cur.length - 1].layoutSignature : ''
			const blockH = groupBlockHeight(prev, group, cur.length === 0)
			if (cur.length && h + blockH > dataBudget) {
				colsArr.push(cur)
				cur = []
				h = 0
			}
			cur.push(group)
			h += groupBlockHeight(prev, group, cur.length === 1)
		})
		if (cur.length) colsArr.push(cur)
		if (!colsArr.length) colsArr.push([])
		return colsArr.map((col, idx) => buildPageBoard([col], idx, colsArr.length, ctx))
	}

	const pageGroups: any[][] = []
	let cur: any[] = []
	let h = 0
	groups.forEach(group => {
		const prev = cur.length ? cur[cur.length - 1].layoutSignature : ''
		const blockH = groupBlockHeight(prev, group, cur.length === 0)
		if (cur.length && h + blockH > dataBudget * 2) {
			pageGroups.push(cur)
			cur = []
			h = 0
		}
		cur.push(group)
		h += groupBlockHeight(prev, group, cur.length === 1)
	})
	if (cur.length) pageGroups.push(cur)
	if (!pageGroups.length) pageGroups.push([])

	const pages = pageGroups.map(list => splitBalancedColumns(list, dataBudget))
	return pages.map((cols, idx) => buildPageBoard(cols, idx, pages.length, ctx))
}

/* ---------------- 出图流程 ---------------- */
// lime-painter 出图会把图片写进小程序本地文件存储(上限约 10MB),旧图不清理会越攒越多,
// 生成几次后 writeFile 报 1300202「storage limit exceeded」。这里记录所有生成过的文件,
// 每次开始新一轮出图前先删掉上一轮的(保存到相册是另存,删临时文件不影响已存的)。
const GEN_FILES_KEY = 'qr_gen_files'

function purgeOldFiles() {
	try {
		const list: string[] = uni.getStorageSync(GEN_FILES_KEY) || []
		if (list.length && typeof uni.getFileSystemManager === 'function') {
			const fs = uni.getFileSystemManager()
			list.forEach(p => {
				try {
					fs.unlinkSync(p)
				} catch (e) {
					/* 文件可能已被系统回收,忽略 */
				}
			})
		}
	} catch (e) {
		/* 非小程序端无此能力,忽略 */
	}
	try {
		uni.setStorageSync(GEN_FILES_KEY, [])
	} catch (e) {}
}

function trackFile(p: string) {
	if (!p) return
	try {
		const list: string[] = uni.getStorageSync(GEN_FILES_KEY) || []
		list.push(p)
		uni.setStorageSync(GEN_FILES_KEY, list)
	} catch (e) {}
}

function onSuccess(e: any) {
	const path = typeof e === 'string' ? e : (e?.tempFilePath || e?.detail?.tempFilePath || e?.path || '')
	if (!path) return
	if (imgPaths.value.length > curRender.value) return
	imgPaths.value.push(path)
	trackFile(path)
	if (curRender.value < boards.value.length - 1) curRender.value++
}
function onFail(e: any) {
	uni.showToast({ title: '出图失败，请重试', icon: 'none' })
	// eslint-disable-next-line no-console
	console.error('[report] lime-painter fail', e)
}

function doSaveToAlbum(path: string): Promise<void> {
	return new Promise(resolve => {
		uni.saveImageToPhotosAlbum({ filePath: path, success: () => resolve(), fail: () => resolve() })
	})
}
function ensureAlbumAuth(): Promise<boolean> {
	return new Promise(resolve => {
		uni.getSetting({
			success: res => {
				if (res.authSetting?.['scope.writePhotosAlbum'] === false) {
					uni.showModal({
						title: '需要相册权限',
						content: '请允许保存图片到相册',
						confirmText: '去设置',
						success: r => {
							if (r.confirm) uni.openSetting({ success: s => resolve(!!s.authSetting?.['scope.writePhotosAlbum']) })
							else resolve(false)
						}
					})
				} else {
					resolve(true)
				}
			},
			fail: () => resolve(true)
		})
	})
}
async function saveCurrent() {
	const p = imgPaths.value[activeImg.value]
	if (!p) {
		uni.showToast({ title: '图片还没生成好', icon: 'none' })
		return
	}
	if (!(await ensureAlbumAuth())) return
	uni.showLoading({ title: '保存中…' })
	await doSaveToAlbum(p)
	uni.hideLoading()
	uni.showToast({ title: '已保存本张到相册', icon: 'success' })
}

async function saveAll() {
	if (!allDone.value) {
		uni.showToast({ title: '图片还没生成好', icon: 'none' })
		return
	}
	if (!(await ensureAlbumAuth())) return
	uni.showLoading({ title: '保存中…' })
	for (const p of imgPaths.value) {
		// eslint-disable-next-line no-await-in-loop
		await doSaveToAlbum(p)
	}
	uni.hideLoading()
	uni.showToast({ title: `已保存 ${imgPaths.value.length} 张到相册`, icon: 'success' })
}

async function init() {
	// 先清掉上一轮生成的图片文件,避免本地存储被撑爆(1300202)
	purgeOldFiles()
	const selected = sheets.value.filter((s: any) => s.selected)
	if (!selected.length) {
		uni.showToast({ title: '没有选择报价单', icon: 'none' })
		return
	}
	// 逐个确保详情已加载
	for (const sheet of selected) {
		// eslint-disable-next-line no-await-in-loop
		await ensureDetail(sheet)
	}
	const all: any[] = []
	selected.forEach((sheet: any) => {
		const ncol = decideNcol(sheet)
		const ctx = makeCtx(sheet, ncol)
		if (!ctx.rows.length) return
		buildSheetBoards(ctx).forEach(b => all.push(b))
	})
	boards.value = all
	curRender.value = 0
}

// 自动判断列数:单列若一张纸放得下就单列,放不下才用双列(最多双列)
function decideNcol(sheet: any): number {
	const ctx1 = makeCtx(sheet, 1)
	if (!ctx1.rows.length) return 1
	const groups = buildGroups(ctx1)
	const total = groups.reduce((a: number, g: any) => a + g.height, 0)
	return total <= pageBudgetRpx(ctx1) ? 1 : 2
}

onLoad(() => {
	init()
})
onUnload(() => {
	boards.value = []
	imgPaths.value = []
})
</script>

<style lang="scss" scoped>
.report-preview {
	min-height: 100vh;
	padding: 24rpx 24rpx calc(140rpx + env(safe-area-inset-bottom));
	background: #f5f6f8;
	box-sizing: border-box;
}
.preview-stage {
	position: relative;
	min-height: calc(100vh - 220rpx);
	display: flex;
	align-items: center;
	justify-content: center;
	overflow: hidden;
}
.result {
	width: 100%;
	align-self: stretch;
	display: flex;
	flex-direction: column;
}
.thumb-strip {
	white-space: nowrap;
	width: 100%;
	padding: 6rpx 0 14rpx;
}
.thumb {
	display: inline-block;
	vertical-align: top;
	position: relative;
	width: 96rpx;
	height: 140rpx;
	margin-right: 14rpx;
	border-radius: 10rpx;
	overflow: hidden;
	border: 3rpx solid transparent;
	box-sizing: border-box;
	background: #fff;
}
.thumb.active {
	border-color: #1f2937;
}
.thumb-img {
	width: 100%;
	height: 100%;
}
.thumb-idx {
	position: absolute;
	right: 4rpx;
	bottom: 4rpx;
	font-size: 18rpx;
	color: #fff;
	background: rgba(0, 0, 0, 0.45);
	padding: 0 6rpx;
	border-radius: 6rpx;
}
.main-swiper {
	width: 100%;
	height: calc(100vh - 420rpx);
}
/* 单张:无缩略图条，大图占满那部分高度 */
.result.single .main-swiper {
	height: calc(100vh - 250rpx);
}
.main-scroll {
	width: 100%;
	height: 100%;
}
.main-img {
	width: 100%;
	border-radius: 12rpx;
}
.preview-scroll {
	width: 100%;
	max-height: calc(100vh - 220rpx);
}
.page-wrap {
	margin-bottom: 24rpx;
}
.page-label {
	display: block;
	margin-bottom: 8rpx;
	font-size: 22rpx;
	color: #9aa0aa;
}
.preview-img {
	width: 100%;
	border-radius: 12rpx;
	box-shadow: 0 10rpx 30rpx rgba(0, 0, 0, 0.08);
}
.preview-canvas {
	position: absolute;
	left: 0;
	top: 0;
	width: 100%;
	z-index: 1;
}
/* 整块不透明遮罩，盖住正在变化尺寸的画布 */
.gen-overlay {
	position: absolute;
	inset: 0;
	z-index: 2;
	background: #f5f6f8;
	display: flex;
	align-items: center;
	justify-content: center;
}
.gen-card {
	width: 360rpx;
	padding: 44rpx 32rpx;
	border-radius: 20rpx;
	background: rgba(40, 40, 44, 0.92);
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 18rpx;
	box-sizing: border-box;
}
.gen-bar {
	width: 100%;
	height: 10rpx;
	border-radius: 999rpx;
	background: rgba(255, 255, 255, 0.2);
	overflow: hidden;
}
.gen-bar-fill {
	height: 100%;
	border-radius: 999rpx;
	background: #fff;
	transition: width 0.35s ease;
}
.result {
	animation: result-fade 0.35s ease both;
}
@keyframes result-fade {
	from {
		opacity: 0;
		transform: translateY(16rpx);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}
.loading-pct {
	color: #fff;
	font-size: 52rpx;
	font-weight: 700;
	font-variant-numeric: tabular-nums;
}
.loading-text {
	color: #fff;
	font-size: 26rpx;
}
.preview-footer {
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
.footer-btn.primary {
	background: #1f2937;
	color: #fff;
}
.footer-btn.ghost {
	background: #f0f1f3;
	color: #374151;
}
.footer-btn.disabled {
	opacity: 0.5;
}
</style>
