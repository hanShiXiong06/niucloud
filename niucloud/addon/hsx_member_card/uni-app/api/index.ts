import request from '@/utils/request'

export function getMemberCardOverview() {
    return request.get('member_card/member/overview')
}

export function getMyMemberCards(params: Record<string, any>) {
    return request.get('member_card/member/cards', params)
}

export function getMyMemberCardDetail(id: number) {
    return request.get(`member_card/member/cards/${id}`)
}

export function getMyMemberCardOrders(params: Record<string, any>) {
    return request.get('member_card/member/orders', params)
}

export function getMyMemberCardRedemptions(params: Record<string, any>) {
    return request.get('member_card/member/redemptions', params)
}
