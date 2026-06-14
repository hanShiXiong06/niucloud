import request from '@/utils/request'

// 账户列表
export function getCapitalAccounts() {
    return request.get('erp/capital_account/lists')
}
// 新建/编辑账户
export function saveCapitalAccount(id: number, data: Record<string, any>) {
    return request.post(`erp/capital_account/save/${id}`, data)
}
// 删除账户
export function deleteCapitalAccount(id: number) {
    return request.delete(`erp/capital_account/${id}`)
}
// 手工记一笔收/付
export function recordCapitalEntry(data: Record<string, any>) {
    return request.post('erp/capital_account/entry', data)
}
// 账目往来流水
export function getCapitalLedger(params: Record<string, any> = {}) {
    return request.get('erp/capital_account/ledger', { params })
}
