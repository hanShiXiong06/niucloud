import request from '@/utils/request'

export function getErpPricingList(params: Record<string, any>) {
    return request.get('erp/pricing/lists', { params })
}

export function getErpPricingInfo(assetId: number) {
    return request.get(`erp/pricing/asset/${assetId}`)
}

export function saveErpAssetPrice(assetId: number, data: Record<string, any>) {
    return request.post(`erp/pricing/asset/${assetId}/price`, data)
}
