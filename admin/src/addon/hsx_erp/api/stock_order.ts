import request from '@/utils/request'

export function getErpStockOrderList(params: Record<string, any>) {
    return request.get('erp/stock_order/lists', { params })
}

export function getErpStockOrderInfo(id: number) {
    return request.get(`erp/stock_order/${id}`)
}

export function confirmErpStockOrderItems(id: number, itemIds: number[], data: Record<string, any> = {}) {
    return request.post(`erp/stock_order/${id}/confirm_items`, {
        item_ids: itemIds,
        ...data
    })
}

export function rejectErpStockOrderItems(id: number, itemIds: number[], reason: string) {
    return request.post(`erp/stock_order/${id}/reject_items`, {
        item_ids: itemIds,
        reason
    })
}

export function resubmitErpStockOrderItem(id: number, itemId: number, data: Record<string, any>) {
    return request.post(`erp/stock_order/${id}/item/${itemId}/resubmit`, data)
}
