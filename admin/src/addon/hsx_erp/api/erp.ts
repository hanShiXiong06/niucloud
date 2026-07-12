import request from '@/utils/request'

export function getErpOperatingFinanceList(params: Record<string, any> = {}) {
    return request.get('erp/operating_finance/lists', { params })
}

export function createErpOperatingFinance(data: Record<string, any>) {
    return request.post('erp/operating_finance/create', data)
}

function withErpRequestId(data: Record<string, any>, prefix: string) {
    const current = String(data?.request_id ?? '').trim()
    if (current) return data
    return {
        ...data,
        request_id: `${prefix}:${Date.now().toString(36)}:${Math.random().toString(36).slice(2, 12)}`
    }
}

export function getErpDicts() {
    return request.get('erp/dicts')
}

export function getErpDashboard(params: Record<string, any> = {}) {
    return request.get('erp/dashboard', { params })
}
export function getErpKpiDashboard(params: Record<string, any> = {}) { return request.get('erp/kpi/dashboard', { params }) }
export function getErpKpiRules() { return request.get('erp/kpi/rules') }
export function saveErpKpiRules(rules: any[]) { return request.post('erp/kpi/rules', { rules }) }

export function getErpPurchaseList(params: Record<string, any>) {
    return request.get('erp/purchase/lists', { params })
}

export function getErpPurchaseInfo(id: number) {
    return request.get(`erp/purchase/${id}`)
}

export function createErpPurchase(data: Record<string, any>) {
    return request.post('erp/purchase/create', withErpRequestId(data, 'purchase'))
}

export function cancelErpPurchase(id: number, data: Record<string, any>) {
    return request.post(`erp/purchase/${id}/cancel`, data)
}

export function adjustErpPurchaseCost(itemId: number, data: Record<string, any>) {
    return request.post(`erp/purchase/item/${itemId}/adjust_cost`, withErpRequestId(data, 'cost-adjust'))
}

export function getErpStaffOptions(params: Record<string, any> = {}) {
    return request.get('erp/staff/options', { params })
}

// ── 商品资料 ─────────────────────────────────────────────────────────────────
export function getErpGoodsCategoryTree(params: Record<string, any> = {}) {
    return request.get('erp/goods/category/tree', { params })
}

export function getErpCategorySyncStatus() {
    return request.get('erp/goods/category/sync/status')
}

export function syncErpCategories(data: Record<string, any>) {
    return request.post('erp/goods/category/sync', data)
}

export function getErpGoodsCategoryList(params: Record<string, any> = {}) {
    return request.get('erp/goods/category/lists', { params })
}

export function saveErpGoodsCategory(id: number, data: Record<string, any>) {
    return request.post(`erp/goods/category/save/${id}`, data)
}

export function deleteErpGoodsCategory(id: number) {
    return request.delete(`erp/goods/category/${id}`)
}

export function getErpGoodsSpecMeta() {
    return request.get('erp/goods/spec/meta')
}

export function saveErpGoodsSpecGroup(id: number, data: Record<string, any>) {
    return request.post(`erp/goods/spec/group/save/${id}`, data)
}

export function deleteErpGoodsSpecGroup(id: number) {
    return request.delete(`erp/goods/spec/group/${id}`)
}

export function saveErpGoodsSpecItem(id: number, data: Record<string, any>) {
    return request.post(`erp/goods/spec/item/save/${id}`, data)
}

export function deleteErpGoodsSpecItem(id: number) {
    return request.delete(`erp/goods/spec/item/${id}`)
}

export function saveErpGoodsGrade(id: number, data: Record<string, any>) {
    return request.post(`erp/goods/grade/save/${id}`, data)
}

export function deleteErpGoodsGrade(id: number) {
    return request.delete(`erp/goods/grade/${id}`)
}

export function getErpPayableList(params: Record<string, any>) {
    return request.get('erp/finance/payable/lists', { params })
}

export function confirmErpPayment(id: number, data: Record<string, any>) {
    return request.post(`erp/finance/payable/${id}/confirm_payment`, withErpRequestId(data, 'payment'))
}

export function confirmErpPartyPayment(partyId: number, data: Record<string, any>) {
    return request.post(`erp/finance/payable/party/${partyId}/confirm_payment`, withErpRequestId(data, 'party-payment'))
}

export function confirmErpPayableItemsPayment(partyId: number, data: Record<string, any>) {
    return request.post(`erp/finance/payable/party/${partyId}/confirm_items_payment`, withErpRequestId(data, 'items-payment'))
}

export function getErpPayablePartyItems(partyId: number, params: Record<string, any>) {
    return request.get(`erp/finance/payable/party/${partyId}/items`, { params })
}

export function getErpAccountLedger(params: Record<string, any>) {
    return request.get('erp/finance/account_ledger', { params })
}

export function getErpMoneyLedger(params: Record<string, any>) {
    return request.get('erp/finance/money_ledger', { params })
}

export function getErpSaleStock(params: Record<string, any>) {
    return request.get('erp/sale/stock', { params })
}

export function getErpStockList(params: Record<string, any>) {
    return request.get('erp/stock/lists', { params })
}
export function getErpSerialTraceList(params: Record<string, any>) { return request.get('erp/stock/serial_trace', { params }) }

export function getErpStockLedger(params: Record<string, any>) {
    return request.get('erp/stock/ledger', { params })
}

export function getErpStockInfo(id: number) {
    return request.get(`erp/stock/${id}`)
}

export function adjustErpStockCost(id: number, data: Record<string, any>) {
    return request.post(`erp/stock/${id}/adjust_cost`, withErpRequestId(data, 'stock-cost'))
}

export function sendErpStockRefurbish(data: Record<string, any>) {
    return request.post('erp/stock/refurbish/send', withErpRequestId(data, 'refurbish-send'))
}

export function completeErpStockRefurbish(id: number, data: Record<string, any>) {
    return request.post(`erp/stock/${id}/refurbish/complete`, withErpRequestId(data, 'refurbish-complete'))
}

export function updateErpStockFlow(id: number, data: Record<string, any>) {
    return request.post(`erp/stock/${id}/flow`, data)
}

export function syncErpStockListing(id: number) {
    return request.post(`erp/stock/${id}/sync_listing`)
}

export function getErpSaleList(params: Record<string, any>) {
    return request.get('erp/sale/lists', { params })
}

export function getErpSaleInfo(id: number) {
    return request.get(`erp/sale/${id}`)
}

export function createErpSale(data: Record<string, any>) {
    return request.post('erp/sale/create', withErpRequestId(data, 'sale'))
}

export function cancelErpSale(id: number, data: Record<string, any>) {
    return request.post(`erp/sale/${id}/cancel`, data)
}

export function cancelErpSaleItem(itemId: number, data: Record<string, any>) {
    return request.post(`erp/sale/item/${itemId}/cancel`, data)
}

export function getErpReceivableList(params: Record<string, any>) {
    return request.get('erp/finance/receivable/lists', { params })
}

export function getErpReceivableInfo(id: number) {
    return request.get(`erp/finance/receivable/${id}`)
}

export function getErpReceivableItems(id: number) {
    return request.get(`erp/finance/receivable/${id}/items`)
}

export function confirmErpReceipt(id: number, data: Record<string, any>) {
    return request.post(`erp/finance/receivable/${id}/confirm_receipt`, withErpRequestId(data, 'receipt'))
}

export function confirmErpOffset(data: Record<string, any>) {
    return request.post('erp/finance/offset', withErpRequestId(data, 'offset'))
}

export function getErpSettlementList(params: Record<string, any>) {
    return request.get('erp/finance/settlement_lists', { params })
}

// ── 采购退货 ─────────────────────────────────────────────────────────────────
export function getErpPurchaseReturnList(params: Record<string, any>) {
    return request.get('erp/purchase/return/lists', { params })
}

export function getErpPurchaseReturnInfo(id: number) {
    return request.get(`erp/purchase/return/${id}`)
}

export function createErpPurchaseReturn(data: Record<string, any>) {
    return request.post('erp/purchase/return/create', withErpRequestId(data, 'purchase-return'))
}

export function confirmErpPurchaseReturn(id: number, data: Record<string, any> = {}) {
    return request.post(`erp/purchase/return/${id}/confirm`, data)
}

export function cancelErpPurchaseReturn(id: number, data: Record<string, any> = {}) {
    return request.post(`erp/purchase/return/${id}/cancel`, data)
}

// ── 销售退货 ─────────────────────────────────────────────────────────────────
export function getErpSaleReturnList(params: Record<string, any>) {
    return request.get('erp/sale/return/lists', { params })
}

export function getErpSaleReturnInfo(id: number) {
    return request.get(`erp/sale/return/${id}`)
}

export function createErpSaleReturn(data: Record<string, any>) {
    return request.post('erp/sale/return/create', withErpRequestId(data, 'sale-return'))
}
export function createErpSaleCompensation(data: Record<string, any>) { return request.post('erp/sale/return/compensate', withErpRequestId(data, 'sale-compensation')) }

export function confirmErpSaleReturn(id: number, data: Record<string, any> = {}) {
    return request.post(`erp/sale/return/${id}/confirm`, data)
}

export function cancelErpSaleReturn(id: number, data: Record<string, any> = {}) {
    return request.post(`erp/sale/return/${id}/cancel`, data)
}
