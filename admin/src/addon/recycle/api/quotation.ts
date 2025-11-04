import request from '@/utils/request'

// USER_CODE_BEGIN -- quotation

// ==================== 报价单配置 ====================
/**
 * 获取报价单配置列表
 * @param params
 * @returns
 */
export function getQuotationConfigList(params: Record<string, any>) {
    return request.get(`recycle/quotation_config`, { params })
}

/**
 * 获取报价单配置详情
 * @param id 报价单配置id
 * @returns
 */
export function getQuotationConfigInfo(id: number) {
    return request.get(`recycle/quotation_config/${id}`)
}

/**
 * 添加报价单配置
 * @param params
 * @returns
 */
export function addQuotationConfig(params: Record<string, any>) {
    return request.post('recycle/quotation_config', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑报价单配置
 * @param params
 * @returns
 */
export function editQuotationConfig(params: Record<string, any>) {
    return request.put(`recycle/quotation_config/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除报价单配置
 * @param id
 * @returns
 */
export function deleteQuotationConfig(id: number) {
    return request.delete(`recycle/quotation_config/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 修改报价单配置状态
 * @param id
 * @param params
 * @returns
 */
export function modifyQuotationConfigStatus(id: number, params: Record<string, any>) {
    return request.put(`recycle/quotation_config/${id}/modify_status`, params, { showErrorMessage: true, showSuccessMessage: true })
}

// ==================== 报价请求记录 ====================
/**
 * 获取报价请求记录列表
 * @param params
 * @returns
 */
export function getQuotationRequestList(params: Record<string, any>) {
    return request.get(`recycle/quotation_request`, { params })
}

/**
 * 获取报价请求记录详情
 * @param id 报价请求记录id
 * @returns
 */
export function getQuotationRequestInfo(id: number) {
    return request.get(`recycle/quotation_request/${id}`)
}

/**
 * 发送报价请求
 * @param params
 * @returns
 */
export function sendQuotationRequest(params: Record<string, any>) {
    return request.post('recycle/quotation_request/send_request', params, { showErrorMessage: true, showSuccessMessage: true })
}

// ==================== 报价数据 ====================
/**
 * 获取报价数据列表
 * @param params
 * @returns
 */
export function getQuotationDataList(params: Record<string, any>) {
    return request.get(`recycle/quotation_data`, { params })
}

/**
 * 获取所有报价数据（不分页，用于表格展示）
 * @param params
 * @returns
 */
export function getQuotationDataAll(params: Record<string, any>) {
    return request.get(`recycle/quotation_data/all`, { params })
}

/**
 * 获取报价数据详情
 * @param id 报价数据id
 * @returns
 */
export function getQuotationDataInfo(id: number) {
    return request.get(`recycle/quotation_data/${id}`)
}

/**
 * 获取级联选项（型号、内存、配置项）
 * @param params quotation_id, price_name
 * @returns
 */
export function getQuotationDataCascadeOptions(params?: { quotation_id?: number, price_name?: string }) {
    return request.get(`recycle/quotation_data/cascade_options`, { params })
}

// ==================== 价格配置 ====================
/**
 * 获取价格配置列表
 * @param params
 * @returns
 */
export function getQuotationPriceConfigList(params: Record<string, any>) {
    return request.get(`recycle/quotation_price_config`, { params })
}

/**
 * 获取价格配置详情
 * @param id 价格配置id
 * @returns
 */
export function getQuotationPriceConfigInfo(id: number) {
    return request.get(`recycle/quotation_price_config/${id}`)
}

/**
 * 添加价格配置
 * @param params
 * @returns
 */
export function addQuotationPriceConfig(params: Record<string, any>) {
    return request.post('recycle/quotation_price_config', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑价格配置
 * @param params
 * @returns
 */
export function editQuotationPriceConfig(params: Record<string, any>) {
    return request.put(`recycle/quotation_price_config/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除价格配置
 * @param id
 * @returns
 */
export function deleteQuotationPriceConfig(id: number) {
    return request.delete(`recycle/quotation_price_config/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 批量删除价格配置
 * @param ids
 * @returns
 */
export function batchDeleteQuotationPriceConfig(ids: number[]) {
    return request.post(`recycle/quotation_price_config/batch_del`, { ids }, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 批量添加SKU配置（智能合并）
 * @param params
 * @returns
 */
export function batchAddSkuPriceConfig(params: { skus: Array<{goods_id: number, goods_name?: string, capacity: string, capacity_answer_id?: number, config_item_name: string}>, adjustment_type: number, adjustment_value: number, is_enable?: number }) {
    return request.post(`recycle/quotation_price_config/batch_add_sku`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 清空所有价格配置
 * @returns
 */
export function clearAllQuotationPriceConfig() {
    return request.post(`recycle/quotation_price_config/clear_all`, {}, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 批量修改报价数据（调价）
 * @param params
 * @returns
 */
export function batchUpdateQuotationPrice(params: {
    items: Array<{
        id: number
        goods_id: number
        capacity: string
        config_name: string
        new_price: number
    }>
}) {
    return request.post(`recycle/quotation_data/batch_update_price`, params, { showErrorMessage: true, showSuccessMessage: true })
}

// USER_CODE_END -- quotation

