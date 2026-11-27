export const DEFAULT_DEMAND_MODEL_LIMIT = 5
export const DEMAND_MODEL_PREVIEW_COUNT = 3

export function isDemandModelLimit(value: unknown): boolean {
    return (
        (typeof value === 'number' || (typeof value === 'string' && value.trim() !== '')) &&
        Number.isSafeInteger(Number(value)) &&
        Number(value) >= 0
    )
}
export function normalizeDemandModelLimit(value: unknown): number {
    return isDemandModelLimit(value) ? Number(value) : DEFAULT_DEMAND_MODEL_LIMIT
}
export function uniqueDemandModels(items: unknown): Record<string, any>[] {
    const rows = new Map<string, Record<string, any>>()
    for (const item of Array.isArray(items) ? items : []) {
        if (!item || typeof item !== 'object' || Array.isArray(item)) continue
        const id = String(item.id ?? item.catalog_id ?? '').trim()
        if (id && !rows.has(id)) rows.set(id, { ...item, id })
    }
    return [...rows.values()]
}
export function demandModelLimitError(items: unknown, value: unknown): string {
    const count = uniqueDemandModels(items).length,
        limit = normalizeDemandModelLimit(value)
    return limit > 0 && count > limit
        ? `已选 ${count} 个型号，本站最多允许 ${limit} 个，请移除 ${count - limit} 个后继续`
        : ''
}
export function toggleDemandModel(items: unknown, item: Record<string, any>, value: unknown) {
    const current = uniqueDemandModels(items),
        candidate = uniqueDemandModels([item])[0]
    if (!candidate) return { items: current, error: '型号信息无效，请重新选择' }
    const exists = current.some((row) => row.id === candidate.id)
    if (exists) return { items: current.filter((row) => row.id !== candidate.id), error: '' }
    const limit = normalizeDemandModelLimit(value)
    if (limit > 0 && current.length >= limit)
        return { items: current, error: `最多选择 ${limit} 个客户需求型号，请先移除已有选择` }
    return { items: [...current, candidate], error: '' }
}
export function demandModelPreview(items: unknown, expanded = false) {
    const rows = uniqueDemandModels(items)
    return {
        total: rows.length,
        hidden: Math.max(0, rows.length - DEMAND_MODEL_PREVIEW_COUNT),
        items: expanded ? rows : rows.slice(0, DEMAND_MODEL_PREVIEW_COUNT)
    }
}
