export function normalizeQuoteDate(value: unknown): string {
	if (value === null || value === undefined || value === '') return ''
	const text = String(value).trim()
	const dateText = text.match(/^(\d{4}-\d{2}-\d{2})/)?.[1]
	if (dateText) return dateText

	const raw = Number(text)
	if (!Number.isFinite(raw) || raw <= 0) return ''
	const date = new Date(text.length === 10 ? raw * 1000 : raw)
	if (Number.isNaN(date.getTime())) return ''

	const year = date.getFullYear()
	const month = `${date.getMonth() + 1}`.padStart(2, '0')
	const day = `${date.getDate()}`.padStart(2, '0')
	return `${year}-${month}-${day}`
}

export function latestQuoteDate(values: unknown[]): string {
	const dates = values.map(normalizeQuoteDate).filter(Boolean)
	return dates.length ? [...new Set(dates)].sort().at(-1) || '' : ''
}
