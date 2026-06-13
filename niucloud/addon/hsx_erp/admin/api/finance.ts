import request from '@/utils/request'

// 往来单位余额看板(应付/应收/可折账/净额)
export function getFinanceBalanceBoard() {
    return request.get('erp/finance/board')
}
// 某往来单位待结算应付
export function getFinancePayableOutstanding(counterpartyId: number) {
    return request.get('erp/finance/payable/outstanding', { params: { counterparty_id: counterpartyId } })
}
// 某往来单位待结算应收
export function getFinanceReceivableOutstanding(counterpartyId: number) {
    return request.get('erp/finance/receivable/outstanding', { params: { counterparty_id: counterpartyId } })
}
// 结算预演(只算不写)
export function previewFinanceSettlement(data: Record<string, any>) {
    return request.post('erp/finance/settlement/preview', data)
}
// 确认结算(现金/折账/混合)
export function settleFinance(data: Record<string, any>) {
    return request.post('erp/finance/settlement/settle', data)
}
// 应付/应收列表
export function getFinancePayableList(params: Record<string, any>) {
    return request.get('erp/finance/payable/lists', { params })
}
export function getFinanceReceivableList(params: Record<string, any>) {
    return request.get('erp/finance/receivable/lists', { params })
}
