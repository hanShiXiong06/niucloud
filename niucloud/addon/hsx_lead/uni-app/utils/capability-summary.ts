/** Compact display facts only. Does not change selections, save or infer stock. */
export interface CapabilitySummaryGroup {
    name: string
    count: number
    kind: 'category' | 'brand' | 'unknown'
}
export interface CapabilitySummary {
    count: number
    summaryText: string
    groups: CapabilitySummaryGroup[]
}

const text = (value: unknown) => (typeof value === 'string' ? value.trim() : '')
const idText = (value: unknown) =>
    typeof value === 'string' ? value.trim() : typeof value === 'number' && Number.isFinite(value) ? String(value) : ''
const categoryText = (row: Record<string, unknown>) => {
    const value = text(row.category) || text(row.category_name)
    return ['其他品类', '其他分类', '未分类'].includes(value) ? '' : value
}
const brandText = (row: Record<string, unknown>) => {
    const value = text(row.brand) || text(row.brand_name)
    return ['其他品牌', '未标注品牌'].includes(value) ? '' : value
}

export function summarizeCapabilities(models: unknown): CapabilitySummary {
    const unique = new Map<string, { category: string; brand: string }>()
    for (const row of Array.isArray(models) ? models : []) {
        if (!row || typeof row !== 'object' || Array.isArray(row)) continue
        const id = idText(row.id)
        if (!id) continue
        const existing = unique.get(id)
        if (existing) {
            // Duplicate snapshots can supply missing labels, never a second count.
            existing.category ||= categoryText(row)
            existing.brand ||= brandText(row)
        } else unique.set(id, { category: categoryText(row), brand: brandText(row) })
    }
    const count = unique.size
    if (!count) return { count: 0, summaryText: '按品类选择', groups: [] }
    // Old rows often have brand but no category. Do not pretend they are phones.
    const useCategories = [...unique.values()].every((row) => !!row.category)
    const map = new Map<string, CapabilitySummaryGroup>()
    for (const row of unique.values()) {
        const name = useCategories ? row.category : row.brand || '未标注品牌'
        const kind = useCategories ? 'category' : row.brand ? 'brand' : 'unknown'
        const key = kind + ':' + name
        const existing = map.get(key)
        if (existing) existing.count++
        else map.set(key, { name, count: 1, kind })
    }
    const groups = [...map.values()].sort((left, right) => right.count - left.count)
    const preview = groups
        .slice(0, 3)
        .map((group) => `${group.name} ${group.count} 款`)
        .join(' · ')
    const suffix = groups.length > 3 ? (useCategories ? ` 等 ${groups.length} 类` : ` 等 ${groups.length} 组`) : ''
    return { count, summaryText: preview + suffix, groups }
}
