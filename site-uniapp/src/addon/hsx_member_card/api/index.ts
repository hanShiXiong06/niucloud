import request from '@/utils/request'

export const memberCardRequestId = (prefix: string) => `${prefix}:${Date.now().toString(36)}:${Math.random().toString(36).slice(2, 12)}`
export const getMemberCardDashboard = (params: Record<string, any> = {}) => request.get('member_card/dashboard', params)
export const getMemberCardConfig = () => request.get('member_card/config')
export const getCardProductOptions = () => request.get('member_card/product/options')
export const getCardMemberOptions = (params: Record<string, any> = {}) => request.get('member_card/member/options', params, { showLoading: false })
export const quickCreateCardMember = (data: Record<string, any>) => request.post('member_card/member/quick_create', data)
export const createCardOrder = (data: Record<string, any>) => request.post('member_card/order/create', data)
export const getCardOrders = (params: Record<string, any> = {}) => request.get('member_card/order/lists', params)
export const getCardOrder = (id: number) => request.get(`member_card/order/${id}`)
export const retryCardOrderFinance = (id: number, data: Record<string, any> = {}) => request.post(`member_card/order/${id}/retry_finance`, data)
export const cancelCardOrder = (id: number, reason: string) => request.post(`member_card/order/${id}/cancel`, { reason })
export const searchMemberCards = (params: Record<string, any>) => request.get('member_card/card/search', params)
export const getMemberCard = (id: number) => request.get(`member_card/card/${id}`)
export const redeemMemberCard = (id: number, data: Record<string, any>) => request.post(`member_card/card/${id}/redeem`, data)
export const getCardRedemptions = (params: Record<string, any> = {}) => request.get('member_card/redemption/lists', params)
export const reverseCardRedemption = (id: number, data: Record<string, any>) => request.post(`member_card/redemption/${id}/reverse`, data)
