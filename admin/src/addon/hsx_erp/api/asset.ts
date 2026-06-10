import request from '@/utils/request'

export function getErpAssetList(params: Record<string, any>) {
    return request.get('erp/asset/lists', { params })
}

export function getErpAssetInfo(id: number) {
    return request.get(`erp/asset/${ id }`)
}

export function createErpManualInbound(data: Record<string, any>) {
    return request.post('erp/asset/manual_inbound', data)
}

export function confirmErpAssetInbound(id: number, data: Record<string, any> = {}) {
    return request.post(`erp/asset/${ id }/confirm_inbound`, data)
}

export function batchConfirmErpAssetInbound(assetIds: number[], data: Record<string, any> = {}) {
    return request.post('erp/asset/batch_confirm_inbound', {
        asset_ids: assetIds,
        ...data
    })
}
