import request from '@/utils/request'

/**
 * 创建线下销售订单
 */
export function createOfflineSale(params: Record<string, any>) {
    return request.post('phone_shop/sale/offline_sale/create', params)
}

/**
 * 获取线下销售订单列表
 */
export function getOfflineSaleList(params: Record<string, any>) {
    return request.get('phone_shop/sale/offline_sale/lists', params)
}

/**
 * 搜索会员
 */
export function searchMember(params: Record<string, any>) {
    return request.get('member/member', params)
}
