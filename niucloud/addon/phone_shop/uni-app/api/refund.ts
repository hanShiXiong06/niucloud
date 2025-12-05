import request from '@/utils/request'

/**
 * 申请退款
 */
export function applyRefund(params: Record<string, any>) {
	return request.post(`phone_shop/refund/apply`, params, { showSuccessMessage: true })
}

/**
 * 修改退款申请
 */
export function editRefund(params: Record<string, any>) {
	return request.put(`phone_shop/refund/${params.order_refund_no}`, params, { showSuccessMessage: true })
}

/**
 * 申请退款退货
 */
export function refundDelivery(params: Record<string, any>) {
	return request.post(`phone_shop/refund/delivery/${params.order_refund_no}`, params, { showSuccessMessage: true })
}

/**
 * 修改退款申请
 */
export function editRefundDelivery(params: Record<string, any>) {
	return request.put(`phone_shop/refund/delivery/${params.order_refund_no}`, params, { showSuccessMessage: true })
}

/**
 * 获取退款原因
 */
export function getRefundReason() {
    return request.get('phone_shop/refund/reason')
}

/**
 * 获取退款列表
 */
export function getRefundList() {
    return request.get('phone_shop/order/refund')
}

/**
 * 获取退款详情
 */
export function getRefundDetail(orderRefundNo: string) {
    return request.get(`phone_shop/order/refund/${orderRefundNo}`)
}

/**
 * 获取退款金额
 */
export function getRefundMoney(params: Record<string, any>) {
    return request.get(`phone_shop/refund/refund_data`,params)
}

/**
 * 编辑退款金额
 */
export function getRefundMoneyAgain(params: Record<string, any>) {
    return request.get(`phone_shop/refund/refund_data_by_no`,params)
}

/**
 * 取消维权
 */
export function closeRefund(orderRefundNo: string) {
    return request.put(`phone_shop/refund/close/${orderRefundNo}`,{}, { showSuccessMessage: true })
}
