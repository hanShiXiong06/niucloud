export const offlinePaymentModes = ['offline_pending', 'offline_cash', 'offline_credit']
export const isOfflineOrder = (order: any) => offlinePaymentModes.includes(String(order?.payment_mode || ''))
export const managedByErp = (order: any) => Boolean(order?.relate_source) || ['offline_cash', 'offline_credit'].includes(String(order?.payment_mode || ''))
export const orderGoods = (order: any): any[] => Array.isArray(order?.order_goods) ? order.order_goods.filter((goods: any) => Number(goods.is_gift || 0) !== 1) : []
export function objectValue(value: any): Record<string, any> {
    if (value && typeof value === 'object' && !Array.isArray(value)) return value
    if (typeof value === 'string') { try { const parsed = JSON.parse(value); return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {} } catch {} }
    return {}
}
export function goodsIdentity(goods: any): string {
    const identity = objectValue(goods?.device_identity)
    const extend = objectValue(goods?.extend)
    return String(identity.imei || identity.sn || identity.sku_no || goods?.sku?.sku_no || extend.imei || extend.sn || extend.sku_no || '')
}
export const orderStatusLabel = (order: any): string => {
    const workflow: Record<string, string> = { pending: '待联系', contacted: '已联系待到店', voucher_submitted: '凭证待审核', voucher_rejected: '凭证已驳回', paid: '已收款待交付', credit: '已挂账待交付', delivered: '已交付', closed: '已关闭' }
    if (isOfflineOrder(order) && workflow[String(order?.offline_record?.status || '')] && [1, 2].includes(Number(order?.status))) return workflow[String(order.offline_record.status)]
    return String(order?.status_name || ({ 1: '待支付', 2: order?.delivery_type === 'store' ? '待提货' : '待发货', 3: '待收货', 5: '已完成', '-1': '已关闭' } as Record<string, string>)[String(order?.status)] || '状态待核对')
}
export const paymentLabel = (order: any) => ({ offline_pending: '线下待确认', offline_cash: '线下已收款', offline_credit: '客户挂账' } as Record<string, string>)[String(order?.payment_mode || '')] || '线上订单'
export interface OrderAction { key: string; label: string; danger?: boolean; hint?: string }
/** 与 PC 的可操作状态对齐；最终权限、支付结果和设备状态仍由同一后端校验。 */
export function getOrderActions(order: any): OrderAction[] {
    if (!Number(order?.order_id)) return []
    const status = Number(order.status), mode = String(order.payment_mode || '')
    const actions: OrderAction[] = []
    if (status === 1 && mode === 'offline_pending') {
        actions.push({ key: 'confirm_paid', label: '确认线下收款' }, { key: 'confirm_credit', label: '确认客户挂账' })
        if (String(order.offline_record?.status || '') === 'voucher_submitted') actions.push({ key: 'reject_voucher', label: '驳回付款凭证', danger: true })
        else actions.push({ key: 'contacted', label: '标记已联系' })
        actions.push({ key: 'close_unreachable', label: '无法联系并关闭', danger: true })
    } else if (status === 1 && !managedByErp(order) && !Number(order.is_credit || 0)) {
        actions.push({ key: 'close', label: '关闭未付款订单', danger: true })
    }
    if (status === 2) actions.push({ key: 'delivery', label: order.delivery_type === 'store' ? '确认到店交付' : '发货' })
    if (status === 3) actions.push({ key: 'finish', label: '确认订单完成' })
    if ([2, 3, 5, -1].includes(status) && managedByErp(order)) actions.push({ key: 'return', label: '收回设备 / 退回处理', danger: true })
    if (![1, -1].includes(status) && !managedByErp(order) && orderGoods(order).some(goods => Number(goods.is_enable_refund) === 1 && Number(goods.status) === 1)) {
        actions.push({ key: 'refund', label: '原路退款', danger: true })
    }
    if (orderGoods(order).some(goods => Boolean(goods.can_confirm_received))) actions.push({ key: 'received', label: '确认退货设备已收回' })
    actions.push({ key: 'remark', label: '商家备注' })
    return actions
}
export function orderQuery(channel: string, status: string, keyword: string, orderId = 0) {
    const params: Record<string, any> = { offline_keyword: keyword.trim() }
    if (['online', 'offline'].includes(channel)) params.order_channel = channel
    if (status !== '') params.status = Number(status)
    if (orderId > 0) params.order_id = orderId
    return params
}
export const money = (value: any) => Number.isFinite(Number(value)) ? Number(value).toFixed(2) : '—'
export function formatOrderTime(value: any): string {
    if (typeof value === 'string' && !/^\d+$/.test(value)) return value || '—'
    const timestamp = Number(value)
    if (!Number.isFinite(timestamp) || timestamp <= 0) return '—'
    const date = new Date(timestamp * 1000), pad = (part: number) => String(part).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}
