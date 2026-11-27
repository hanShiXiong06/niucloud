import { cooperationTaskLink } from './cooperation-navigation'

export const cooperationPaymentOrderStatuses = [
    { value: 'unpaid', label: '待支付' },
    { value: 'paid', label: '已支付' }, { value: 'closed', label: '已关闭' }
]
export function cooperationPaymentOrderStatus(value: unknown) {
    return cooperationPaymentOrderStatuses.some(item => item.value === value) ? String(value) : 'unpaid'
}
export const cooperationPaymentRefundStates = ['none', 'pending', 'approved', 'refunding', 'refunded', 'rejected', 'cancelled', 'attention', 'failed']
export function cooperationPaymentRefundDetail(value: unknown, id: number): Record<string, any> {
    const item = value as Record<string, any>
    if (!item || Number(item.order_id) !== id || !cooperationPaymentRefundStates.includes(item.status) || typeof item.amount !== 'string' || !/^\d+(?:\.\d{1,2})?$/.test(item.amount)) throw new Error('退款资料未完整返回，请重新读取原申请')
    return item
}
export function cooperationPaymentOrderIsVirtual(order: Record<string, any>) {
    return order.payment_type === 'hsx_lead_virtualpay'
}
export function cooperationPaymentOrderCanPay(order: Record<string, any>) {
    if (order.status !== 'unpaid' || order.can_pay !== true || order.requires_manual_review || order.quote_expired
        || ['pending', 'paid_pending', 'attention', 'paid', 'closed'].includes(String(order.payment_state || ''))) return false
    return !cooperationPaymentOrderIsVirtual(order) || order.payment_state === 'ready' || order.payment_recovery?.state === 'ready'
}
/** Inspect the existing virtual attempt only; this URL never authorizes another charge. */
export function cooperationPaymentOrderCheckUrl(order: Record<string, any>) {
    const id = Number(order.id)
    if (!Number.isSafeInteger(id) || id <= 0 || !(order.status === 'unpaid' || order.payment_state === 'paid_pending')
        || !(order.can_check_payment === true || order.purpose === 'membership' && cooperationPaymentOrderIsVirtual(order))) return ''
    if (order.purpose === 'membership') return `/addon/hsx_lead/pages/membership/index?payment_order_id=${id}`
    const lead = Number(order.lead_id)
    return order.purpose === 'commission' && Number.isSafeInteger(lead) && lead > 0
        ? cooperationTaskLink('commission', { collaboration_id: lead, payment_order_id: id }) : ''
}
/** Resume only a server-confirmed original order, never create a replacement. */
export function cooperationPaymentOrderResumeUrl(order: Record<string, any>) {
    const id = Number(order.id)
    if (!Number.isSafeInteger(id) || id <= 0 || !cooperationPaymentOrderCanPay(order)) return ''
    if (order.purpose === 'membership') return `/addon/hsx_lead/pages/membership/index?payment_order_id=${id}`
    const lead = Number(order.lead_id)
    if (order.purpose === 'commission' && Number.isSafeInteger(lead) && lead > 0) return cooperationTaskLink('commission', { collaboration_id: lead, payment_order_id: id })
    return ''
}
export function cooperationPaymentReplacementId(order: Record<string, any>) {
    const id = Number(order.replacement_order_id)
    return order.status === 'closed' && Number.isSafeInteger(id) && id > 0 && id !== Number(order.id) ? id : 0
}
export function cooperationPaymentReplacementMatches(old: Record<string, any>, next: Record<string, any>) {
    if (Number(next.id) !== cooperationPaymentReplacementId(old) || Number(next.reprices_order_id) !== Number(old.id) || next.purpose !== old.purpose || Number(next.member_id) !== Number(old.member_id) || Number(next.merchant_id) !== Number(old.merchant_id)) return false
    return old.purpose === 'membership' ? next.level_code === old.level_code && next.cycle === old.cycle : old.purpose === 'commission' && Number(next.lead_id) === Number(old.lead_id)
}
