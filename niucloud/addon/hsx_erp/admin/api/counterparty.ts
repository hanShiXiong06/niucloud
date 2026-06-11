import request from '@/utils/request'

export function getErpCounterpartyList(params: Record<string, any>) {
    return request.get('erp/counterparty/lists', { params })
}

export function getErpCounterpartyOptions(params: Record<string, any> = {}) {
    return request.get('erp/counterparty/options', { params })
}

export function saveErpCounterparty(id: number, data: Record<string, any>) {
    return request.post(`erp/counterparty/save/${id}`, data)
}

export function getErpMemberOptions(params: Record<string, any> = {}) {
    return request.get('erp/counterparty/member_options', { params })
}

export function getErpCounterpartyMembers(id: number) {
    return request.get(`erp/counterparty/${id}/members`)
}
