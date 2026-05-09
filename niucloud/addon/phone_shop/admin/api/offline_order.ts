import request from '@/utils/request'

/**
 * 创建线下订单
 */
export function createOfflineOrder(params: Record<string, any>) {
    return request.post('phone_shop/offline_order/create', params)
}

/**
 * 获取线下订单列表
 */
export function getOfflineOrderList(params: Record<string, any>) {
    return request.get('phone_shop/offline_order/lists', params)
}
