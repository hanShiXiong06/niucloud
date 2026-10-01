/** 链接只初始化页面已有的筛选；分页、站点和登录身份不能由链接覆盖。 */
export const createGoodsListFilters = () => ({
    category_ids: [] as string[],
    memory_group: [] as string[],
    condition_grade: [] as string[],
    device_color: [] as string[],
    battery_range: [] as string[],
    warranty_range: [] as string[],
    label_ids: [] as string[],
    service_ids: [] as string[],
    brand_ids: [] as string[],
    start_price: '' as string | number,
    end_price: '' as string | number,
    source: '',
    warehouse: '',
    in_stock: false
})

const routeText = (value: unknown): string => {
    if (typeof value !== 'string' && typeof value !== 'number') return ''
    const text = String(value).trim()
    // H5 / 小程序入口可能已解码；遇到字面量 % 也不能让整个页面崩溃。
    try { return decodeURIComponent(text).trim() } catch { return text }
}

const routeValues = (value: unknown): string[] => [...new Set(
    (Array.isArray(value) ? value : [value])
        .flatMap(item => routeText(item).split(','))
        .map(item => item.trim()).filter(Boolean)
)]

const positiveId = (value: unknown): string => {
    const text = routeText(value)
    return /^\d+$/.test(text) && Number.isSafeInteger(Number(text)) && Number(text) > 0
        ? String(Number(text)) : ''
}

const routeIds = (value: unknown): string[] => [...new Set(routeValues(value).map(positiveId).filter(Boolean))]
const routePrice = (value: unknown): string => {
    const text = routeText(value)
    return /^\d+(\.\d{1,2})?$/.test(text) && Number.isFinite(Number(text)) ? text : ''
}

export function parseGoodsListRoute(params: Record<string, unknown> = {}) {
    const filters = createGoodsListFilters()
    // 空字符串代表用户已清除条件，不能再次回退到旧别名。
    filters.category_ids = routeIds(params.goods_category ?? params.category_ids ?? params.category_id ?? params.curr_goods_category)
    filters.brand_ids = routeIds(params.brand_id ?? params.brand_ids)
    filters.label_ids = routeIds(params.label_ids)
    filters.service_ids = routeIds(params.service_ids)
    for (const key of ['memory_group', 'condition_grade', 'device_color', 'battery_range', 'warranty_range'] as const) {
        filters[key] = routeValues(params[key])
    }
    filters.start_price = routePrice(params.start_price)
    filters.end_price = routePrice(params.end_price)
    filters.in_stock = ['1', 'true'].includes(routeText(params.in_stock).toLowerCase())
    const warehouse = routeText(params.warehouse)
    filters.warehouse = ['local', 'agent'].includes(warehouse) ? warehouse : ''
    const source = routeText(params.source).toLowerCase()
    filters.source = ['self', 'local'].includes(source) ? '1'
        : (['agent', 'proxy'].includes(source) ? 'agent' : positiveId(source))
    if (filters.source) filters.warehouse = filters.source === '1' ? 'local' : 'agent'

    const order = routeText(params.order)
    return {
        filters,
        keyword: routeText(params.keyword ?? params.goods_name).slice(0, 50),
        couponId: positiveId(params.coupon_id),
        arrivalBatchId: Number(positiveId(params.arrival_batch_id) || 0),
        newArrival: routeText(params.new_arrival) === '1',
        order: ['price', 'sale_num', 'latest'].includes(order) ? order : 'all',
        sort: routeText(params.sort) === 'asc' ? 'asc' : 'desc',
        layout: routeText(params.layout) === 'waterfall' ? 'waterfall' : 'list'
    }
}
