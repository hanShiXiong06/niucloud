/** 二手机业务口径：最新上架按创建时间；最近调价只按实际销售价格变更时间。 */
export const RECENT_GOODS_SECONDS = 24 * 60 * 60
export type GoodsRecentTag = 'new' | 'updated' | ''

export function isRecentGoodsTime(value: unknown, now: number): boolean {
    const timestamp = Number(value)
    return Number.isFinite(timestamp) && timestamp > 0 && timestamp <= now
        && now - timestamp < RECENT_GOODS_SECONDS
}

export function goodsRecentTag(goods: Record<string, any>, now: number): GoodsRecentTag {
    if (isRecentGoodsTime(goods.created_at, now)) return 'new'
    return isRecentGoodsTime(goods.price_changed_at, now) ? 'updated' : ''
}
