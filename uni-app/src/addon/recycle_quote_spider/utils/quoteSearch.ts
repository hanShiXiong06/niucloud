export interface QuoteSearchHistory {
    keyword: string
    total: number
    searchedAt: number
}

export const SEARCH_PAGE = '/addon/recycle_quote_spider/pages/price/search'
export const PRICE_PAGE = '/addon/recycle_quote_spider/pages/price/show_price'

export function normalizeSearchKeyword(value: unknown): string {
    return Array.from(String(value ?? '').replace(/\s+/g, ' ').trim()).slice(0, 80).join('')
}

export function decodeSearchKeyword(value: unknown): string {
    const text = String(value ?? '')
    try { return normalizeSearchKeyword(decodeURIComponent(text)) } catch { return normalizeSearchKeyword(text) }
}

export function searchHistoryKey(siteId: number | string, memberId: number | string, sourceId: number): string {
    return `recycle_quote_search:v1:${siteId || 0}:${memberId || 0}:${sourceId || 0}`
}

export function readSearchHistory(value: unknown): QuoteSearchHistory[] {
    if (!Array.isArray(value)) return []
    const seen = new Set<string>()
    return value.filter(item => item && typeof item.keyword === 'string' && Number.isFinite(item.total) && item.total >= 0)
        .map(item => ({ keyword: normalizeSearchKeyword(item.keyword), total: Math.floor(item.total), searchedAt: Number(item.searchedAt) || 0 }))
        .filter(item => {
            const key = item.keyword.toLowerCase().replace(/\s/g, '')
            if (!key || seen.has(key)) return false
            seen.add(key)
            return true
        }).slice(0, 12)
}

export function recordSearchHistory(history: QuoteSearchHistory[], keyword: string, total: number, now = Date.now()): QuoteSearchHistory[] {
    const normalized = normalizeSearchKeyword(keyword)
    if (!normalized) return readSearchHistory(history)
    return readSearchHistory([{ keyword: normalized, total, searchedAt: now }, ...history])
}
