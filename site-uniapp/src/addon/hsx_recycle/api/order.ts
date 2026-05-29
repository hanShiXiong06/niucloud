import request from '@/utils/request'

export function getOrderList(params: Record<string, any>) {
    return request.get('recycle/recycle_order/lists', params)
}

export function getOrderDetail(id: number | string) {
    return request.get(`recycle/recycle_order/detail/${id}`)
}

export function getOrderDevices(id: number | string) {
    return request.get(`recycle/recycle_order/${id}/devices`)
}

export function getOrderStatus() {
    return request.get('recycle/recycle_order/status')
}

export function getOrderBusinessStageOptions() {
    return request.get('recycle/recycle_order/business_stage_options')
}

export function searchMemberList(params: Record<string, any>) {
    return request.get('member/member', params)
}

export function updateOrder(id: number | string, data: Record<string, any>) {
    return request.put(`recycle/recycle_order/${id}`, data)
}

export function deleteOrder(id: number | string) {
    return request.delete(`recycle/recycle_order/${id}`)
}

export function paymentConfirm(id: number | string, data: Record<string, any> = {}) {
    return request.put(`recycle/recycle_order/${id}/payment_confirm`, data)
}

export function devicePaymentConfirm(id: number | string, data: Record<string, any>) {
    return request.post(`recycle/recycle_order/${id}/device_payment_confirm`, data)
}

export function confirmOrderDevices(id: number | string, data: Record<string, any>) {
    return request.post(`recycle/recycle_order/${id}/device_confirm`, data)
}

export function getMerchantPayInfo(memberId: number | string) {
    return request.get(`recycle/recycle_order/merchant_pay_info/${memberId}`)
}

export function pushOrderNotify(id: number | string) {
    return request.post(`recycle/recycle_order/${id}/push_notify`)
}

export function getDevicePaymentLogs(id: number | string) {
    return request.get(`recycle/recycle_order/${id}/device_payment_logs`)
}

export function getOrderNoticeLogs(id: number | string) {
    return request.get(`recycle/recycle_order/${id}/notice_logs`)
}

// 设备相关接口
export function getDevice(id: number | string) {
    return request.get(`recycle/recycle_device/${id}`)
}

export function updateDevice(id: number | string, data: Record<string, any>) {
    return request.put(`recycle/recycle_device/${id}`, data)
}

export function confirmPrice(id: number | string, data: Record<string, any>) {
    return request.put(`recycle/recycle_device/${id}/confirm_price`, data)
}

export function batchRecycleDevices(data: Record<string, any>) {
    return request.post('recycle/recycle_device/batch_recycle', data)
}

export function batchReturnDevices(data: Record<string, any>) {
    return request.post('recycle/recycle_device/batch_return', data)
}

// 转代卖
export function transferDeviceToConsignment(deviceId: number | string, data: Record<string, any>) {
    return request.post(`recycle/recycle_device/${deviceId}/transfer_consignment`, data)
}
