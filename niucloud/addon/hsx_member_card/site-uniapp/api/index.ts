import request from '@/utils/request'

export const memberCardRequestId = (prefix: string) =>
    `${prefix}:${Date.now().toString(36)}:${Math.random().toString(36).slice(2, 12)}`
export const getMemberCardDashboard = (params: Record<string, any> = {}) =>
    request.get('member_card/dashboard', params, { showLoading: false, showErrorMessage: false })
export const getMemberCardConfig = () =>
    request.get('member_card/config', {}, { showLoading: false, showErrorMessage: false })
export const saveMemberCardConfig = (data: Record<string, any>) =>
    request.post('member_card/config', data, { showLoading: false, showErrorMessage: false })
export const saveMemberCardCapitalAccount = (data: Record<string, any>) =>
    request.post('member_card/config/capital_account', data, { showLoading: false, showErrorMessage: false })
export const deleteMemberCardCapitalAccount = (id: number) =>
    request.delete(`member_card/config/capital_account/${id}`, {}, { showLoading: false, showErrorMessage: false })
export const getCardProducts = (params: Record<string, any> = {}) =>
    request.get('member_card/product/lists', params, { showLoading: false, showErrorMessage: false })
export const getCardProductOptions = () =>
    request.get('member_card/product/options', {}, { showLoading: false, showErrorMessage: false })
export const getCardProduct = (id: number) =>
    request.get(`member_card/product/${id}`, {}, { showLoading: false, showErrorMessage: false })
export const saveCardProduct = (data: Record<string, any>, id = 0) =>
    request.post(id ? `member_card/product/save/${id}` : 'member_card/product/save', data, {
        showLoading: false,
        showErrorMessage: false
    })
export const enableCardProduct = (id: number) =>
    request.post(`member_card/product/${id}/enable`, {}, { showLoading: false, showErrorMessage: false })
export const disableCardProduct = (id: number) =>
    request.post(`member_card/product/${id}/disable`, {}, { showLoading: false, showErrorMessage: false })
export const deleteCardProduct = (id: number) =>
    request.delete(`member_card/product/${id}`, {}, { showLoading: false, showErrorMessage: false })
export const adjustCardProductStock = (id: number, data: Record<string, any>) =>
    request.post(`member_card/product/${id}/stock/adjust`, data, { showLoading: false, showErrorMessage: false })
export const getCardMembers = (params: Record<string, any> = {}) =>
    request.get('member_card/member/lists', params, { showLoading: false, showErrorMessage: false })
export const getCardMemberOptions = (params: Record<string, any> = {}) =>
    request.get('member_card/member/options', params, { showLoading: false, showErrorMessage: false })
export const getCardMember = (id: number) =>
    request.get(`member_card/member/${id}/info`, {}, { showLoading: false, showErrorMessage: false })
export const quickCreateCardMember = (data: Record<string, any>) =>
    request.post('member_card/member/quick_create', data, { showLoading: false, showErrorMessage: false })
export const createCardOrder = (data: Record<string, any>) =>
    request.post('member_card/order/create', data, { showLoading: false, showErrorMessage: false })
export const getCardOrders = (params: Record<string, any> = {}) =>
    request.get('member_card/order/lists', params, { showLoading: false, showErrorMessage: false })
export const getCardOrder = (id: number) =>
    request.get(`member_card/order/${id}`, {}, { showLoading: false, showErrorMessage: false })
export const retryCardOrderFinance = (id: number, data: Record<string, any> = {}) =>
    request.post(`member_card/order/${id}/retry_finance`, data, { showLoading: false, showErrorMessage: false })
export const cancelCardOrder = (id: number, reason: string) =>
    request.post(`member_card/order/${id}/cancel`, { reason }, { showLoading: false, showErrorMessage: false })
export const searchMemberCards = (params: Record<string, any>) =>
    request.get('member_card/card/search', params, { showLoading: false, showErrorMessage: false })
export const getMemberCard = (id: number) =>
    request.get(`member_card/card/${id}`, {}, { showLoading: false, showErrorMessage: false })
export const redeemMemberCard = (id: number, data: Record<string, any>) =>
    request.post(`member_card/card/${id}/redeem`, data, { showLoading: false, showErrorMessage: false })
export const getCardRedemptions = (params: Record<string, any> = {}) =>
    request.get('member_card/redemption/lists', params, { showLoading: false, showErrorMessage: false })
export const reverseCardRedemption = (id: number, data: Record<string, any>) =>
    request.post(`member_card/redemption/${id}/reverse`, data, { showLoading: false, showErrorMessage: false })
