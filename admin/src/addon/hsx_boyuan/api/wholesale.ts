import request from '@/utils/request'

export const listOutbound = (page: number, keyword: string, status: string) =>
    request.get('boyuan/tasks', { params: { type: 'wholesale_review', page, limit: 15, keyword, status } })
export const getOutbound = (id: number) => request.get(`boyuan/tasks/${id}`, { params: { type: 'wholesale_review' } })
export const prepareOutbound = (saleId: number, saleNo: string) => request.post(`boyuan/wholesale-source/${saleId}`, { sale_no: saleNo })
export const confirmOutbound = (id: number, data: any) => request.post(`boyuan/wholesale/${id}/submit`, data)
export const verifyOutbound = (id: number, data: any) => request.post(`boyuan/wholesale/${id}/verify`, data)
export const searchCustomers = (key: string, keyword: string) => request.post('boyuan/reference-search', {
    expected_connection_key: key, resource: 'parties', role: 'customer', keyword, enabled_only: true, cached: true, limit: 30
})
export const searchCreators = (key: string, company: string, keyword: string) => request.post('boyuan/reference-search', {
    expected_connection_key: key, resource: 'staff', company_id: company, keyword, enabled_only: true, cached: true, limit: 30
})
