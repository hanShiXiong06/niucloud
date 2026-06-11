import request from '@/utils/request'

export function getErpRefurbishmentList(params: Record<string, any>) {
    return request.get('erp/refurbishment/lists', { params })
}

export function getErpRefurbishmentInfo(id: number) {
    return request.get(`erp/refurbishment/${id}`)
}

export function getErpRefurbishmentUsers(params: Record<string, any> = {}) {
    return request.get('erp/refurbishment/user_options', { params })
}

export function createErpRefurbishment(data: Record<string, any>) {
    return request.post('erp/refurbishment/create', data)
}

export function completeErpRefurbishment(id: number, data: Record<string, any>) {
    return request.post(`erp/refurbishment/${id}/complete`, data)
}

export function cancelErpRefurbishment(id: number, data: Record<string, any> = {}) {
    return request.post(`erp/refurbishment/${id}/cancel`, data)
}

export function skipErpRefurbishment(assetId: number, data: Record<string, any> = {}) {
    return request.post(`erp/refurbishment/asset/${assetId}/skip`, data)
}
