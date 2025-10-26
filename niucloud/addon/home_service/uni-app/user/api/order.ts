import request from '@/utils/request'

/***************************************************** 订单 ***************************************************/
/**
 * 订单确认
 */
export function orderConfirm(params: Record<string, any>) {
    return request.get(`home_service/order/confirm`, params)
}

/**
 * 订单计算
 */
export function orderCalculate(params: Record<string, any>) {
    return request.post(`home_service/order/calculate`, params)
}

/**
 * 订单创建
 */
export function orderCreate(params: Record<string, any>) {
    return request.post(`home_service/order/create`, params, { showSuccessMessage: true })
}

/**
 * 获取订单列表
 * @param params
 * @returns
 *
 */
export function getOrderList(params: Record<string, any>) {
    return request.get('home_service/order', params);
}

/**
 * 获取订单详情
 * @param orderId
 * @returns
 *
 */
export function getOrderDetail(orderId: number) {
    return request.get(`home_service/order/${ orderId }`);
}

/**
 * 获取订单状态
 * @returns
 *
 */
export function getOrderStatus() {
    return request.get(`home_service/order/status`);
}

/**
 * 取消订单
 * @param orderId
 * @returns
 */
export function cancelOrder(orderId: number) {
    return request.put(`home_service/order/cancel/${ orderId }`, {}, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除订单
 * @param orderId
 * @returns
 */
export function deleteOrder(orderId: number) {
    return request.delete(`home_service/order/${ orderId }`, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 获取订单角标数据
 */
export function getOrderNum() {
    return request.get(`home_service/order/getNum`)
}


/**
 * 查询订单可用优惠券
 */
export function orderCoupon(params: Record<string, any>) {
    return request.get('home_service/order/create/coupon', params)
}

/**
 * 查询订单可用优惠券
 */
export function checkOrder(params: Record<string, any>) {
    return request.post('home_service/order/check', params,{ showSuccessMessage: true })
}

// 获取refund_no
export function getRefundNo(orderId: number) {
    return request.get(`home_service/refund/latest/order/${ orderId }`);
}