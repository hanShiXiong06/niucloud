export interface GoodsSpecFilterItem {
    value: string
    label: string
}

export interface GoodsSpecFilterGroup {
    key: string
    title: string
    items: GoodsSpecFilterItem[]
}

/** 规格筛选只消费后台维护的分组；不从商品值或旧 memories 列表猜分类。 */
export function goodsSpecFilterGroups(options: any): GoodsSpecFilterGroup[] {
    if (!Array.isArray(options?.memory_groups)) return []
    return options.memory_groups.reduce((groups: GoodsSpecFilterGroup[], group: any, index: number) => {
        if (!group || typeof group !== 'object' || Array.isArray(group)) return groups
        const title = String(group.group_name || group.label || '').trim()
        if (!title || !Array.isArray(group.items)) return groups
        const seen = new Set<string>()
        const items: GoodsSpecFilterItem[] = []
        for (const item of group.items) {
            if (!item || typeof item !== 'object' || Array.isArray(item)) continue
            if (!['string', 'number'].includes(typeof item.value)) continue
            if (typeof item.value === 'number' && !Number.isFinite(item.value)) continue
            const value = String(item.value).trim()
            if (!value || seen.has(value)) continue
            seen.add(value)
            const label = ['string', 'number'].includes(typeof item.label) ? String(item.label).trim() : ''
            items.push({ value, label: label || value })
        }
        if (items.length) groups.push({ key: `spec_${group.group_id ?? index}`, title, items })
        return groups
    }, [])
}
