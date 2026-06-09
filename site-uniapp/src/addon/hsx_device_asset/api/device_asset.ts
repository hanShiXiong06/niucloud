import request from '@/utils/request'

export function getAssetInfo(id: number | string) {
    return request.get(`device_asset/info/${ id }`)
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
