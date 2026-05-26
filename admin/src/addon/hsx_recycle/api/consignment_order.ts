import request from '@/utils/request'

export function getConsignmentOrderList(params: Record<string, any>) {
  return request.get('/recycle/consignment_order/lists', { params })
}

export function getConsignmentOrderInfo(id: number | string) {
  return request.get(`/recycle/consignment_order/${id}`)
}

export function getConsignmentStatusOptions() {
  return request.get('/recycle/consignment_order/status')
}

export function transferDeviceToConsignment(deviceId: number | string, data: Record<string, any>) {
  return request.post(`/recycle/recycle_device/${deviceId}/transfer_consignment`, data)
}

export function updateConsignmentListing(id: number | string, data: Record<string, any>) {
  return request.put(`/recycle/consignment_order/${id}/listing`, data)
}

export function markConsignmentSold(id: number | string, data: Record<string, any>) {
  return request.put(`/recycle/consignment_order/${id}/sold`, data)
}

export function settleConsignment(id: number | string, data: Record<string, any>) {
  return request.put(`/recycle/consignment_order/${id}/settle`, data)
}

export function closeConsignment(id: number | string, data: Record<string, any>) {
  return request.put(`/recycle/consignment_order/${id}/close`, data)
}

export function pushConsignmentNotify(id: number | string) {
  return request.post(`/recycle/consignment_order/${id}/push_notify`, {}, {
    showErrorMessage: true,
    showSuccessMessage: true
  })
}
