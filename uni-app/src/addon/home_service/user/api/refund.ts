import request from '@/utils/request'

/**
 * 获取退款原因列表
 * @returns Promise
 */
export function getRefundReasonList() {
  return request.get('home_service/refund/reason')
}

/**
 * 提交退款申请
 * @param params 退款申请参数
 * @returns Promise
 */
export function submitRefund(params: Record<string, any>) {
  return request.post('home_service/refund/apply', params, { showErrorMessage: true })
}






/**
 * 申请退款
 * @params
 * @returns
 */
export function refundApply(params: Record<string, any>) {
    return request.post(`home_service/refund/apply`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 获取退款详情
 * @param refundId
 * @returns
 */
export function getRefundDetail(refundId: number) {
    return request.get(`home_service/refund/${ refundId }`)
}

/**
 * 取消退款申请
 * @param refundId
 * @returns
 */
export function cancelRefund(refundId: number) {
    return request.put(`home_service/refund/cancel/${ refundId }`, {}, { showSuccessMessage: true })
}

/**
 * 获取退款原因
 * @returns
 */
export function getRefundReason() {
    return request.get(`home_service/refund/reason`)
}

/**
 * 获取退款状态
 * @returns
 */
export function getRefundStatus() {
    return request.get(`home_service/refund/status`)
}

/**
 * 获取退款列表
 * @param params
 * @returns
 *
 */
export function getRefundList(params: Record<string, any>) {
    return request.get('home_service/refund/lists', params);
}

