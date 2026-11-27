import request from '@/utils/request'

export function getErpCounterpartyOptions(params: Record<string, any> = {}) {
    return request.get('erp/counterparty/options', { params })
}

export function quickCreateErpContact(data: Record<string, any>) {
    return request.post('erp/counterparty/quick_contact', data)
}

export function getErpMemberOptions(params: Record<string, any> = {}) {
    return request.get('erp/counterparty/member_options', { params })
}

export function resolveErpContact(data: Record<string, any>) {
    return request.post('erp/counterparty/resolve_contact', data)
}
export function quickCreateErpParty(data: Record<string, any>) { return request.post('erp/counterparty/quick_party', data) }
export function getErpPartyCredit(id: number) { return request.get(`erp/counterparty/credit/${id}`) }
export function updateErpPartyCredit(id: number, data: Record<string, any>) { return request.post(`erp/counterparty/credit/${id}`, data) }
