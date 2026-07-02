import request from '@/utils/request'

export function getErpPurchaseList(params: Record<string, any>) {
    return request.get('erp/purchase/lists', { params })
}

export function getErpPurchaseInfo(id: number) {
    return request.get(`erp/purchase/${id}`)
}

export function createErpPurchase(data: Record<string, any>) {
    return request.post('erp/purchase/create', data)
}

export function adjustErpPurchaseCost(itemId: number, data: Record<string, any>) {
    return request.post(`erp/purchase/item/${itemId}/adjust_cost`, data)
}

export function getErpStaffOptions(params: Record<string, any> = {}) {
    return request.get('erp/staff/options', { params })
}

export function getErpPayableList(params: Record<string, any>) {
    return request.get('erp/finance/payable/lists', { params })
}

export function confirmErpPayment(id: number, data: Record<string, any>) {
    return request.post(`erp/finance/payable/${id}/confirm_payment`, data)
}

export function confirmErpPartyPayment(partyId: number, data: Record<string, any>) {
    return request.post(`erp/finance/payable/party/${partyId}/confirm_payment`, data)
}

export function confirmErpPayableItemsPayment(partyId: number, data: Record<string, any>) {
    return request.post(`erp/finance/payable/party/${partyId}/confirm_items_payment`, data)
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

export function getErpSaleList(params: Record<string, any>) {
    return request.get('erp/sale/lists', { params })
}

export function getErpSaleInfo(id: number) {
    return request.get(`erp/sale/${id}`)
}

export function createErpSale(data: Record<string, any>) {
    return request.post('erp/sale/create', data)
}

export function getErpReceivableList(params: Record<string, any>) {
    return request.get('erp/finance/receivable/lists', { params })
}

export function confirmErpReceipt(id: number, data: Record<string, any>) {
    return request.post(`erp/finance/receivable/${id}/confirm_receipt`, data)
}
