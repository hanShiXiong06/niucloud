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
export interface CatalogIndex {
    rows: CatalogModel[]
    categories: CatalogGroup[]
    byCategory: Map<string, CatalogModel[]>
    byBrand: Map<string, Map<string, CatalogModel[]>>
    searchText: Map<string, string>
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

/** Build once for normalized leaf rows. Selection changes never rebuild this index. */
export function indexCatalog(rows: CatalogModel[]): CatalogIndex {
    const categories: CatalogGroup[] = [], byCategory = new Map<string, CatalogModel[]>(), byBrand = new Map<string, Map<string, CatalogModel[]>>(), searchText = new Map<string, string>()
    const groups = new Map<string, CatalogGroup>(), brandGroups = new Map<string, Map<string, { key: string; name: string; count: number }>>()
    for (const row of rows) {
        let group = groups.get(row.category)
        if (!group) { group = { key: row.category, name: row.category, count: 0, brands: [] }; groups.set(row.category, group); categories.push(group); byCategory.set(row.category, []); byBrand.set(row.category, new Map()); brandGroups.set(row.category, new Map()) }
        group.count++; byCategory.get(row.category)!.push(row)
        const brands = byBrand.get(row.category)!, definitions = brandGroups.get(row.category)!
        let brand = definitions.get(row.brand)
        if (!brand) { brand = { key: row.brand, name: row.brand, count: 0 }; definitions.set(row.brand, brand); group.brands.push(brand); brands.set(row.brand, []) }
        brand.count++; brands.get(row.brand)!.push(row)
        const aliases = Array.isArray(row.aliases) ? row.aliases.map(text).join(' ') : ''
        searchText.set(row.id, `${row.category} ${row.brand} ${row.title} ${aliases}`.toLocaleLowerCase().replace(/\s+/g, ''))
    }
    return { rows, categories, byCategory, byBrand, searchText }
}

/** Stable source order, with independent brand groups inside each category. */
export function catalogCategories(rows: unknown): CatalogGroup[] {
    return indexCatalog(normalizeCatalog(rows)).categories
}

/** Indexed browsing/search: no leaf normalization or grouping on each checkbox change. */
export function indexedModels(index: CatalogIndex, filter: CatalogFilter = {}): CatalogModel[] {
    const category = text(filter.category), brand = text(filter.brand)
    const terms = text(filter.keyword).toLocaleLowerCase().split(/\s+/).filter(Boolean)
    let rows = category ? brand ? index.byBrand.get(category)?.get(brand) || [] : index.byCategory.get(category) || [] : index.rows
    if (!category && brand) rows = rows.filter(row => row.brand === brand)
    return terms.length ? rows.filter(row => terms.every(term => (index.searchText.get(row.id) || '').includes(term))) : rows
}

/** Empty category/brand means all; a search never silently drops the active range. */
export function visibleModels(rows: unknown, filter: CatalogFilter = {}): CatalogModel[] { return indexedModels(indexCatalog(normalizeCatalog(rows)), filter) }

function countState(count: number, total: number) { return { checked: total > 0 && count === total, indeterminate: count > 0 && count < total, count, total } }

/** One linear pass computes every parent count, including identically named brands. */
export function indexedSelectionStates(index: CatalogIndex, ids: ReadonlySet<string>) {
    const categoryCounts = new Map<string, number>(), brandCounts = new Map<string, Map<string, number>>()
    for (const row of index.rows) {
        if (!ids.has(row.id)) continue
        categoryCounts.set(row.category, (categoryCounts.get(row.category) || 0) + 1)
        let brands = brandCounts.get(row.category)
        if (!brands) { brands = new Map(); brandCounts.set(row.category, brands) }
        brands.set(row.brand, (brands.get(row.brand) || 0) + 1)
    }
    const categories = new Map<string, ReturnType<typeof countState>>(), brands = new Map<string, Map<string, ReturnType<typeof countState>>>()
    for (const group of index.categories) {
        categories.set(group.key, countState(categoryCounts.get(group.key) || 0, group.count))
        brands.set(group.key, new Map(group.brands.map(brand => [brand.key, countState(brandCounts.get(group.key)?.get(brand.key) || 0, brand.count)])))
    }
    return { categories, brands }
}

/** Fast range count for already-normalized, unique leaves. */
export function indexedSelectionState(ids: ReadonlySet<string>, range: CatalogModel[]) {
    let count = 0
    for (const row of range) if (ids.has(row.id)) count++
    return countState(count, range.length)
}

/** An empty range is never marked selected. Duplicate IDs count only once. */
export function selectionState(selection: unknown, range: unknown) {
    const chosen = new Set(normalize(selection, true).map(row => row.id)), rows = normalizeCatalog(range)
    return indexedSelectionState(chosen, rows)
}

/**
 * Only the supplied range changes. Off-screen/unknown saved models stay selected.
 * Adding over the limit rejects the entire batch; removal remains possible for
 * an oversized pre-existing selection. The caller decides when to commit items.
 */
export function applySelection(selection: unknown, range: unknown, mode: CatalogSelectionMode, max = 100): { items: CatalogModel[]; error?: string } {
    const previous = normalize(selection, true), rows = normalizeCatalog(range)
    return applyIndexedSelection(previous, rows, mode, max)
}

/** Picker-only fast path: inputs are already normalized and IDs are unique. */
export function applyIndexedSelection(previous: CatalogModel[], rows: CatalogModel[], mode: CatalogSelectionMode, max = 100): { items: CatalogModel[]; error?: string } {
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
    return { items: items.length === previous.length && items.every((row, index) => row.id === previous[index].id) ? previous : items }
}
