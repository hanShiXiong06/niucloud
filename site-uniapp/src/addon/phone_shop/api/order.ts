import request from '@/utils/request'

/** 统一订单列表；不默认把线上订单排除。order_channel 为空/online/offline。 */
export function getOrderList(params: Record<string, any> = {}) {
    return request.get('phone_shop/order/list', params)
}

/** 商家移动端线下订单工作台 */
export function getOfflineOrderList(params: Record<string, any> = {}) {
    return request.get('phone_shop/order/list', { ...params, offline_workflow: 1 })
}

/** 商城订单详情 */
export function getOfflineOrderDetail(orderId: number | string) {
    return request.get(`phone_shop/order/detail/${ orderId }`)
}

export const getOrderDetail = getOfflineOrderDetail

export function closeOrder(orderId: number | string) {
    return request.put(`phone_shop/order/close/${orderId}`, {}, { showErrorMessage: false })
}

export function finishOrder(orderId: number | string) {
    return request.put(`phone_shop/order/finish/${orderId}`, {}, { showErrorMessage: false })
}

export function deliverOrder(data: Record<string, any>) {
    return request.put('phone_shop/order/delivery', data, { showErrorMessage: false })
}

export function getDeliveryCompanies() {
    return request.get('phone_shop/delivery/company/list', {}, { showErrorMessage: false })
}

export function getOrderDeliveryTypes(deliveryType: string) {
    return request.get('phone_shop/order/delivery_type', { delivery_type: deliveryType }, { showErrorMessage: false })
}

export function confirmOrderDeviceReceived(orderGoodsId: number | string) {
    return request.post(`phone_shop/order/device_received/${orderGoodsId}`, { received: true }, { showErrorMessage: false })
}

export function refundOrder(data: Record<string, any>) {
    return request.post('phone_shop/order/refund/active', data, { showErrorMessage: false })
}

export function setOrderRemark(orderId: number | string, remark: string) {
    return request.put('phone_shop/order/shop_remark', { order_id: orderId, shop_remark: remark }, { showErrorMessage: false })
}

/** ERP 可用资金账户；未安装或未配置 ERP 时返回空数组 */
export function getOfflineCapitalAccounts() {
    return request.get('phone_shop/order/offline/capital_accounts')
}

/** 联系、收款、挂账、交付或关闭线下订单 */
export function processOfflineOrder(params: Record<string, any>) {
    return request.post('phone_shop/order/offline/process', params, { showSuccessMessage: true })
}

export function processOrderAction(params: Record<string, any>) {
    return request.post('phone_shop/order/offline/process', params, { showSuccessMessage: false, showErrorMessage: false })
}
