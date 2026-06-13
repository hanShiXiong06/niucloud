import request from '@/utils/request'

export function getAssetPool(params: Record<string, any>) {
    return request.get('device_asset/pool', { params })
}

export function getAssetList(params: Record<string, any>) {
    return request.get('device_asset/lists', { params })
}

export function getAssetStats() {
    return request.get('device_asset/stats')
}

export function getAssetInfo(id: number | string) {
    return request.get(`device_asset/info/${ id }`)
}

export function importAssets(deviceIds: number[]) {
    return request.post('device_asset/import', { device_ids: deviceIds })
}

export function createPhotoTask(id: number | string, data: Record<string, any>) {
    return request.post(`device_asset/photo_task/${ id }`, data)
}

export function saveAssetMedia(id: number | string, data: Record<string, any>) {
    return request.post(`device_asset/media/${ id }`, data)
}

export function confirmAssetPhotos(id: number | string) {
    return request.post(`device_asset/photos/confirm/${ id }`)
}

export function completeAssetPrice(id: number | string, data: Record<string, any>) {
    return request.post(`device_asset/price/${ id }`, data)
}

// 扫码入库（二维码 device_id / IMEI / SN）
export function scanImportAsset(keyword: string) {
    return request.post('device_asset/scan', { keyword })
}

// 照片复检：单张通过/退回
export function reviewAssetMedia(mediaId: number | string, data: Record<string, any>) {
    return request.post(`device_asset/media/review/${ mediaId }`, data)
}

// 照片复检：整台批量通过/退回
export function reviewAssetMediaBatch(id: number | string, data: Record<string, any>) {
    return request.post(`device_asset/media/review_batch/${ id }`, data)
}

// 设置 / 修改资产库位（库管在仓里归位）
export function setAssetLocation(id: number | string, data: Record<string, any>) {
    return request.post(`device_asset/location/${ id }`, data)
}

// ERP 仓库/库位树（设库位用）
export function getAssignWarehouseTree() {
    return request.get('device_asset/assign/warehouse_tree')
}
