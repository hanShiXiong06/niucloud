/** 单规格数量输入、加减共用校验；0 表示移出购物车。 */
export function validateCartQuantity(value: unknown, goods: any): { quantity: number; error: string } {
    const text = String(value ?? '').trim()
    if (!/^\d+$/.test(text) || !Number.isSafeInteger(Number(text))) {
        return { quantity: 0, error: '请输入整数数量，填 0 可移出购物车' }
    }
    const quantity = Number(text)
    if (quantity === 0) return { quantity, error: '' }
    const stock = Math.max(0, Math.floor(Number(goods?.goodsSku?.stock ?? goods?.stock) || 0))
    const minimum = Math.max(1, Math.ceil(Number(goods?.min_buy) || 1))
    if (quantity > stock) return { quantity, error: `库存不足，最多可选 ${stock} 件` }
    if (quantity < minimum) return { quantity, error: `该商品 ${minimum} 件起购，填 0 可移出购物车` }
    if (Number(goods?.is_limit) === 1 && Number(goods?.max_buy) > 0) {
        const perOrder = Number(goods.limit_type) === 1
        const remaining = Math.max(0, Number(goods.max_buy) - (perOrder ? 0 : Number(goods.has_buy) || 0))
        if (quantity > remaining) {
            return { quantity, error: perOrder ? `该商品单次限购 ${goods.max_buy} 件` : `该商品还可购买 ${remaining} 件` }
        }
    }
    return { quantity, error: '' }
}
