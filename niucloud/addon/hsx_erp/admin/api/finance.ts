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
// 一组对接人(主体)的待结算应付/应收(主体级折账)
export function getFinanceGroupOutstanding(memberIds: number[]) {
    return request.get('erp/finance/group_outstanding', { params: { member_ids: memberIds } })
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
// 财务汇总(应收/应付净额 + 各资金账户余额)
export function getFinanceSummary() {
    return request.get('erp/finance/summary')
}
// 结算记录(已结清历史)
export function getFinanceSettlementList(params: Record<string, any>) {
    return request.get('erp/finance/settlement/lists', { params })
}
// 经营支出快捷记账
export function recordFinanceExpense(data: Record<string, any>) {
    return request.post('erp/finance/expense', data)
}
