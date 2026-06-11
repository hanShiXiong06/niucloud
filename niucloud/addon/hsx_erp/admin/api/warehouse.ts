import request from '@/utils/request'

export function getErpWarehouseList() {
    return request.get('erp/warehouse/lists')
}

export function getErpWarehouseOptions() {
    return request.get('erp/warehouse/options')
}

export function saveErpWarehouse(id: number, data: Record<string, any>) {
    return request.post(`erp/warehouse/save/${id}`, data)
}

export function deleteErpWarehouse(id: number) {
    return request.delete(`erp/warehouse/${id}`)
}

export function saveErpWarehouseLocation(warehouseId: number, id: number, data: Record<string, any>) {
    return request.post(`erp/warehouse/${warehouseId}/location/save/${id}`, data)
}

export function deleteErpWarehouseLocation(id: number) {
    return request.delete(`erp/warehouse/location/${id}`)
}
