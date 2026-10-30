type Row = Record<string, any>
export const MAX_CATALOG_REQUIREMENTS = 20
export interface CatalogNode { value: string, label: string, leaf?: boolean, disabled?: boolean, search_text?: string, children?: CatalogNode[] }
export const normalizeCatalogSearch = (value: any) => String(value || '').normalize('NFKC').toLocaleLowerCase().replace(/\s+/g, '')
export function buildCatalogTree(items: Row[], snapshots: Row[] = [], selectedIds: string[] = []): CatalogNode[] {
    const selected = new Set(selectedIds.map(String)), catalog = new Map<string, Row>()
    for (const item of items) if (item.id != null && String(item.id)) catalog.set(String(item.id), item)
    for (const item of snapshots) if (item.catalog_id != null && !catalog.has(String(item.catalog_id))) catalog.set(String(item.catalog_id), { ...item, id: item.catalog_id })
    const categories = new Map<string, CatalogNode>(), brands = new Map<string, CatalogNode>()
    for (const [id, item] of catalog) {
        const category = String(item.category_name || '').trim() || '其他设备', brand = String(item.brand || '').trim() || '其他品牌'
        if (!categories.has(category)) categories.set(category, { value: `category:${category}`, label: category, children: [] })
        const brandKey = JSON.stringify([category, brand])
        if (!brands.has(brandKey)) { const node = { value: `brand:${brandKey}`, label: brand, children: [] }; brands.set(brandKey, node); categories.get(category)!.children!.push(node) }
        const aliases = Array.isArray(item.aliases) ? item.aliases : []
        brands.get(brandKey)!.children!.push({ value: id, label: String(item.title || ''), leaf: true, disabled: selected.size >= MAX_CATALOG_REQUIREMENTS && !selected.has(id), search_text: normalizeCatalogSearch([category, brand, item.title, ...aliases].join(' ')) })
    }
    return [...categories.values()]
}
export function catalogNodeMatches(node: { isLeaf?: boolean, data?: CatalogNode }, keyword: string): boolean {
    return !!(node.isLeaf || node.data?.leaf) && String(node.data?.search_text || '').includes(normalizeCatalogSearch(keyword))
}
export function selectCatalogRequirements(ids: any[], current: Row[], catalog: Row[]): Row[] {
    return [...new Set((ids || []).map(String))].slice(0, MAX_CATALOG_REQUIREMENTS).map(id => {
        const existing = current.find(item => String(item.catalog_id) === id)
        if (existing) return existing
        const item = catalog.find(item => String(item.id) === id)
        return item ? { catalog_id: item.id, title: item.title, brand: item.brand || '', quantity: 1, budget_min: undefined, budget_max: undefined, remark: '' } : null
    }).filter(Boolean) as Row[]
}
