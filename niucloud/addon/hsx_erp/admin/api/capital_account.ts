import request from '@/utils/request'

export function getCapitalAccounts() {
    return request.get('erp/capital_account/lists')
}

export function saveCapitalAccount(id: number, data: Record<string, any>) {
    return request.post(`erp/capital_account/save/${id}`, data)
}

export function deleteCapitalAccount(id: number) {
    return request.delete(`erp/capital_account/${id}`)
}

export function recordCapitalEntry(data: Record<string, any>) {
    return request.post('erp/capital_account/entry', data)
}

export function getCapitalLedger(params: Record<string, any> = {}) {
    return request.get('erp/capital_account/ledger', { params })
}
