import request from '@/utils/request'

export const getMemberCardDashboard = (params: Record<string, any> = {}) => request.get('member_card/dashboard', { params })
export const getMemberCardConfig = () => request.get('member_card/config')
export const saveMemberCardConfig = (data: Record<string, any>) => request.post('member_card/config', data)
export const getMemberCardDicts = () => request.get('member_card/dicts')

export const getCardProducts = (params: Record<string, any> = {}) => request.get('member_card/product/lists', { params })
export const getCardProductOptions = () => request.get('member_card/product/options')
export const getCardProduct = (id: number) => request.get(`member_card/product/${id}`)
export const saveCardProduct = (data: Record<string, any>, id = 0) => request.post(id ? `member_card/product/save/${id}` : 'member_card/product/save', data)
export const enableCardProduct = (id: number) => request.post(`member_card/product/${id}/enable`)
export const disableCardProduct = (id: number) => request.post(`member_card/product/${id}/disable`)
export const deleteCardProduct = (id: number) => request.delete(`member_card/product/${id}`)

export const getCardMemberOptions = (params: Record<string, any> = {}) => request.get('member_card/member/options', { params })
export const quickCreateCardMember = (data: Record<string, any>) => request.post('member_card/member/quick_create', data)
export const getMemberCards = (memberId: number) => request.get(`member_card/member/${memberId}/cards`)

export const getCardOrders = (params: Record<string, any> = {}) => request.get('member_card/order/lists', { params })
export const getCardOrder = (id: number) => request.get(`member_card/order/${id}`)
export const createCardOrder = (data: Record<string, any>) => request.post('member_card/order/create', data)
export const retryCardOrderFinance = (id: number, data: Record<string, any> = {}) => request.post(`member_card/order/${id}/retry_finance`, data)
export const cancelCardOrder = (id: number, reason: string) => request.post(`member_card/order/${id}/cancel`, { reason })
export const applyCardRefund = (id: number, data: Record<string, any>) => request.post(`member_card/order/${id}/refund/apply`, data)

export const searchMemberCards = (params: Record<string, any>) => request.get('member_card/card/search', { params })
export const getMemberCard = (id: number) => request.get(`member_card/card/${id}`)
export const redeemMemberCard = (id: number, data: Record<string, any>) => request.post(`member_card/card/${id}/redeem`, data)
export const freezeMemberCard = (id: number, reason: string) => request.post(`member_card/card/${id}/freeze`, { reason })
export const unfreezeMemberCard = (id: number) => request.post(`member_card/card/${id}/unfreeze`)

export const getCardRedemptions = (params: Record<string, any> = {}) => request.get('member_card/redemption/lists', { params })
export const getCardRedemption = (id: number) => request.get(`member_card/redemption/${id}`)
export const reverseCardRedemption = (id: number, data: Record<string, any>) => request.post(`member_card/redemption/${id}/reverse`, data)

export const getCardRefunds = (params: Record<string, any> = {}) => request.get('member_card/refund/lists', { params })
export const getCardRefund = (id: number) => request.get(`member_card/refund/${id}`)
export const retryCardRefundFinance = (id: number, data: Record<string, any> = {}) => request.post(`member_card/refund/${id}/retry_finance`, data)

export const memberCardRequestId = (prefix: string) => `${prefix}:${Date.now().toString(36)}:${Math.random().toString(36).slice(2, 12)}`
