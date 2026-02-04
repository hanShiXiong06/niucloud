import request from '@/utils/request'

/**
 * 获取快递订单记录列表
 */
export function getExpressOrderRecordList(params: any) {
    return request.get('recycle/express_order_record/lists', params)
}

/**
 * 获取快递订单记录详情
 */
export function getExpressOrderRecordInfo(id: number) {
    return request.get(`recycle/express_order_record/${id}`)
}

/**
 * 添加快递订单记录
 */
export function addExpressOrderRecord(params: any) {
    return request.post('recycle/express_order_record', params)
}

/**
 * 编辑快递订单记录
 */
export function editExpressOrderRecord(id: number, params: any) {
    return request.put(`recycle/express_order_record/${id}`, params)
}

/**
 * 删除快递订单记录
 */
export function deleteExpressOrderRecord(id: number) {
    return request.delete(`recycle/express_order_record/${id}`)
}

/**
 * 更新订单状态
 */
export function updateExpressOrderStatus(params: any) {
    return request.post('recycle/express_order_record/update_status', params)
}

/**
 * 更新实际重量和费用
 */
export function updateExpressOrderActualInfo(params: any) {
    return request.post('recycle/express_order_record/update_actual_info', params)
}

/**
 * 获取重量差异列表
 */
export function getWeightDiffList(params: any) {
    return request.get('recycle/express_order_record/weight_diff_list', params)
}

/**
 * 获取费用差异列表
 */
export function getCostDiffList(params: any) {
    return request.get('recycle/express_order_record/cost_diff_list', params)
}

/**
 * 获取快递费用统计
 */
export function getExpressOrderStatistics(params: any) {
    return request.get('recycle/express_order_record/statistics', params)
}

/**
 * 根据回收订单ID获取快递记录
 */
export function getExpressOrderByRecycleOrderId(params: any) {
    return request.get('recycle/express_order_record/by_recycle_order', params)
}

/**
 * 创建易速快递订单
 */
export function createExpressOrder(params: any) {
    return request.post('recycle/yisu/create_order', params)
}
