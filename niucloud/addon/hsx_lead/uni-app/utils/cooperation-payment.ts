/** A collaboration payment belongs to one merchant and one lead. Membership has its own page and contract. */
export interface CooperationPaymentContext {
    purpose: 'commission'
    leadId: number
}
export function cooperationPaymentContext(purpose: unknown, leadId: unknown = 0): CooperationPaymentContext | null {
    if (purpose !== 'commission') return null
    const id = Number(leadId)
    if (!Number.isSafeInteger(id) || id <= 0) return null
    return { purpose: purpose as CooperationPaymentContext['purpose'], leadId: id }
}
export function cooperationOrderContext(
    order: Record<string, any>,
    merchantId: unknown
): CooperationPaymentContext | null {
    const id = Number(order?.id),
        merchant = Number(merchantId)
    if (
        !Number.isSafeInteger(id) ||
        id <= 0 ||
        !Number.isSafeInteger(merchant) ||
        merchant <= 0 ||
        Number(order?.merchant_id) !== merchant
    )
        return null
    return cooperationPaymentContext(order.purpose, order.lead_id)
}
export function sameCooperationPaymentContext(left: CooperationPaymentContext, right: CooperationPaymentContext) {
    return left.purpose === right.purpose && left.leadId === right.leadId
}
