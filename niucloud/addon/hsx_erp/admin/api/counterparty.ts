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
