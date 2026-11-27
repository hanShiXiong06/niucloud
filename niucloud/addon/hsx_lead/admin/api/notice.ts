import request from '@/utils/request'

const root = 'hsx_lead/notice'
const quiet = { showErrorMessage: false }
export const getAccountNoticeSettings = () => request.get(`${root}/settings`, quiet)
export const saveAccountNoticeSettings = (data: Record<string, any>) => request.put(`${root}/settings`, data, quiet)
export const getAccountNoticeTasks = (params: Record<string, any>) => request.get(`${root}/tasks`, { ...quiet, params })
export const getAccountNoticeAttempts = (id: number) => request.get(`${root}/tasks/${id}/attempts`, quiet)
export const retryAccountNotice = (id: number, data: { confirm: true; reason: string; request_key: string }) =>
    request.post(`${root}/tasks/${id}/retry`, data, quiet)
export const getAccountNoticeTemplateLibrary = (params: Record<string, any>) =>
    request.get(`${root}/library`, { ...quiet, params })
