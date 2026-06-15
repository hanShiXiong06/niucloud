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

// 主体详情(信息+对接人+财务对账)，供财务中心抽屉
export function getErpCounterpartyDetail(id: number) {
    return request.get(`erp/counterparty/${id}/detail`)
}

export function addErpCounterpartyMember(id: number, data: Record<string, any>) {
    return request.post(`erp/counterparty/${id}/member/add`, data)
}

export function removeErpCounterpartyMember(id: number, memberId: number) {
    return request.post(`erp/counterparty/${id}/member/remove`, { member_id: memberId })
}

export function deleteErpCounterparty(id: number) {
    return request.delete(`erp/counterparty/${id}`)
}
