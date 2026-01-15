import request from '@/utils/request'

/***************************************************** 订单列表 ****************************************************/

/**
 * 获取订单状态列表
 */
export function getOrderStatus() {
    return request.get(`mall/site/order/status`)
}

/**
 * 获取订单列表
 * @returns
 */
export function getOrderList(params: Record<string, any>) {
    return request.get('mall/site/order/list', params)
}


/**
 * 获取订单详情
 */
export function getOrderDetail(order_id :number) {
    return request.get(`mall/site/order/detail/${order_id}`)
}

/**
 * 获取订单配送方式
 * @return
 */
export function getOrderDeliveryType(params: Record<string, any>) {
    return request.get(`mall/site/order/delivery_type`, params)
}

/**
 * 订单发货
 * @return
 */
export function orderDelivery(params: Record<string, any>) {
    return request.put(`mall/site/order/delivery`, params)
}

/**
 * 关闭订单
 */
export function orderClose(order_id: number) {
    return request.put(`mall/site/order/close/${order_id}`)
}

/**
 * 订单删除
 * @return
 */
export function orderDelete(params: Record<string, any>) {
    return request.post(`mall/site/order/delete`,params, { showSuccessMessage: true })
}

/**
 * 订单完成
 * @return
 */
export function orderFinish(order_id: number) {
    return request.put(`mall/site/order/finish/${order_id}`)
}

/**
 * 物流包裹信息（物流跟踪）
 * @return
 */
export function deliveryPackage(params: Record<string, any>) {
    return request.get(`mall/site/order/delivery/package`, params)
}

/**
 * 商家留言
 * @return
 */
export function setShopRemark(params: Record<string, any>) {
    return request.put(`mall/site/order/shop_remark`, params)
}

/**
 * 获取订单地址信息
 * @return
 */
export function getOrderEditAddress(params: Record<string, any>) {
    return request.get(`mall/site/order/edit_delivery`, params)
}

/**
 * 订单调价
 * @return
 */
export function orderEditPrice(params: Record<string, any>) {
    return request.put(`mall/site/order/edit_price`, params, { showSuccessMessage: true })
}

/**
 * 修改地址
 * @return
 */
export function orderEditAddress(params: Record<string, any>) {
    return request.put(`mall/site/order/edit_delivery`, params)
}

/**
 * 获取已选订单项总重量
 * @return
 */
export function getSelectOrderGoodsWeight(params: Record<string, any>) {
    return request.get(`mall/site/order/select/weight`, params)
}

/**
 * 获取已选订单项配送费用
 * @return
 */
export function getDeliveryFee(params: Record<string, any>) {
    return request.get(`mall/site/order/delivery/fee`, params)
}

/**
 * 商家主动退款
 */
export function shopActiveRefund(params: Record<string, any>) {
    return request.post(`mall/site/order/refund/active`, params)
}