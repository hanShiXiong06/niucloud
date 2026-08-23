import request from '@/utils/request'

export const getProjectCenterProject = (id: number) => request.get(`project-center/projects/${id}`)
export const checkProjectCenterGroup = (id: number, groupNo: string) => request.get(`project-center/projects/${id}/group-check`, { group_no: groupNo }, { showErrorMessage: false })
export const resolveProjectCenterGroup = (id: number, groupNo: string) => request.post(`project-center/projects/${id}/group-resolve`, { group_no: groupNo }, { showErrorMessage: false })
export const getProjectCenterApplicationStatus = (id: number, groupNo = '') => request.get(`project-center/projects/${id}/status`, { group_no: groupNo })
export const submitProjectCenterApplication = (id: number, data: Record<string, any>) => request.post(`project-center/projects/${id}/submit`, data, { showErrorMessage: false })
export const reviseProjectCenterApplication = (id: number, data: Record<string, any>) => request.post(`project-center/projects/${id}/revise`, data, { showErrorMessage: false })
