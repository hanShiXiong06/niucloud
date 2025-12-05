
import request from '@/utils/request'

/***************************************************** AI设计 ****************************************************/
export function getConfig() {
    return request.get(`ai_image/config/getconfig`)
}

export function verifyNum(card_num: any) {
    return request.post(`ai_image/aiimagecard/verify/${card_num}`)
}

export function getCardList(data: any) {
    return request.get(`ai_image/aiimagecard`, data)
}

export function changeExport(id: any) {
    return request.put(`ai_image/aiimagecard/${id}`)
}
export function deltetCard(id: any) {
    return request.delete(`ai_image/aiimagecard/${id}`)
}
export function getPackageList(data: any) {
    return request.get(`ai_image/aiimagepackage`, data)
}
export function getModelList(data: any) {
    return request.get(`ai_image/aiimagemodel`, data)
}
export function getModelInfo(id: any) {
    return request.get(`ai_image/aiimagemodel/${id}`)
}
export function createImage(data: any) {
    return request.post(`ai_image/aiimagecreate`, data)
}
export function getImageList(data: any) {
    return request.get(`ai_image/aiimagecreate`, data)
}
export function deleteImage(id: any) {
    return request.delete(`ai_image/aiimagecreate/${id}`)
}
export function getImageInfo(id: any) {
    return request.get(`ai_image/aiimagecreate/${id}`)
}
export function sendText(data: any) {
    return request.post(`ai_image/sendtext`, data)
}
export function addOrder(data: any) {
    return request.post(`ai_image/aiimageorder`, data)
}
export function getOrderList(data: any) {
    return request.get(`ai_image/aiimageorder`, data)
}
export function getStat() {
    return request.get(`ai_image/getstat`)
}