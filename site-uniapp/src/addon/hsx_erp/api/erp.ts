import request from '@/utils/request'

function withErpRequestId(data: Record<string, any>, prefix: string) {
    const current = String(data?.request_id ?? '').trim()
    if (current) return data
    return {
        ...data,
        request_id: `${prefix}:${Date.now().toString(36)}:${Math.random().toString(36).slice(2, 12)}`
    }
}

// ─── 经营看板 ────────────────────────────────────────────────────────────────
export function getMobileErpDashboard(params: Record<string, any> = {}) {
    return request.get('erp/dashboard', params)
}
export function getMobileErpKpiDashboard(params: Record<string, any> = {}) { return request.get('erp/kpi/dashboard', params) }
export function dismissMobileErpRefurbishReminder(mode: 'today' | 'forever' = 'today') { return request.post('erp/config/refurbish_reminder/dismiss', { mode }) }
export function dismissMobileErpTurnoverReminder(mode: 'today' | 'forever' = 'today') { return request.post('erp/config/turnover_reminder/dismiss', { mode }) }
export function getMobileErpConfig() { return request.get('erp/config') }

export function getMobileOperatingFinanceList(params: Record<string, any> = {}) {
    return request.get('erp/operating_finance/lists', params)
}

export function createMobileOperatingFinance(data: Record<string, any>) {
    return request.post('erp/operating_finance/create', withErpRequestId(data, 'operating-finance'))
}

export function getMobileCounterpartyOptions(params: Record<string, any> = {}) {
    return request.get('erp/counterparty/options', params)
}

export function getMobileStaffOptions(params: Record<string, any> = {}) {
    return request.get('erp/staff/options', params)
}

export function getMobileErpGoodsMeta(params: Record<string, any> = {}) {
    return request.get('erp/goods/meta', params)
}

export function getMobileErpGoodsCatalogHierarchy(params: Record<string, any> = {}) {
    return request.get('erp/goods/catalog/hierarchy', params)
}

// ─── 采购 ────────────────────────────────────────────────────────────────────
export function getMobilePurchaseList(params: Record<string, any>) {
    return request.get('erp/purchase/lists', params)
}
export function getMobilePurchaseInfo(id: number) {
    return request.get(`erp/purchase/${id}`)
}
export function createMobileErpPurchase(data: Record<string, any>) {
    return request.post('erp/purchase/create', withErpRequestId(data, 'purchase'))
}
export function confirmMobilePurchasePayment(partyId: number, data: Record<string, any>) {
    return request.post(`erp/finance/payable/party/${partyId}/confirm_payment`, withErpRequestId(data, 'party-payment'))
}
export function getMobileErpQuantityProducts(params: Record<string, any> = {}) {
    return request.get('erp/stock/quantity_products', params)
}
export function createMobileErpQuantityProduct(data: Record<string, any>) {
    return request.post('erp/stock/quantity_product', data)
}
export function updateMobileErpQuantityProductCategory(id: number, data: Record<string, any>) {
    return request.post(`erp/stock/quantity_product/${id}/category`, data)
}

// ─── 销售 ────────────────────────────────────────────────────────────────────
export function getMobileSaleList(params: Record<string, any>) {
    return request.get('erp/sale/lists', params)
}
export function getMobileSaleInfo(id: number) {
    return request.get(`erp/sale/${id}`)
}
export function createMobileSale(data: Record<string, any>) {
    return request.post('erp/sale/create', withErpRequestId(data, 'sale'))
}
export function cancelMobileSale(id: number, data: Record<string, any> = {}) {
    return request.post(`erp/sale/${id}/cancel`, data)
}
export function cancelMobileSaleItem(itemId: number, data: Record<string, any> = {}) {
    return request.post(`erp/sale/item/${itemId}/cancel`, data)
}
export function confirmMobileSaleReceipt(id: number, data: Record<string, any>) {
    return request.post(`erp/finance/receivable/${id}/confirm_receipt`, withErpRequestId(data, 'receipt'))
}
export function getErpSaleChannels() {
    return request.get('erp/config/sale_channels')
}
export function getErpSaleChannelOptions() {
    return request.get('erp/config/sale_channel_options')
}
export function getErpBusinessSourceOptions() {
    return request.get('erp/config/business_source_options')
}
export function getErpFinanceCategories() {
    return request.get('erp/config/finance_categories')
}
export function saveErpSaleChannels(channels: string[]) {
    return request.post('erp/config/sale_channels', { channels })
}

// ─── 库存 ────────────────────────────────────────────────────────────────────
export function getMobileStockList(params: Record<string, any>) {
    return request.get('erp/stock/lists', params)
}
export function getMobileStockTurnoverSummary() {
    return request.get('erp/stock/turnover_summary')
}
export function getMobileStockListingWorkload() {
    return request.get('erp/stock/listing_workload')
}

export function getMobileStocktakeList(params: Record<string, any> = {}) { return request.get('erp/stocktake/lists', params) }
export function getMobileStocktakeInfo(id: number) { return request.get(`erp/stocktake/${id}`) }
export function getMobileStocktakeItems(id: number, params: Record<string, any> = {}) { return request.get(`erp/stocktake/${id}/items`, params) }
export function createMobileStocktake(data: Record<string, any>) { return request.post('erp/stocktake/create', withErpRequestId(data, 'stocktake')) }
export function scanMobileStocktake(id: number, data: Record<string, any>) { return request.post(`erp/stocktake/${id}/scan`, data) }
export function submitMobileStocktake(id: number, autoComplete = false) { return request.post(`erp/stocktake/${id}/submit`, { auto_complete: autoComplete }) }
export function resolveMobileStocktakeItem(id: number, itemId: number, data: Record<string, any>) { return request.post(`erp/stocktake/${id}/items/${itemId}/resolve`, data) }
export function completeMobileStocktake(id: number, remark = '') { return request.post(`erp/stocktake/${id}/complete`, { remark }) }
export function cancelMobileStocktake(id: number, remark: string) { return request.post(`erp/stocktake/${id}/cancel`, { remark }) }

export function adjustMobileStockRetailPrice(id: number, data: Record<string, any>) {
    return request.post(`erp/stock/${id}/retail_price`, withErpRequestId(data, `stock-retail-price-${id}`))
}

export function transferMobileStock(data: Record<string, any>) {
    return request.post('erp/stock/transfer', withErpRequestId(data, 'stock-transfer'))
}
export function previewMobileStockTransfer(data: Record<string, any>) {
    return request.post('erp/stock/transfer/preview', data)
}
export function buyoutMobileConsignment(data: Record<string, any>) {
    return request.post('erp/stock/consignment/buyout', withErpRequestId(data, 'consignment-buyout'))
}
export function getMobileSerialTraceList(params: Record<string, any>) {
    return request.get('erp/stock/serial_trace', params)
}
export function getMobileSerialTraceDetail(id: number) {
    return request.get(`erp/stock/serial_trace/${id}`)
}
export function getMobileStockInfo(id: number) {
    return request.get(`erp/stock/${id}`)
}
export function syncMobileStockListing(id: number) {
    return request.post(`erp/stock/${id}/sync_listing`)
}
export function handoffMobileStockListing(id: number) {
    return request.post(`erp/stock/${id}/handoff_listing`)
}
export function prepareMobileStockListingMedia(id: number) {
    return request.post(`erp/stock/${id}/listing_media/prepare`)
}
export function updateMobileStockFlow(id: number, data: Record<string, any>) {
    return request.post(`erp/stock/${id}/flow`, data)
}
export function getMobileSaleStock(params: Record<string, any> = {}) {
    return request.get('erp/sale/stock', params)
}

// ─── 应付款 ──────────────────────────────────────────────────────────────────
export function getMobilePayableList(params: Record<string, any>) {
    return request.get('erp/finance/payable/lists', params)
}
export function getMobilePayableInfo(id: number | string) {
    return request.get(`erp/finance/payable/info/${id}`)
}
export function getMobilePayablePartyItems(partyId: number, params: Record<string, any>) {
    return request.get(`erp/finance/payable/party/${partyId}/items`, params)
}
export function confirmMobilePayableItems(partyId: number, data: Record<string, any>) {
    return request.post(`erp/finance/payable/party/${partyId}/confirm_items_payment`, withErpRequestId(data, 'items-payment'))
}
export function confirmMobileOffset(data: Record<string, any>) {
    return request.post('erp/finance/offset', withErpRequestId(data, 'offset'))
}

// ─── 应收款 ──────────────────────────────────────────────────────────────────
export function getMobileReceivableList(params: Record<string, any>) {
    return request.get('erp/finance/receivable/lists', params)
}
export function getMobileReceivableInfo(id: number) {
    return request.get(`erp/finance/receivable/${id}`)
}
export function getMobileReceivableItems(id: number) {
    return request.get(`erp/finance/receivable/${id}/items`)
}

// ─── 采购退货 ────────────────────────────────────────────────────────────────
export function getMobilePurchaseReturnList(params: Record<string, any>) {
    return request.get('erp/purchase/return/lists', params)
}
export function createErpPurchaseReturn(data: Record<string, any>) {
    return request.post('erp/purchase/return/create', withErpRequestId(data, 'purchase-return'))
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
    return request.post('erp/sale/return/create', withErpRequestId(data, 'sale-return'))
}
export function createAndConfirmErpSaleReturn(data: Record<string, any>) {
    return request.post('erp/sale/return/create_and_confirm', withErpRequestId(data, 'sale-return-direct'))
}
export function createMobileSaleCompensation(data: Record<string, any>) {
    return request.post('erp/sale/return/compensate', withErpRequestId(data, 'sale-compensation'))
}
export function getMobileSaleReturnInfo(id: number) {
    return request.get(`erp/sale/return/${id}`)
}
export function confirmMobileSaleReturn(id: number, data: Record<string, any> = {}) {
    return request.post(`erp/sale/return/${id}/confirm`, data)
}
export function cancelMobileSaleReturn(id: number, data: Record<string, any> = {}) {
    return request.post(`erp/sale/return/${id}/cancel`, data)
}

// ─── 资金账户 ────────────────────────────────────────────────────────────────
export function getMobileCapitalAccounts() {
    return request.get('erp/capital_account/lists', { page: 1, limit: 50 })
}

// ─── 打印中心 ────────────────────────────────────────────────────────────────
export function getMobileErpPrintMeta() {
    return request.get('erp/print/meta')
}
export function getMobileErpPrinters() {
    return request.get('erp/print/printers')
}
export function getMobileErpPrintJobs(params: Record<string, any> = {}) {
    return request.get('erp/print/jobs', params)
}
export function testMobileErpPrinter(id: number) {
    return request.post(`erp/print/printer/${id}/test`)
}
export function retryMobileErpPrintJob(id: number) {
    return request.post(`erp/print/job/${id}/retry`)
}
export function completeMobileErpPrintJob(id: number, success: boolean, message = '') {
    return request.post(`erp/print/job/${id}/client_complete`, { success: success ? 1 : 0, message })
}
export function printMobileErpAssetLabel(assetId: number) {
    return request.post('erp/print/manual', { scene_key: 'asset_label', biz_type: 'asset', biz_id: assetId })
}
export function printMobileErpSaleReceipt(saleOrderId: number) {
    return request.post('erp/print/manual', { scene_key: 'sale_created', biz_type: 'sale', biz_id: saleOrderId })
}
