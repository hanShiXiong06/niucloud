import request from '@/utils/request'

// 出库单列表
export function getErpOutboundList(params: Record<string, any>) {
    return request.get('erp/outbound/lists', { params })
}
// 出库单详情
export function getErpOutboundInfo(id: number) {
    return request.get(`erp/outbound/${id}`)
}
// 新建出库(同行销售/报废)
export function createErpOutbound(data: Record<string, any>) {
    return request.post('erp/outbound/create', data)
}
// 回填价格(可选立即收款入账)
export function fillErpOutboundPrice(id: number, items: any[], options: Record<string, any> = {}) {
    return request.post(`erp/outbound/${id}/fill_price`, { items, ...options })
}
// 卖同行待办(待回填价/待收款)
export function getErpPeerSaleTodo(params: Record<string, any> = {}) {
    return request.get('erp/outbound/peer_sale_todo', { params })
}
// 调拨
export function transferErpAsset(data: Record<string, any>) {
    return request.post('erp/outbound/transfer', data)
}
// 退回/取消出库(仅挂单/未定价、未收款)
export function cancelErpOutbound(id: number, reason = '') {
    return request.post(`erp/outbound/${id}/cancel`, { reason })
}
