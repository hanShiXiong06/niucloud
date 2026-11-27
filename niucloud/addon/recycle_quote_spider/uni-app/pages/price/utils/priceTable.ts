import type { QuotationPriceData } from '@/addon/recycle_quote_spider/api/quotation'

export interface EnhancedPriceRow extends QuotationPriceData {
	showAdjustment?: boolean
	adjustmentRowspan?: number
	displayAdjustment?: string
	displayAdjustmentParts?: string[]
	displayAdjustmentCells?: Record<string, AdjustmentDisplayCell>
	displayRowHeight?: number
}

export interface GroupedTable {
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

export interface PriceColumn {
	key: string
	name: string
	width: number
}

export interface AdjustmentColumn {
	key: string
	name: string
	sort: number
	width?: number
}

export interface AdjustmentDisplayCell {
	show: boolean
	rowspan: number
	parts: string[]
	text: string
	height: number
}

export interface ModelGroup {
	modelName: string
	isHot: boolean
	rows: EnhancedPriceRow[]
}

export const ADJUSTMENT_ROW_HEIGHT_RPX = 68

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

export function isRecord(value: unknown): value is Record<string, any> {
	return typeof value === 'object' && value !== null
}

export function normalizePrices(value: unknown): Record<string, unknown> {
	if (Array.isArray(value)) {
		const result: Record<string, unknown> = {}
		for (const item of value) {
			if (!isRecord(item)) continue
			const name = String(item.name || item.field_name || item.price_name || '').trim()
			if (name) result[name] = item
		}
		return result
	}
	return isRecord(value) ? value : {}
}

export function normalizeModelName(value: unknown): string {
	return String(value || '').replace(/\s+/g, ' ').trim()
}

export function resolveRowSeriesName(row: Partial<QuotationPriceData>): string {
	const seriesName = String(row.series_name || '').trim()
	if (seriesName) return seriesName
	const groupKey = Number(row.model_group_key || 0)
	return groupKey > 0 ? `系列 ${groupKey}` : '其他系列'
}

export function resolveRowSeriesKey(row: Partial<QuotationPriceData>): string {
	const seriesName = String(row.series_name || '').trim()
	if (seriesName) return `series:${seriesName}`
	const groupKey = Number(row.model_group_key || 0)
	return groupKey > 0 ? `group:${groupKey}` : 'series:other'
}

export function getModelGroups(rows: EnhancedPriceRow[]): ModelGroup[] {
	const groups: ModelGroup[] = []
	let currentModelName = ''
	let currentGroup: ModelGroup | null = null

	rows.forEach(row => {
		if (row.goods_name !== currentModelName) {
			if (currentGroup) groups.push(currentGroup)
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

	if (currentGroup) groups.push(currentGroup)
	return groups
}

function parsePriceValue(value: unknown): number | null {
	if (value === null || value === undefined || value === '') return null
	if (typeof value === 'number' || typeof value === 'string') {
		const num = Number(value)
		return Number.isFinite(num) ? num : null
	}
	if (isRecord(value)) {
		for (const candidate of [value.final, value.price, value.value, value.current, value.original]) {
			if (candidate === null || candidate === undefined || candidate === '') continue
			const num = Number(candidate)
			if (Number.isFinite(num)) return num
		}
	}
	return null
}

export function hasPriceValue(value: unknown): boolean {
	return parsePriceValue(value) !== null
}

export function formatPrice(value: unknown): string {
	const num = parsePriceValue(value)
	return num === null ? '--' : `${Math.round(num)}`
}

export function spiderPriceTrend(row: Record<string, any>, columnName: string): '' | 'up' | 'down' {
	const current = parsePriceValue(row?.prices?.[columnName])
	const previous = parsePriceValue(row?.prevPrices?.[columnName])
	if (current === null || previous === null || !previous || current === previous) return ''
	return current > previous ? 'up' : 'down'
}

export function getModelBrand(modelName: string): string {
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

export function collectAdjustmentColumns(rows: EnhancedPriceRow[]): AdjustmentColumn[] {
	const columns = new Map<string, AdjustmentColumn>()

	for (const row of rows) {
		for (const item of collectAdjustmentItemRecords(row)) {
			const column = getAdjustmentColumnFromItem(item)
			if (column && !columns.has(column.key)) columns.set(column.key, column)
		}

		for (const part of splitAdjustmentSummaryText(row.adjustment_summary || row.value_info || '')) {
			const parsed = parseLabeledAdjustmentText(part)
			if (!parsed || columns.has(parsed.key) || Array.from(columns.values()).some(column => column.name === parsed.name)) continue
			columns.set(parsed.key, parsed)
		}
	}

	if (columns.size === 0 && rows.some(row => normalizeAdjustmentParts(row).length > 0)) {
		columns.set('adjustment', { key: 'adjustment', name: '加/扣钱项', sort: 999 })
	}

	return Array.from(columns.values()).sort((a, b) => (a.sort - b.sort) || a.name.localeCompare(b.name))
}

export function calculateTableColumnWidths(priceNames: string[], adjustmentColumns: AdjustmentColumn[], rows: EnhancedPriceRow[]): {
	priceColumns: PriceColumn[]
	adjustmentColumns: AdjustmentColumn[]
} {
	const priceColumns: PriceColumn[] = priceNames.map(name => ({ key: `price:${name}`, name, width: 0 }))
	const columns = [
		...priceColumns.map(column => ({
			key: column.key,
			score: getPriceColumnScore(column.name, rows),
			min: getPriceColumnMinWidth(priceColumns.length),
			max: getPriceColumnMaxWidth(priceColumns.length)
		})),
		...adjustmentColumns.map(column => ({
			key: column.key,
			score: getAdjustmentColumnScore(column, rows) * 1.35,
			min: getAdjustmentColumnMinWidth(adjustmentColumns.length),
			max: getAdjustmentColumnMaxWidth(adjustmentColumns.length)
		}))
	]

	if (columns.length === 0) return { priceColumns, adjustmentColumns }
	const widthMap = allocateColumnWidths(columns, Math.max(0, TABLE_TOTAL_WIDTH_RPX - CAPACITY_COLUMN_WIDTH_RPX))

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
		) break
	}

	return result
}

function getPriceColumnScore(name: string, rows: EnhancedPriceRow[]): number {
	let maxPriceWeight = 0
	for (const row of rows) {
		const price = formatPrice(row.prices?.[name])
		if (price !== '--') maxPriceWeight = Math.max(maxPriceWeight, getTextDisplayWeight(price))
	}
	return Math.max(3, maxPriceWeight * 1.4 + Math.min(getTextDisplayWeight(name) * 0.16, 4))
}

function getAdjustmentColumnScore(column: AdjustmentColumn, rows: EnhancedPriceRow[]): number {
	let score = getTextDisplayWeight(column.name) * 1.4
	let longestPartWeight = 0
	let totalPartWeight = 0
	let partCount = 0

	for (const row of rows) {
		const parts = buildRowAdjustmentCells(row, [column])[column.key]?.parts || []
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
	for (const char of String(text || '')) weight += /[\x00-\x7F]/.test(char) ? 0.55 : 1
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

export function getDisplayRows(rows: EnhancedPriceRow[], adjustmentColumns: AdjustmentColumn[]): EnhancedPriceRow[] {
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
		const textEntries = displayRows.map(row => {
			const text = row.displayAdjustmentCells?.[column.key]?.text || ''
			return { text, normalized: normalizeAdjustmentMergeText(text) }
		})
		const uniqueNonEmptyTexts = Array.from(new Set(textEntries.map(item => item.normalized).filter(Boolean)))

		if (uniqueNonEmptyTexts.length === 1) {
			const sourceIndex = textEntries.findIndex(item => item.normalized === uniqueNonEmptyTexts[0])
			const sourceText = sourceIndex >= 0 ? textEntries[sourceIndex].text : uniqueNonEmptyTexts[0]
			const sourceCell = sourceIndex >= 0 ? displayRows[sourceIndex].displayAdjustmentCells?.[column.key] : null
			const firstCell = displayRows[0].displayAdjustmentCells?.[column.key]
			if (firstCell) {
				firstCell.show = true
				firstCell.rowspan = displayRows.length
				firstCell.parts = sourceCell?.parts || splitAdjustmentText(sourceText)
				firstCell.text = sourceText
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
		let currentText = textEntries[0]?.normalized || ''
		for (let index = 1; index <= displayRows.length; index++) {
			const text = textEntries[index]?.normalized || ''
			if (index === displayRows.length || text !== currentText) {
				const span = index - startIndex
				const startCell = displayRows[startIndex].displayAdjustmentCells?.[column.key]
				if (startCell) {
					startCell.show = true
					startCell.rowspan = span
				}
				for (let inner = startIndex + 1; inner < index; inner++) {
					const cell = displayRows[inner].displayAdjustmentCells?.[column.key]
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

function normalizeAdjustmentMergeText(value: unknown): string {
	return String(value || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim()
}

function buildRowAdjustmentCells(row: EnhancedPriceRow, adjustmentColumns: AdjustmentColumn[]): Record<string, AdjustmentDisplayCell> {
	const map: Record<string, AdjustmentDisplayCell> = {}
	for (const column of adjustmentColumns) {
		map[column.key] = { show: true, rowspan: 1, parts: [], text: '', height: ADJUSTMENT_ROW_HEIGHT_RPX }
	}

	for (const item of collectAdjustmentItemRecords(row)) {
		const column = getAdjustmentColumnFromItem(item)
		if (column && map[column.key]) map[column.key].parts.push(...getAdjustmentItemContentParts(item))
	}

	for (const part of splitAdjustmentSummaryText(row.adjustment_summary || row.value_info || '')) {
		const parsed = parseLabeledAdjustmentText(part)
		if (!parsed) continue
		const key = map[parsed.key] ? parsed.key : findAdjustmentColumnKeyByName(adjustmentColumns, parsed.name)
		if (key && map[key]) map[key].parts.push(...splitAdjustmentText(parsed.content))
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
	cell.height = Math.max(rowspan * ADJUSTMENT_ROW_HEIGHT_RPX, contentHeight)
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
			const averageHeight = Math.ceil((cell.height || ADJUSTMENT_ROW_HEIGHT_RPX * cell.rowspan) / Math.max(1, cell.rowspan))
			for (let index = rowIndex; index < Math.min(rows.length, rowIndex + cell.rowspan); index++) {
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
	const contentLines = parts.reduce((total, part) => total + estimateTextLineCount(part, columnWidth), 0)
	const gapHeight = Math.max(0, parts.length - 1) * ADJUSTMENT_TEXT_GAP_RPX
	return Math.max(ADJUSTMENT_ROW_HEIGHT_RPX, contentLines * ADJUSTMENT_TEXT_LINE_HEIGHT_RPX + gapHeight + ADJUSTMENT_CELL_PADDING_RPX)
}

function estimateTextLineCount(text: string, columnWidth: number): number {
	const normalizedText = String(text || '').trim()
	if (!normalizedText) return 1
	const charsPerLine = Math.max(4, Math.floor(Math.max(64, columnWidth - 12) / 14))
	let weight = 0
	for (const char of normalizedText) weight += /[\x00-\x7F]/.test(char) ? 0.55 : 1
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
			for (const item of source) if (isRecord(item)) records.push(item)
		} else if (isRecord(source)) {
			if (hasAdjustmentContent(source)) records.push(source)
			else for (const item of Object.values(source)) if (isRecord(item)) records.push(item)
		}
	}
	return records
}

function getAdjustmentColumnFromItem(item: Record<string, unknown>): AdjustmentColumn | null {
	const name = String(item.field_name || item.name || item.title || item.label || '').trim()
	if (!name) return null
	const key = item.field_id !== undefined && item.field_id !== null && item.field_id !== ''
		? `field:${String(item.field_id)}`
		: `label:${name}`
	return { key, name, sort: Number(item.field_sort || item.sort || 999) }
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
	return name && content ? { key: `label:${name}`, name, sort: 999, content } : null
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
	return uniqueAdjustmentParts(parts.flatMap(splitAdjustmentText))
}

function uniqueAdjustmentParts(parts: string[]): string[] {
	const result: string[] = []
	const seen = new Set<string>()
	for (const part of parts) {
		for (const text of splitAdjustmentText(part)) {
			const key = text.replace(/\s+/g, '')
			if (seen.has(key)) continue
			seen.add(key)
			result.push(text)
		}
	}
	return result
}

function getAdjustmentColumnWidth(column: AdjustmentColumn): number {
	return column.width || 128
}

export function getAdjustmentColumnStyle(column: AdjustmentColumn): Record<string, string> {
	const width = getAdjustmentColumnWidth(column)
	return {
		flex: `0 0 ${width}rpx`,
		width: `${width}rpx`,
		minWidth: `${width}rpx`,
		maxWidth: `${width}rpx`
	}
}

export function getPriceColumnStyle(column: PriceColumn): Record<string, string> {
	const width = column.width || 64
	return {
		flex: `0 0 ${width}rpx`,
		width: `${width}rpx`,
		minWidth: `${width}rpx`,
		maxWidth: `${width}rpx`
	}
}

export function getAdjustmentCell(row: EnhancedPriceRow, key: string): AdjustmentDisplayCell {
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
