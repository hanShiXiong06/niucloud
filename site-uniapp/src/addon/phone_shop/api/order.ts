import request from '@/utils/request'

/** 商家移动端线下订单工作台 */
export function getOfflineOrderList(params: Record<string, any> = {}) {
    return request.get('phone_shop/order/list', { ...params, offline_workflow: 1 })
}

/** 商城订单详情 */
export function getOfflineOrderDetail(orderId: number | string) {
    return request.get(`phone_shop/order/detail/${ orderId }`)
}

/** ERP 可用资金账户；未安装或未配置 ERP 时返回空数组 */
export function getOfflineCapitalAccounts() {
    return request.get('phone_shop/order/offline/capital_accounts')
}

/** 联系、收款、挂账、交付或关闭线下订单 */
export function processOfflineOrder(params: Record<string, any>) {
    return request.post('phone_shop/order/offline/process', params, { showSuccessMessage: true })
}
