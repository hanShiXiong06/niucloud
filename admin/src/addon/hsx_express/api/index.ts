import request from '@/utils/request'

// 业务错误由页面以纯文本解释，避免全局提示与页面提示重复。
const readOptions = { showErrorMessage: false, timeout: 15000 }
const actionOptions = { showErrorMessage: false, timeout: 60000 }
export const getExpressConfig = () => request.get('hsx_express/config', readOptions)
export const saveExpressConfig = (data: Record<string, any>) => request.put('hsx_express/config', data, readOptions)
export const checkExpressConfig = () => request.post('hsx_express/config/check', {}, readOptions)
export const getExpressTasks = (params: Record<string, any>) => request.get('hsx_express/tasks', { ...readOptions, params })
export const getExpressTask = (id: number) => request.get(`hsx_express/tasks/${id}`, readOptions)
export const reprintExpressTask = (id: number) => request.post(`hsx_express/tasks/${id}/reprint`, { confirm: 1 }, actionOptions)
export const recoverExpressTask = (id: number) => request.post(`hsx_express/tasks/${id}/recover`, { confirm: 1 }, actionOptions)
export const cancelExpressTask = (id: number, reason: string) => request.post(`hsx_express/tasks/${id}/cancel`, { reason }, actionOptions)
