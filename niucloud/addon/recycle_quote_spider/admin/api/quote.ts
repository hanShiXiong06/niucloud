import request from '@/utils/request'

export function getQuoteSourceList(params: Record<string, any>) {
    return request.get('recycle_quote_spider/source', { params })
}

export function getQuoteSourceAll() {
    return request.get('recycle_quote_spider/source/all')
}

export function addQuoteSource(params: Record<string, any>) {
    return request.post('recycle_quote_spider/source', params, { showErrorMessage: true })
}

export function editQuoteSource(id: number, params: Record<string, any>) {
    return request.put(`recycle_quote_spider/source/${id}`, params, { showErrorMessage: true })
}

export function createDefaultQuoteSource() {
    return request.post('recycle_quote_spider/source/default', {}, { showErrorMessage: true })
}

export function syncQuoteSource(id: number) {
    return request.post(`recycle_quote_spider/source/${id}/sync`, {}, { showErrorMessage: true })
}

export function getQuoteCategoryList(params: Record<string, any>) {
    return request.get('recycle_quote_spider/category', { params })
}

export function getQuoteCategoryTree(params: Record<string, any>) {
    return request.get('recycle_quote_spider/category/tree', { params })
}

export function editQuoteCategory(id: number, params: Record<string, any>) {
    return request.put(`recycle_quote_spider/category/${id}`, params, { showErrorMessage: true })
}

export function deleteQuoteCategory(id: number) {
    return request.delete(`recycle_quote_spider/category/${id}`, { showErrorMessage: true })
}

export function addQuoteCategory(params: Record<string, any>) {
    return request.post('recycle_quote_spider/category', params, { showErrorMessage: true })
}

export function getQuoteItemList(params: Record<string, any>) {
    return request.get('recycle_quote_spider/item', { params })
}

export function getQuoteItemFilterOptions(params: Record<string, any>) {
    return request.get('recycle_quote_spider/item/filter-options', { params })
}

export function editQuoteItem(id: number, params: Record<string, any>) {
    return request.put(`recycle_quote_spider/item/${id}`, params, { showErrorMessage: true })
}

export function addQuoteItem(params: Record<string, any>) {
    return request.post('recycle_quote_spider/item', params, { showErrorMessage: true })
}

export function deleteQuoteItem(id: number) {
    return request.delete(`recycle_quote_spider/item/${id}`, { showErrorMessage: true })
}

export function getQuoteRowList(params: Record<string, any>) {
    return request.get('recycle_quote_spider/row', { params })
}

export function editQuoteRow(id: number, params: Record<string, any>) {
    return request.put(`recycle_quote_spider/row/${id}`, params, { showErrorMessage: true })
}

export function addQuoteRow(params: Record<string, any>) {
    return request.post('recycle_quote_spider/row', params, { showErrorMessage: true })
}

export function deleteQuoteRow(id: number) {
    return request.delete(`recycle_quote_spider/row/${id}`, { showErrorMessage: true })
}

export function getQuoteRowPriceHistory(id: number, days: number) {
    return request.get(`recycle_quote_spider/row/${id}/price-history`, { params: { days } })
}

export function uploadQuoteExcel(formData: FormData) {
    return request.post('recycle_quote_spider/import/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        showErrorMessage: true
    })
}

export function previewQuoteExcel(params: Record<string, any>) {
    return request.post('recycle_quote_spider/import/preview', params, { showErrorMessage: true })
}

export function confirmQuoteExcel(params: Record<string, any>) {
    return request.post('recycle_quote_spider/import/confirm', params, { showErrorMessage: true })
}

export function batchConfirmQuoteExcel(params: Record<string, any>) {
    return request.post('recycle_quote_spider/import/batch-confirm', params, { showErrorMessage: true })
}

export function getQuoteSyncLogList(params: Record<string, any>) {
    return request.get('recycle_quote_spider/log', { params })
}
