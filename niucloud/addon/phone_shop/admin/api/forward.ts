import request from '@/utils/request'

export const getForwardApplicationPage = (params: Record<string, any>) => request.get('phone_shop/forward/application', { params })
export const getForwardApplicationInfo = (id: number) => request.get(`phone_shop/forward/application/${id}`)
export const reviewForwardApplication = (id: number, params: Record<string, any>) => request.put(`phone_shop/forward/application/${id}/review`, params, { showSuccessMessage: true })
