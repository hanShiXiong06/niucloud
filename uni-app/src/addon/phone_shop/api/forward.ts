import request from '@/utils/request'

export const getGoodsForwardAccess = () => request.get('phone_shop/forward/access')
export const applyGoodsForward = (params: Record<string, any>) => request.post('phone_shop/forward/apply', params)
