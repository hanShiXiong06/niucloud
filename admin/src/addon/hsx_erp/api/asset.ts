import request from '@/utils/request'

export function getErpAssetList(params: Record<string, any>) {
    return request.get('erp/asset/lists', { params })
}

// 库存概览（设备中心顶部卡片）
export function getErpAssetOverview() {
    return request.get('erp/asset/overview')
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

// 完成拍照:待拍照 → 入库在库(传图)
export function completeErpAssetPhoto(id: number, images: string[]) {
    return request.post(`erp/asset/${id}/complete_photo`, { images })
}

// 实时调整在库设备成本(写成本流水)
export function adjustErpAssetCost(id: number, data: Record<string, any>) {
    return request.post(`erp/asset/${id}/adjust_cost`, data)
}
