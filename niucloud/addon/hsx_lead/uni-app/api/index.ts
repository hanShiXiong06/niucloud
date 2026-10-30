import request from '@/utils/request'

const root = 'hsx_lead/merchant'
const quiet = { showErrorMessage: false }
export const getMerchantWorkbench = (params: Record<string, any> = {}) => request.get(`${root}/workbench`, params, quiet)
export const getMerchantTask = (id: number) => request.get(`${root}/tasks/${id}`, {}, quiet)
export const merchantTaskAction = (id: number, action: 'accept' | 'reject' | 'follow' | 'deal' | 'close', data: Record<string, any> = {}) => request.post(`${root}/tasks/${id}/${action}`, data, quiet)
