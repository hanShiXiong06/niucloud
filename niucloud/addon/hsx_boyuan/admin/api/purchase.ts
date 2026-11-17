import request from '@/utils/request'

export const getConfig = () => request.get('boyuan/config')
export const capturePurchaseSource = (id: number) => request.post(`boyuan/purchase-source/${id}`, {})
export const getPurchase = (id: number) => request.get(`boyuan/purchases/${id}`)
export const savePurchaseDraft = (id: number, data: any) => request.post(`boyuan/purchases/${id}/draft`, data)
export const submitPurchase = (id: number, data: any) => request.post(`boyuan/purchases/${id}/submit`, data)
export const verifyPurchase = (id: number, data: any) => request.post(`boyuan/purchases/${id}/verify`, data)
export const getReferences = (data: any) => request.post('boyuan/reference-search', data)
