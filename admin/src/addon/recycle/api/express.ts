import request from '@/utils/request'

/**
 * 获取快递订单记录列表
 */
export function getExpressOrderRecordList(params: any) {
    return request.get('recycle/express_order_record/lists', { params })
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
    return request.get('recycle/express_order_record/weight_diff_list', { params })
}

/**
 * 获取费用差异列表
 */
export function getCostDiffList(params: any) {
    return request.get('recycle/express_order_record/cost_diff_list', { params })
}

/**
 * 获取快递费用统计
 */
export function getExpressOrderStatistics(params: any) {
    return request.get('recycle/express_order_record/statistics', { params })
}

/**
 * 根据回收订单ID获取快递记录
 */
export function getExpressOrderByRecycleOrderId(params: any) {
    return request.get('recycle/express_order_record/by_recycle_order', { params })
}

/**
 * 创建易速快递订单
 */
export function createExpressOrder(params: any) {
    return request.post('recycle/yisu/create_order', params)
}

// ============ 统一快递服务接口 ============

export function getExpressQuote(params: any) {
    return request.post('recycle/express_order/quote', params)
}

export function createExpressOrderDirect(params: any) {
    return request.post('recycle/express_order/create', params)
}

/**
 * 为回收订单创建快递单（管理员操作）
 */
export function createExpressForOrder(params: any) {
    return request.post('recycle/express_order/create_for_order', params)
}

/**
 * 取消回收订单的快递单（管理员操作）
 */
export function cancelExpressForOrder(params: any) {
    return request.post('recycle/express_order/cancel_for_order', params)
}

/**
 * 查询回收订单的快递轨迹（管理员操作）
 */
export function trackExpressForOrder(params: any) {
    return request.get('recycle/express_order/track_for_order', params)
}

/**
 * 获取统一快递报价（管理员操作）
 */
export function getUnifiedExpressQuote(params: any) {
    return request.post('recycle/express_order/unified_quote', params)
}

export function getExpressFund() {
    return request.get('recycle/express_order/balance')
}

export function getExpressOrderDetail(params: any) {
    return request.get('recycle/express_order/detail', { params })
}

export function cancelOrInterceptExpressOrder(params: any) {
    return request.post('recycle/express_order/cancel', params)
}

export function modifyExpressOrder(params: any) {
    return request.post('recycle/express_order/modify', params)
}

export function getExpressWaybillPdf(params: any) {
    return request.post('recycle/express_order/waybill_pdf', params)
}

// ============ 快递服务商配置接口 ============

/**
 * 获取服务商配置列表
 */
export function getExpressProviderConfigList() {
    return request.get('recycle/express_provider_config/lists')
}

/**
 * 获取服务商配置详情
 */
export function getExpressProviderConfigInfo(id: number) {
    return request.get(`recycle/express_provider_config/${id}`)
}

/**
 * 编辑服务商配置
 */
export function editExpressProviderConfig(id: number, params: any) {
    return request.put(`recycle/express_provider_config/${id}`, params)
}

/**
 * 设置默认服务商
 */
export function setDefaultExpressProvider(id: number) {
    return request.put(`recycle/express_provider_config/set_default/${id}`)
}

/**
 * 切换服务商启用状态
 */
export function toggleExpressProviderStatus(id: number) {
    return request.put(`recycle/express_provider_config/toggle_status/${id}`)
}

/**
 * 获取当前启用的服务商
 */
export function getActiveExpressProvider() {
    return request.get('recycle/express_provider_config/active')
}

/**
 * 检查快递服务状态
 */
export function checkExpressServiceStatus() {
    return request.get('recycle/express_provider_config/check_status')
}
