import request from '@/utils/request'

const root = 'kingdee_jdy'
export const getConfig = () => request.get(`${root}/config`)
export const saveConfig = (data: Record<string, any>) => request.post(`${root}/config`, data)
export const getStaff = () => request.get(`${root}/staff`)
export const probe = (revision: number) => request.post(`${root}/connection/probe`, { revision })
export const getTasks = (params: Record<string, any>) => request.get(`${root}/tasks`, { params })
export const getTask = (id: number) => request.get(`${root}/tasks/${id}`)
export const retry = (id: number) => request.post(`${root}/tasks/${id}/retry`)
export const notice = (id: number) => request.post(`${root}/tasks/${id}/notice`)
export const resolve = (id: number, data: Record<string, any>) => request.post(`${root}/tasks/${id}/resolve`, data)
export const getMappings = (params: Record<string, any>) => request.get(`${root}/mappings`, { params })
export const saveMapping = (data: Record<string, any>) => request.post(`${root}/mappings`, data)
export const getLogs = (params: Record<string, any>) => request.get(`${root}/logs`, { params })
