import request from '@/utils/request'

export function getErpAssetList(params: Record<string, any>) {
    return request.get('erp/asset/lists', { params })
}

// 集成状态：中台是否接入(接入后拍照/定价交给中台)
export function getErpIntegrationStatus() {
    return request.get('erp/asset/integration_status')
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
