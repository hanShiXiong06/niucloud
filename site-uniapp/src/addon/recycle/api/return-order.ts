import request from '@/utils/request'

export function getReturnOrderList(params: Record<string, any>) {
    return request.get('recycle/recycle_return_order/lists', params)
}

export function getReturnOrderDetail(id: number | string) {
    return request.get(`recycle/recycle_return_order/${id}/device_info`)
}

export function getReturnOrderStatusCount() {
    return request.get('recycle/recycle_return_order/status')
}

export function getReturnOrderStatusList() {
    return request.get('recycle/recycle_return_order/status_list')
}

export function confirmReturnOrder(id: number | string, data: Record<string, any>) {
    return request.put(`recycle/recycle_return_order/${id}/confirm`, data)
}

export function updateReturnOrderStatus(id: number | string, data: Record<string, any>) {
    return request.put(`recycle/recycle_return_order/${id}/status`, data)
}

export function cancelReturnOrder(id: number | string, comment = '') {
    return request.put(`recycle/recycle_return_order/${id}/cancel`, { comment })
}

export function deleteReturnOrder(id: number | string) {
    return request.delete(`recycle/recycle_return_order/${id}`)
}
