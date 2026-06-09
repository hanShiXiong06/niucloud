import request from '@/utils/request'

export function getAssetPool(params: Record<string, any>) {
    return request.get('device_asset/pool', { params })
}

export function getAssetList(params: Record<string, any>) {
    return request.get('device_asset/lists', { params })
}

export function getAssetInfo(id: number) {
    return request.get(`device_asset/info/${ id }`)
}

export function importAssets(deviceIds: number[]) {
    return request.post('device_asset/import', { device_ids: deviceIds })
}

export function scanImportAsset(keyword: string) {
    return request.post('device_asset/scan', { keyword })
}

export function createPhotoTask(id: number, data: Record<string, any>) {
    return request.post(`device_asset/photo_task/${ id }`, data)
}

export function saveAssetMedia(id: number, data: Record<string, any>) {
    return request.post(`device_asset/media/${ id }`, data)
}

export function reviewAssetMedia(mediaId: number, data: Record<string, any>) {
    return request.post(`device_asset/media/review/${ mediaId }`, data)
}

export function confirmAssetPhotos(id: number) {
    return request.post(`device_asset/photos/confirm/${ id }`)
}

export function completeAssetPrice(id: number, data: Record<string, any>) {
    return request.post(`device_asset/price/${ id }`, data)
}

export function exportAssetExcel(params: Record<string, any>) {
    return request.post('device_asset/export', params, { responseType: 'blob' })
}
