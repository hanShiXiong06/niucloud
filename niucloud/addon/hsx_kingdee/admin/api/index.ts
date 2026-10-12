import request from '@/utils/request'

export const getConfig = () => request.get('kingdee/config')
export const getStaff = () => request.get('kingdee/staff')
export const getLogs = (params: Record<string, any>) => request.get('kingdee/logs', { params })
export const saveConfig = (data: Record<string, any>) => request.post('kingdee/config', data)
export const probeConnection = (revision: number) => request.post('kingdee/connection/probe', { revision })
export const getTasks = (params: Record<string, any>) => request.get('kingdee/tasks', { params })
export const getTask = (id: number) => request.get(`kingdee/tasks/${id}`)
export const retryTask = (id: number) => request.post(`kingdee/tasks/${id}/retry`)
export const requestNotice = (id: number) => request.post(`kingdee/tasks/${id}/notice`)
export const resolveTask = (id: number, data: Record<string, any>) => request.post(`kingdee/tasks/${id}/resolve`, data)
export const getMappings = (params: Record<string, any>) => request.get('kingdee/mappings', { params })
export const saveMapping = (data: Record<string, any>) => request.post('kingdee/mappings', data)
