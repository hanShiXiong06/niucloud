import request from '@/utils/request'

type RequestExtra = { showErrorMessage?: boolean; showSuccessMessage?: boolean }

// 骑手信息接口
export function getRunnerInfo() {
    return request.get('sd_xiaoyuan/runner/info')
}

export function applyRunner(data: any) {
    return request.post('sd_xiaoyuan/runner/apply', data)
}

export function updateLocation(data: any) {
    return request.post('sd_xiaoyuan/runner/update_location', data)
}

export function setOnline(data: any) {
    return request.post('sd_xiaoyuan/runner/set_online', data)
}

export function setRange(data: any, config: RequestExtra = {}) {
    return request.post('sd_xiaoyuan/runner/set_range', data, config)
}

export function updateRunnerInfo(data: any, config: RequestExtra = {}) {
    return request.post('sd_xiaoyuan/runner/update_info', data, config)
}

export function getRunnerStat() {
    return request.get('sd_xiaoyuan/runner/stat')
}

export function getRunnerIncome(params: any) {
    return request.get('sd_xiaoyuan/runner/income', params)
}

export function getBalanceLog(params: any) {
    return request.get('sd_xiaoyuan/runner/balance_log', params)
}

// 骑手订单接口
export function getOrderHall(params: any) {
    return request.get('sd_xiaoyuan/runner/order/hall', params)
}

export function getMyOrders(params: any) {
    return request.get('sd_xiaoyuan/runner/order/my', params)
}

export function getOrderDetail(id: number) {
    return request.get(`sd_xiaoyuan/runner/order/detail/${id}`)
}

export function acceptOrder(data: any) {
    return request.post('sd_xiaoyuan/runner/order/accept', data)
}

export function rejectOrder(data: any) {
    return request.post('sd_xiaoyuan/runner/order/reject', data)
}

export function pickupOrder(data: any) {
    return request.post('sd_xiaoyuan/runner/order/pickup', data)
}

export function deliveryOrder(data: any) {
    return request.post('sd_xiaoyuan/runner/order/delivery', data)
}

export function completeOrder(data: any) {
    return request.post('sd_xiaoyuan/runner/order/complete', data)
}

// 评价接口
export function getRunnerEvaluates(params: any) {
    return request.get('sd_xiaoyuan/runner/evaluate/list', params)
}

// 申诉接口
export function getAppealList(params: any) {
    return request.get('sd_xiaoyuan/runner/appeal/list', params)
}

export function addAppeal(data: any) {
    return request.post('sd_xiaoyuan/runner/appeal/add', data)
}

export function getAppealDetail(id: number) {
    return request.get(`sd_xiaoyuan/runner/appeal/detail/${id}`)
}
