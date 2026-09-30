import request from '@/utils/request'
export const getTierPricing = () => request.get('phone_shop/goods/tier_pricing')
export const saveTierPricing = (data: any) => request.post('phone_shop/goods/tier_pricing', data)
export const previewTierPricing = (base_price: number) => request.get('phone_shop/goods/tier_pricing/preview', { params: { base_price } })
