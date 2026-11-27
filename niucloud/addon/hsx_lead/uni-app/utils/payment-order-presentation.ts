type PaymentRow = Record<string, any>
export type PaymentOrderPresentation = {
    title: string
    hint: string
    tone: 'neutral' | 'warning' | 'success' | 'danger'
    amountLabel: string
}

/** Display only. Actions must still use the server's can_pay / can_close contract. */
export function paymentOrderPresentation(order: PaymentRow): PaymentOrderPresentation {
    const amountLabel =
        order.status === 'paid' || order.payment_state === 'paid_pending'
            ? '已付金额'
            : order.status === 'unpaid'
              ? '待付金额'
              : '订单金额'
    if (order.requires_manual_review || order.payment_state === 'attention')
        return {
            title: '付款需核对',
            tone: 'danger',
            amountLabel,
            hint: order.review_message || order.payment_message || '请联系平台核对这笔订单；核对前不要再次付款。'
        }
    if (order.payment_state === 'paid_pending')
        return {
            title: order.purpose === 'membership' ? '开通处理中' : '付款确认处理中',
            tone: 'warning',
            amountLabel,
            hint: order.payment_message || '款项已确认，正在更新业务结果。请核对进度，不要再次付款。'
        }
    if (order.refund_status === 'refunded' || order.membership_refund_status === 'refunded')
        return {
            title: '已退款',
            tone: 'neutral',
            amountLabel,
            hint:
                order.purpose === 'membership'
                    ? '退款结果已确认。当前权益请到会员页查看，重新购买前会重新核价。'
                    : '退款结果已确认，请返回协作查看最新业务结果。'
        }
    if (order.status === 'closed')
        return {
            title: '已关闭',
            tone: 'neutral',
            amountLabel,
            hint:
                order.closure_reason ||
                (order.purpose === 'membership'
                    ? '这笔订单已结束；如仍需开通，可重新选择套餐并核对费用。'
                    : '这笔付款单已结束，请返回协作查看最新业务结果。')
        }
    if (order.status === 'paid')
        return {
            title: '已支付',
            tone: 'success',
            amountLabel,
            hint:
                order.refund_status && order.refund_status !== 'none'
                    ? order.refund_status_name || '退款申请处理中，请查看退款进度。'
                    : order.purpose === 'membership'
                      ? '付款已确认。当前等级、有效期和账号名额可在会员页查看。'
                      : '付款已确认，可返回协作查看最新业务结果。'
        }
    if (order.payment_check_required === true || order.payment_state === 'pending')
        return {
            title: '付款结果待确认',
            tone: 'warning',
            amountLabel,
            hint: order.payment_message || '请核对这笔订单的付款结果。确认前不会重复发起扣款。'
        }
    if (order.status === 'unpaid' && order.quote_expired)
        return {
            title: '报价待更新',
            tone: 'warning',
            amountLabel,
            hint: order.close_message || '本次报价已过期，请先核对原单；确认关闭后再重新核价。'
        }
    if (order.status === 'unpaid' && order.can_pay === true)
        return {
            title: '等待付款',
            tone: 'neutral',
            amountLabel,
            hint: '继续付款时可选择当前支持的支付方式。取消支付窗口不会取消这笔订单。'
        }
    return {
        title: order.status === 'unpaid' ? '订单待核对' : '状态待核对',
        tone: 'warning',
        amountLabel,
        hint: order.close_reason || order.payment_message || '请重新读取订单，确认状态后再操作。'
    }
}

export function paymentOrderPlanCaption(order: PaymentRow): string {
    if (order.purpose !== 'membership') return '成交协作付佣'
    const months = Number(order.entitlement_months)
    const period =
        typeof order.cycle_name === 'string' && order.cycle_name.trim()
            ? order.cycle_name.trim()
            : Number.isSafeInteger(months) && months > 0
              ? `${months}个月`
              : ({ month: '月付', quarter: '季付', year: '年付' } as Record<string, string>)[String(order.cycle)] || ''
    return ['门店会员', period].filter(Boolean).join(' · ')
}
