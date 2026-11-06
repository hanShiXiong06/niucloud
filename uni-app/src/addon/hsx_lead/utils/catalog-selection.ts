/** Pure, range-scoped selection rules. No storage, requests or implicit saving. */
export interface CatalogModel {
    id: string
    title: string
    brand: string
    category: string
    [key: string]: unknown
}

export interface CatalogFilter { category?: string; brand?: string; keyword?: string }
export type CatalogSelectionMode = 'toggle_all' | 'select_all' | 'invert' | 'clear'
export interface CatalogGroup {
    key: string
    name: string
    count: number
    brands: Array<{ key: string; name: string; count: number }>
}

const text = (value: unknown) => typeof value === 'string' ? value.trim() : ''
function rowId(value: unknown): string {
    if (typeof value === 'string') return value.trim()
    return typeof value === 'number' && Number.isFinite(value) ? String(value) : ''
}

function normalize(rows: unknown, preserveUnknown: boolean): CatalogModel[] {
    if (!Array.isArray(rows)) return []
    const seen = new Set<string>(), result: CatalogModel[] = []
    for (const raw of rows) {
        if (!raw || typeof raw !== 'object' || Array.isArray(raw)) continue
        const id = rowId(raw.id), title = text(raw.title) || text(raw.model_name) || text(raw.name)
        if (!id || seen.has(id) || (!title && !preserveUnknown)) continue
        seen.add(id)
        const path = Array.isArray(raw.path) ? raw.path.map(text).filter(Boolean) : []
        result.push({ ...raw, id, title: title || '未命名选项', path, brand: path[1] || text(raw.brand) || text(raw.brand_name) || '全部', category: path[0] || text(raw.category) || text(raw.category_name) || '全部分类' })
    }
    return result
}

/** Flat source data remains leaf data; hierarchy is derived without rewriting IDs. */
export function normalizeCatalog(rows: unknown): CatalogModel[] { return normalize(rows, false) }

/** Stable source order, with independent brand groups inside each category. */
export function catalogCategories(rows: unknown): CatalogGroup[] {
    const groups = new Map<string, CatalogGroup>()
    for (const row of normalizeCatalog(rows)) {
        let category = groups.get(row.category)
        if (!category) { category = { key: row.category, name: row.category, count: 0, brands: [] }; groups.set(row.category, category) }
        category.count++
        let brand = category.brands.find(item => item.key === row.brand)
        if (!brand) { brand = { key: row.brand, name: row.brand, count: 0 }; category.brands.push(brand) }
        brand.count++
    }
    return [...groups.values()]
}

/** Empty category/brand means all; a search never silently drops the active range. */
export function visibleModels(rows: unknown, filter: CatalogFilter = {}): CatalogModel[] {
    const category = text(filter.category), brand = text(filter.brand)
    const terms = text(filter.keyword).toLocaleLowerCase().split(/\s+/).filter(Boolean)
    return normalizeCatalog(rows).filter(row => {
        if (category && row.category !== category || brand && row.brand !== brand) return false
        const aliases = Array.isArray(row.aliases) ? row.aliases.map(text).join(' ') : ''
        const haystack = `${row.category} ${row.brand} ${row.title} ${aliases}`.toLocaleLowerCase().replace(/\s+/g, '')
        return terms.every(term => haystack.includes(term))
    })
}

/** An empty range is never marked selected. Duplicate IDs count only once. */
export function selectionState(selection: unknown, range: unknown) {
    const chosen = new Set(normalize(selection, true).map(row => row.id)), rows = normalizeCatalog(range)
    const total = rows.length, count = rows.filter(row => chosen.has(row.id)).length
    return { checked: total > 0 && count === total, indeterminate: count > 0 && count < total, count, total }
}

/**
 * Only the supplied range changes. Off-screen/unknown saved models stay selected.
 * Adding over the limit rejects the entire batch; removal remains possible for
 * an oversized pre-existing selection. The caller decides when to commit items.
 */
export function applySelection(selection: unknown, range: unknown, mode: CatalogSelectionMode, max = 100): { items: CatalogModel[]; error?: string } {
    const previous = normalize(selection, true), rows = normalizeCatalog(range)
    if (!['toggle_all', 'select_all', 'invert', 'clear'].includes(mode)) return { items: previous, error: '不支持的选择操作，原选择未更改' }
    if (!rows.length) return { items: previous }
    const chosenIds = new Set(previous.map(row => row.id)), rangeIds = new Set(rows.map(row => row.id))
    let items: CatalogModel[]
    if (mode === 'clear' || mode === 'toggle_all' && rows.every(row => chosenIds.has(row.id))) {
        items = previous.filter(row => !rangeIds.has(row.id))
    } else if (mode === 'invert') {
        items = [...previous.filter(row => !rangeIds.has(row.id)), ...rows.filter(row => !chosenIds.has(row.id))]
    } else {
        items = [...previous, ...rows.filter(row => !chosenIds.has(row.id))]
    }
    const limit = Number.isFinite(max) ? Math.max(0, Math.floor(max)) : 100
    if (limit > 0 && items.length > limit && items.some(row => !chosenIds.has(row.id))) return { items: previous, error: `最多选择 ${limit} 项，本次未更改选择` }
    return { items }
}
