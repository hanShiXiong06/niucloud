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
// 回填价格
export function fillErpOutboundPrice(id: number, items: any[]) {
    return request.post(`erp/outbound/${id}/fill_price`, { items })
}
// 调拨
export function transferErpAsset(data: Record<string, any>) {
    return request.post('erp/outbound/transfer', data)
}
