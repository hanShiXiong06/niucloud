import request from '@/utils/request'

export const getConfig = () => request.get('boyuan/config')
export const saveConfig = (data: any) => request.post('boyuan/config', data)
export const probe = () => request.post('boyuan/probe', {})
export const getTasks = (params: any) => request.get('boyuan/tasks', params)
export const getTask = (id: number) => request.get(`boyuan/tasks/${id}`)
export const taskAction = (id: number, data: any) => request.post(`boyuan/tasks/${id}/action`, data)
export const getMappings = (params: any = {}) => request.get('boyuan/mappings', params)
export const saveMapping = (data: any) => request.post('boyuan/mappings', data)
export const getLogs = () => request.get('boyuan/logs')
export const getStaff = () => request.get('boyuan/staff')
export const getReferences = (params: any) => request.get('boyuan/references', params)
