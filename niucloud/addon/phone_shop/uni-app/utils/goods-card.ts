export type GoodsCardActionEvent = 'detail' | 'cart' | 'download'

export interface GoodsCardAction {
    event: GoodsCardActionEvent
    text: string
    style: string
}

/** 分类页与搜索列表共用同一份商品操作配置，不自行默认开启转发。 */
export function resolveGoodsCardAction(config: any): GoodsCardAction | null {
    const cart = config?.cart
    if (Number(cart?.control) !== 1) return null
    const event: GoodsCardActionEvent = ['detail', 'cart', 'download'].includes(cart.event) ? cart.event : 'detail'
    const defaults = { detail: '购买', cart: '加入购物车', download: '转发' }
    return {
        event,
        text: event === 'cart' ? defaults.cart : String(cart.text || '').trim() || defaults[event],
        style: cart.style || 'style-1'
    }
}

export function needsGoodsDetailLogin(config: any, token: unknown): boolean {
    return Number(config?.detail_login_required) === 1 && !token
}

export function goodsPriceBadgeType(goods: any, token: unknown): string {
    const type = goods?.goodsSku?.show_type || goods?.show_type || ''
    // 会员价属于登录态；不能因商品数据缓存而给游客展示 VIP 标识。
    return type === 'member_price' && !token ? '' : type
}
