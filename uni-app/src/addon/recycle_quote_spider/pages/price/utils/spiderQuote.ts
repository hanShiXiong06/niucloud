import type { QuoteSpiderItem, QuotationPriceData } from '@/addon/recycle_quote_spider/api/quotation'
import { isRecord } from './priceTable'

export function resolveSpiderImage(item: QuoteSpiderItem): string {
	return item.bimage || item.image || item.timage || item.icon || ''
}

export function normalizeSpiderRows(item: QuoteSpiderItem): QuotationPriceData[] {
	const title = item.title || item.name || '报价单'
	const rows = Array.isArray(item.rows) ? item.rows : []

	return rows.map(row => {
		const prices = normalizeSpiderPrices(row.final_prices || row.manual_prices || row.source_prices || {}, row.columns || [])
		const prevPrices = normalizeSpiderPrices(row.prev_final_prices || {}, row.columns || [])
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
			prevPrices,
			add_value_info: 0,
			value_info: row.remark || '',
			adjustment_items: row.remark ? [{ field_name: '备注', content_text: row.remark }] : [],
			adjustment_summary: row.remark || '',
			price_date: row.price_date || item.price_date || '',
			create_at: row.create_at || '',
			update_at: row.update_at || ''
		}
	})
}

function resolveSpiderCapacity(row: Record<string, any>, seriesName = ''): string {
	const raw = isRecord(row.raw_data) ? row.raw_data : {}
	const candidates = [
		raw['内存'], raw['容量'], raw['规格'], raw['存储'], row.capacity_name, row.capacity,
		raw.capacity_name, raw.capacity, raw.memory, raw.storage, raw.rom
	]
	const value = candidates.find(item => isValidSpiderCapacity(item, seriesName))
	return value === undefined ? '--' : normalizeText(value)
}

function isValidSpiderCapacity(value: unknown, seriesName = ''): boolean {
	const text = normalizeText(value)
	return !!text && (!seriesName || text !== seriesName) && !/分组|系列/.test(text)
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
			if (columnName) result[columnName] = { price: value[key], final: value[key] }
		}
	}

	if (Array.isArray(value)) {
		value.forEach((item, index) => {
			if (isRecord(item)) {
				const key = String(item.name || item.field_name || item.label || columnNames[index] || `价格${index + 1}`).trim()
				if (key) result[key] = item
			} else {
				const key = String(columnNames[index] || `价格${index + 1}`)
				result[key] = { price: item, final: item }
			}
		})
	}

	if (Object.keys(result).length === 0) {
		for (const column of columnNames) if (column) result[column] = ''
	}
	return result
}

function resolveSpiderPriceColumnName(key: string, columns: string[]): string {
	const name = String(key || '').trim()
	if (!name) return ''
	return /^\d+$/.test(name) ? columns[Number(name)] || '' : name
}
