import request from '@/utils/request'

export const readOpeningJobs = () => request.get('boyuan/reference-sync')
// Reads this site's saved categories, never starts a sync or requests Boyuan.
export const readCategoryDirectory = (expected_connection_key: string) =>
    request.post('boyuan/reference-search', { operation: 'category_directory', expected_connection_key })
// Only the selected reference job is advanced; this is not a general queue trigger.
export const readJobPage = (id: number, resource: string, expected_connection_key: string, mode: string, cursor: string) =>
    request.post('boyuan/reference-sync', {intent:'read_job_page', id, resource, expected_connection_key, mode, cursor})
export const readManualDelivery = () => request.get('boyuan/config', { params: { operation: 'uat_manual_delivery' } })
export const setManualDelivery = (data: {intent:'enable'|'pause';revision:number;connection_key:string;confirm_account:string}) =>
    request.post('boyuan/config', { ...data, operation: 'uat_manual_delivery' })
export const queueOpening = (resources: string[], expected_connection_key: string, intent: 'opening' | 'refresh') =>
    request.post('boyuan/reference-sync', { resources, expected_connection_key, intent })
export const resumeOpening = (id: number, expected_connection_key: string) =>
    request.post(`boyuan/reference-sync/${id}/retry`, { expected_connection_key })
export const readOpeningRows = (resource: string, keyword: string, page: number, expected_connection_key: string, filters: {role?:string; archive_status?:string; limit?:number} = {}) =>
    request.post('boyuan/reference-search', { operation:'archive_list', resource, keyword, cursor: String(page), limit: filters.limit || 20, role:filters.role || '', archive_status:filters.archive_status || '', cached:true, expected_connection_key })
export const readArchiveDetail = (resource:string, id:string, expected_connection_key:string, check_source=false) =>
    request.post('boyuan/reference-search',{operation:'archive_detail',resource,id,expected_connection_key,check_source})
