import request from '@/utils/request'

// USER_CODE_BEGIN -- quotation

// ==================== 报价爬虫配置 ====================
export function getQuotationCrawlerConfig() {
    return request.get('recycle_daheng_quote/quotation_crawler_config')
}

export function saveQuotationCrawlerConfig(params: Record<string, any>) {
    return request.post('recycle_daheng_quote/quotation_crawler_config', params, { showErrorMessage: true, showSuccessMessage: false })
}

export function getQuotationCrawlerDefaultConfig() {
    return request.get('recycle_daheng_quote/quotation_crawler_config/default')
}

// ==================== 报价 2.0 ====================
export function getQuotationV2DatasetList(params: Record<string, any>) {
    return request.get('recycle_daheng_quote/quotation_v2/dataset', { params })
}

export function getQuotationV2DatasetAll(params: Record<string, any> = {}) {
    return request.get('recycle_daheng_quote/quotation_v2/dataset/all', { params })
}

export function getQuotationV2DisplayConfig() {
    return request.get('recycle_daheng_quote/quotation_v2/display_config')
}

export function saveQuotationV2DisplayConfig(params: Record<string, any>) {
    return request.post('recycle_daheng_quote/quotation_v2/display_config', params, { showErrorMessage: true, showSuccessMessage: true })
}

export function addQuotationV2Dataset(params: Record<string, any>) {
    return request.post('recycle_daheng_quote/quotation_v2/dataset', params, { showErrorMessage: true, showSuccessMessage: true })
}

export function editQuotationV2Dataset(id: number, params: Record<string, any>) {
    return request.put(`recycle_daheng_quote/quotation_v2/dataset/${id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function deleteQuotationV2Dataset(id: number) {
    return request.delete(`recycle_daheng_quote/quotation_v2/dataset/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function initQuotationV2ChaoniuDefaults() {
    return request.post('recycle_daheng_quote/quotation_v2/dataset/init_chaoniu_defaults', {}, { showErrorMessage: true, showSuccessMessage: true })
}

export function previewQuotationV2Dataset(datasetId: number) {
    return request.post(`recycle_daheng_quote/quotation_v2/sync/preview/${datasetId}`, {}, { showErrorMessage: true, showSuccessMessage: false })
}

export function importQuotationV2Preview(logId: number) {
    return request.post(`recycle_daheng_quote/quotation_v2/sync/import/${logId}`, {}, { showErrorMessage: true, showSuccessMessage: true })
}

export function syncQuotationV2Now(datasetId: number) {
    return request.post(`recycle_daheng_quote/quotation_v2/sync/now/${datasetId}`, {}, { showErrorMessage: true, showSuccessMessage: true })
}

export function getQuotationV2Models(params: Record<string, any>) {
    return request.get('recycle_daheng_quote/quotation_v2/model', { params })
}

export function addQuotationV2Model(params: Record<string, any>) {
    return request.post('recycle_daheng_quote/quotation_v2/model', params, { showErrorMessage: true, showSuccessMessage: true })
}

export function editQuotationV2Model(id: number, params: Record<string, any>) {
    return request.put(`recycle_daheng_quote/quotation_v2/model/${id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function deleteQuotationV2Model(id: number) {
    return request.delete(`recycle_daheng_quote/quotation_v2/model/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getQuotationV2Capacities(params: Record<string, any>) {
    return request.get('recycle_daheng_quote/quotation_v2/capacity', { params })
}

export function addQuotationV2Capacity(params: Record<string, any>) {
    return request.post('recycle_daheng_quote/quotation_v2/capacity', params, { showErrorMessage: true, showSuccessMessage: true })
}

export function editQuotationV2Capacity(id: number, params: Record<string, any>) {
    return request.put(`recycle_daheng_quote/quotation_v2/capacity/${id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function deleteQuotationV2Capacity(id: number) {
    return request.delete(`recycle_daheng_quote/quotation_v2/capacity/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getQuotationV2Fields(params: Record<string, any>) {
    return request.get('recycle_daheng_quote/quotation_v2/field', { params })
}

export function addQuotationV2Field(params: Record<string, any>) {
    return request.post('recycle_daheng_quote/quotation_v2/field', params, { showErrorMessage: true, showSuccessMessage: true })
}

export function editQuotationV2Field(id: number, params: Record<string, any>) {
    return request.put(`recycle_daheng_quote/quotation_v2/field/${id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function deleteQuotationV2Field(id: number) {
    return request.delete(`recycle_daheng_quote/quotation_v2/field/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getQuotationV2Prices(params: Record<string, any>) {
    return request.get('recycle_daheng_quote/quotation_v2/price', { params })
}

export function getQuotationV2PriceMatrix(params: Record<string, any>) {
    return request.get('recycle_daheng_quote/quotation_v2/price/matrix', { params })
}

export function getQuotationV2Notes(params: Record<string, any>) {
    return request.get('recycle_daheng_quote/quotation_v2/note', { params })
}

export function addQuotationV2Note(params: Record<string, any>) {
    return request.post('recycle_daheng_quote/quotation_v2/note', params, { showErrorMessage: true, showSuccessMessage: true })
}

export function editQuotationV2Note(id: number, params: Record<string, any>) {
    return request.put(`recycle_daheng_quote/quotation_v2/note/${id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function editQuotationV2NoteGroup(id: number, params: Record<string, any>) {
    return request.put(`recycle_daheng_quote/quotation_v2/note/${id}/group`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function deleteQuotationV2Note(id: number) {
    return request.delete(`recycle_daheng_quote/quotation_v2/note/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function deleteQuotationV2NoteGroup(id: number) {
    return request.delete(`recycle_daheng_quote/quotation_v2/note/${id}/group`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getQuotationV2Logs(params: Record<string, any>) {
    return request.get('recycle_daheng_quote/quotation_v2/log', { params })
}

export function adjustQuotationV2Price(id: number, params: Record<string, any>) {
    return request.put(`recycle_daheng_quote/quotation_v2/price/${id}/adjust`, params, { showErrorMessage: true, showSuccessMessage: true })
}

export function batchAdjustQuotationV2Price(params: Record<string, any>) {
    return request.post('recycle_daheng_quote/quotation_v2/price/batch_adjust', params, { showErrorMessage: true, showSuccessMessage: true })
}
