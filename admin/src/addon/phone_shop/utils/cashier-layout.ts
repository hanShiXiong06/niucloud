export function cashierGridLayout(width: number, height: number) {
    const columns = Math.max(1, Math.min(12, Math.floor((width + 12) / 180)))
    const rows = Math.max(1, Math.min(10, Math.floor((height + 12) / 232)))
    return { columns, rows, limit: Math.min(120, columns * rows) }
}

export function cashierImages(goods: Record<string, any> | null): string[] {
    if (!goods) return []
    const source = goods.goods_image
    let images: unknown = source
    if (typeof source === 'string') {
        try { images = JSON.parse(source) } catch { images = source.split(',') }
    }
    return [...new Set([goods.goods_cover, ...(Array.isArray(images) ? images : [])]
        .filter((value): value is string => typeof value === 'string' && !!value.trim())
        .map(value => value.trim()))]
}

export function cashierSelectionIndex(cart: Array<{ sku_id: number | string }>, skuId: number | string): number {
    return cart.findIndex(item => Number(item.sku_id) === Number(skuId))
}
