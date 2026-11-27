import request from '@/utils/request'

export const getProjectCenterProject = (id: number) => request.get(`project-center/projects/${id}`)
export const getProjectCenterAreaEligibility = (id: number, params: Record<string, any>) => request.get(`project-center/projects/${id}/area-eligibility`, params, { showErrorMessage: false })
export const checkProjectCenterGroup = (id: number, groupNo: string) => request.get(`project-center/projects/${id}/group-check`, { group_no: groupNo }, { showErrorMessage: false })
export const resolveProjectCenterGroup = (id: number, groupNo: string) => request.post(`project-center/projects/${id}/group-resolve`, { group_no: groupNo }, { showErrorMessage: false })
export const getProjectCenterApplicationStatus = (id: number, groupNo = '') => request.get(`project-center/projects/${id}/status`, { group_no: groupNo })
export const submitProjectCenterApplication = (id: number, data: Record<string, any>) => request.post(`project-center/projects/${id}/submit`, data, { showErrorMessage: false })
export const reviseProjectCenterApplication = (id: number, data: Record<string, any>) => request.post(`project-center/projects/${id}/revise`, data, { showErrorMessage: false })
export const createProjectCenterDistributionInvite = (id: number) => request.post(`project-center/projects/${id}/distribution/invite`)
export const bindProjectCenterDistributionInvite = (id: number, token: string) => request.post(`project-center/projects/${id}/distribution/bind`, { token }, { showErrorMessage: false })
export const getProjectCenterDistributionOverview = (projectId = 0) => request.get('project-center/distribution/overview', { project_id: projectId })
export const getProjectCenterDistributionDetails = (params: Record<string, any>) => request.get('project-center/distribution/details', params)
export const generateProjectCenterDistributionPoster = (id: number, invite: string, posterId = 0) => request.post(`project-center/projects/${id}/distribution/poster`, { invite, poster_id: posterId }, { showErrorMessage: false })
