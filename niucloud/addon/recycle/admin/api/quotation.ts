import request from '@/utils/request'

// USER_CODE_BEGIN -- quotation

// ==================== 报价爬虫配置 ====================
export function getQuotationCrawlerConfig() {
    return request.get('recycle/quotation_crawler_config')
}

export function saveQuotationCrawlerConfig(params: Record<string, any>) {
    return request.post('recycle/quotation_crawler_config', params, { showErrorMessage: true, showSuccessMessage: false })
}

export function getQuotationCrawlerDefaultConfig() {
    return request.get('recycle/quotation_crawler_config/default')
}

// ==================== 报价 2.0 ====================
export function getQuotationV2DatasetList(params: Record<string, any>) {
    return request.get('recycle/quotation_v2/dataset', { params })
}

export function getQuotationV2DatasetAll(params: Record<string, any> = {}) {
    return request.get('recycle/quotation_v2/dataset/all', { params })
}

export function getQuotationV2DisplayConfig() {
    return request.get('recycle/quotation_v2/display_config')
}

export function saveQuotationV2DisplayConfig(params: Record<string, any>) {
    return request.post('recycle/quotation_v2/display_config', params, { showErrorMessage: true, showSuccessMessage: true })
}

export function addQuotationV2Dataset(params: Record<string, any>) {
    return request.post('recycle/quotation_v2/dataset', params, { showErrorMessage: true, showSuccessMessage: true })
}

export function editQuotationV2Dataset(id: number, params: Record<string, any>) {
    return request.put(`recycle/quotation_v2/dataset/${id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function deleteQuotationV2Dataset(id: number) {
    return request.delete(`recycle/quotation_v2/dataset/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function initQuotationV2ChaoniuDefaults() {
    return request.post('recycle/quotation_v2/dataset/init_chaoniu_defaults', {}, { showErrorMessage: true, showSuccessMessage: true })
}

export function previewQuotationV2Dataset(datasetId: number) {
    return request.post(`recycle/quotation_v2/sync/preview/${datasetId}`, {}, { showErrorMessage: true, showSuccessMessage: false })
}

export function importQuotationV2Preview(logId: number) {
    return request.post(`recycle/quotation_v2/sync/import/${logId}`, {}, { showErrorMessage: true, showSuccessMessage: true })
}

export function syncQuotationV2Now(datasetId: number) {
    return request.post(`recycle/quotation_v2/sync/now/${datasetId}`, {}, { showErrorMessage: true, showSuccessMessage: true })
}

export function getQuotationV2Models(params: Record<string, any>) {
    return request.get('recycle/quotation_v2/model', { params })
}

export function addQuotationV2Model(params: Record<string, any>) {
    return request.post('recycle/quotation_v2/model', params, { showErrorMessage: true, showSuccessMessage: true })
}

export function editQuotationV2Model(id: number, params: Record<string, any>) {
    return request.put(`recycle/quotation_v2/model/${id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function deleteQuotationV2Model(id: number) {
    return request.delete(`recycle/quotation_v2/model/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getQuotationV2Capacities(params: Record<string, any>) {
    return request.get('recycle/quotation_v2/capacity', { params })
}

export function addQuotationV2Capacity(params: Record<string, any>) {
    return request.post('recycle/quotation_v2/capacity', params, { showErrorMessage: true, showSuccessMessage: true })
}

export function editQuotationV2Capacity(id: number, params: Record<string, any>) {
    return request.put(`recycle/quotation_v2/capacity/${id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function deleteQuotationV2Capacity(id: number) {
    return request.delete(`recycle/quotation_v2/capacity/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getQuotationV2Fields(params: Record<string, any>) {
    return request.get('recycle/quotation_v2/field', { params })
}

export function addQuotationV2Field(params: Record<string, any>) {
    return request.post('recycle/quotation_v2/field', params, { showErrorMessage: true, showSuccessMessage: true })
}

export function editQuotationV2Field(id: number, params: Record<string, any>) {
    return request.put(`recycle/quotation_v2/field/${id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function deleteQuotationV2Field(id: number) {
    return request.delete(`recycle/quotation_v2/field/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getQuotationV2Prices(params: Record<string, any>) {
    return request.get('recycle/quotation_v2/price', { params })
}

export function getQuotationV2PriceMatrix(params: Record<string, any>) {
    return request.get('recycle/quotation_v2/price/matrix', { params })
}

export function getQuotationV2Notes(params: Record<string, any>) {
    return request.get('recycle/quotation_v2/note', { params })
}

export function addQuotationV2Note(params: Record<string, any>) {
    return request.post('recycle/quotation_v2/note', params, { showErrorMessage: true, showSuccessMessage: true })
}

export function editQuotationV2Note(id: number, params: Record<string, any>) {
    return request.put(`recycle/quotation_v2/note/${id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function deleteQuotationV2Note(id: number) {
    return request.delete(`recycle/quotation_v2/note/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getQuotationV2Logs(params: Record<string, any>) {
    return request.get('recycle/quotation_v2/log', { params })
}

export function adjustQuotationV2Price(id: number, params: Record<string, any>) {
    return request.put(`recycle/quotation_v2/price/${id}/adjust`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function batchAdjustQuotationV2Price(params: Record<string, any>) {
    return request.post('recycle/quotation_v2/price/batch_adjust', params, { showErrorMessage: true, showSuccessMessage: true })
}

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
    // params.quotation_id = 114
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
 * modifyQuotationPriceConfigStatus
 * 修改价格配置状态
 * @param id
 * @param params
 * @returns
*/
export function modifyQuotationPriceConfigStatus(id: number, params: Record<string, any>) {
    return request.put(`recycle/quotation_price_config/${id}/modify_status`, params, { showErrorMessage: true, showSuccessMessage: true })
}


//quotation_model/lists
export function getQuotationModelList(params: Record<string, any>) {
    return request.get(`recycle/quotation_model/lists`, { params })
}

// ==================== 规格管理 ====================
// 型号管理
export function getQuotationSpecModelList(params: Record<string, any>) {
    return request.get(`recycle/quotation_spec/model/lists`, { params })
}

export function setQuotationSpecModelSyncStatus(id: number, params: { sync_enable: number }) {
    return request.put(`recycle/quotation_spec/model/${id}/sync_status`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function batchSetQuotationSpecModelSyncStatus(params: { ids: number[], sync_enable: number }) {
    return request.post(`recycle/quotation_spec/model/batch_sync_status`, params, { showErrorMessage: true, showSuccessMessage: true })
}

// 内存管理
export function getQuotationSpecCapacityList(params: Record<string, any>) {
    return request.get(`recycle/quotation_spec/capacity/lists`, { params })
}

export function setQuotationSpecCapacitySyncStatus(id: number, params: { sync_enable: number }) {
    return request.put(`recycle/quotation_spec/capacity/${id}/sync_status`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function batchSetQuotationSpecCapacitySyncStatus(params: { ids: number[], sync_enable: number }) {
    return request.post(`recycle/quotation_spec/capacity/batch_sync_status`, params, { showErrorMessage: true, showSuccessMessage: true })
}

// 等级规格管理
export function getQuotationSpecGradeSpecList(params: Record<string, any>) {
    return request.get(`recycle/quotation_spec/grade_spec/lists`, { params })
}

export function setQuotationSpecGradeSpecSyncStatus(id: number, params: { sync_enable: number }) {
    return request.put(`recycle/quotation_spec/grade_spec/${id}/sync_status`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function batchSetQuotationSpecGradeSpecSyncStatus(params: { ids: number[], sync_enable: number }) {
    return request.post(`recycle/quotation_spec/grade_spec/batch_sync_status`, params, { showErrorMessage: true, showSuccessMessage: true })
}

// 同步统计
export function getQuotationSpecSyncStats() {
    return request.get(`recycle/quotation_spec/sync_stats`)
}

// USER_CODE_END -- quotation
