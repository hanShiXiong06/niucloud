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

export function getDeviceModelDictTree() {
    return request.get('recycle/recycle_device_model_dict/tree')
}

export function getDeviceModelDictChildren(params: Record<string, any> = {}) {
    return request.get('recycle/recycle_device_model_dict/children', params)
}

export function searchDeviceModelDictOptions(params: Record<string, any> = {}) {
    return request.get('recycle/recycle_device_model_dict/options', params)
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

// 查询当前订单的付款归属，不能用站点是否安装 ERP 推断历史订单的归属。
export function getCapitalAccountOptions(orderId: number | string, deviceIds?: Array<number | string>) {
    return request.get('recycle/recycle_order/capital_accounts', { order_id: orderId, ...(deviceIds ? { device_ids: deviceIds } : {}) }, { showErrorMessage: false })
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

export function getDeviceCostAdjustLogs(id: number | string) {
    return request.get(`recycle/recycle_device/${id}/cost_adjust_logs`, {}, { showErrorMessage: false })
}

export function getDeviceCostAdjustAbility(id: number | string) {
    return request.get(`recycle/recycle_device/${id}/cost_adjust_ability`, {}, { showErrorMessage: false })
}

export function adjustDeviceCost(id: number | string, data: Record<string, any>) {
    return request.post(`recycle/recycle_device/${id}/cost_adjust`, data)
}

export function scanSearchDevice(params: Record<string, any>) {
    return request.get('recycle/recycle_device/scan_search', params)
}

export function updateDevice(id: number | string, data: Record<string, any>) {
    return request.put(`recycle/recycle_device/${id}`, data)
}

export function confirmPrice(id: number | string, data: Record<string, any>) {
    return request.put(`recycle/recycle_device/${id}/confirm_price`, data)
}

export function getStaffOptions() {
    return request.get('recycle/stats/getUserList')
}

export function getRefurbishmentOptions() {
    return request.get('recycle/recycle_device/refurbishment_options')
}

// 销售去向选项（含 ERP 仓库/库位、是否连接 ERP），与 PC 端 PriceFormDialog 一致
export function getSaleDestinationOptions(deviceId?: number) {
    return request.get('recycle/recycle_device/sale_destination_options', deviceId ? { device_id: deviceId } : {})
}

// IMEI 信息查询（质检/签收时校验有效性、带出机型信息）
export function getImeiInfo(imei: string) {
    return request.get(`recycle/recycle_device/imei_info/${imei}`)
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
