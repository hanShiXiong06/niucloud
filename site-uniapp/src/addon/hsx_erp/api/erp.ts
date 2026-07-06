import request from '@/utils/request'

// ─── 采购 ────────────────────────────────────────────────────────────────────
export function getMobilePurchaseList(params: Record<string, any>) {
    return request.get('erp/purchase/lists', params)
}
export function getMobilePurchaseInfo(id: number) {
    return request.get(`erp/purchase/${id}`)
}
export function confirmMobilePurchasePayment(partyId: number, data: Record<string, any>) {
    return request.post(`erp/finance/payable/party/${partyId}/confirm_payment`, data)
}

// ─── 销售 ────────────────────────────────────────────────────────────────────
export function getMobileSaleList(params: Record<string, any>) {
    return request.get('erp/sale/lists', params)
}
export function getMobileSaleInfo(id: number) {
    return request.get(`erp/sale/${id}`)
}
export function cancelMobileSale(id: number, data: Record<string, any> = {}) {
    return request.post(`erp/sale/${id}/cancel`, data)
}
export function confirmMobileSaleReceipt(id: number, data: Record<string, any>) {
    return request.post(`erp/finance/receivable/${id}/confirm_receipt`, data)
}
export function getErpSaleChannels() {
    return request.get('erp/config/sale_channels')
}
export function saveErpSaleChannels(channels: string[]) {
    return request.post('erp/config/sale_channels', { channels })
}

// ─── 库存 ────────────────────────────────────────────────────────────────────
export function getMobileStockList(params: Record<string, any>) {
    return request.get('erp/stock/lists', params)
}
export function getMobileStockInfo(id: number) {
    return request.get(`erp/stock/${id}`)
}

// ─── 应付款 ──────────────────────────────────────────────────────────────────
export function getMobilePayableList(params: Record<string, any>) {
    return request.get('erp/finance/payable/lists', params)
}
export function getMobilePayablePartyItems(partyId: number, params: Record<string, any>) {
    return request.get(`erp/finance/payable/party/${partyId}/items`, params)
}
export function confirmMobilePayableItems(partyId: number, data: Record<string, any>) {
    return request.post(`erp/finance/payable/party/${partyId}/confirm_items_payment`, data)
}

// ─── 应收款 ──────────────────────────────────────────────────────────────────
export function getMobileReceivableList(params: Record<string, any>) {
    return request.get('erp/finance/receivable/lists', params)
}
export function getMobileReceivableItems(id: number) {
    return request.get(`erp/finance/receivable/${id}/items`)
}

// ─── 采购退货 ────────────────────────────────────────────────────────────────
export function getMobilePurchaseReturnList(params: Record<string, any>) {
    return request.get('erp/purchase/return/lists', params)
}
export function createErpPurchaseReturn(data: Record<string, any>) {
    return request.post('erp/purchase/return/create', data)
}
export function confirmMobilePurchaseReturn(id: number, data: Record<string, any> = {}) {
    return request.post(`erp/purchase/return/${id}/confirm`, data)
}
export function cancelMobilePurchaseReturn(id: number) {
    return request.post(`erp/purchase/return/${id}/cancel`, {})
}

// ─── 销售退货 ────────────────────────────────────────────────────────────────
export function getMobileSaleReturnList(params: Record<string, any>) {
    return request.get('erp/sale/return/lists', params)
}
export function createErpSaleReturn(data: Record<string, any>) {
    return request.post('erp/sale/return/create', data)
}
export function confirmMobileSaleReturn(id: number, data: Record<string, any> = {}) {
    return request.post(`erp/sale/return/${id}/confirm`, data)
}
export function cancelMobileSaleReturn(id: number) {
    return request.post(`erp/sale/return/${id}/cancel`, {})
}

// ─── 资金账户 ────────────────────────────────────────────────────────────────
export function getMobileCapitalAccounts() {
    return request.get('erp/capital_account/lists', { page: 1, limit: 50 })
}
