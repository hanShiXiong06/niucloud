import request from '@/utils/request'
import type { CoopRow } from '../composables/cooperation'

const root = 'hsx_lead/cooperation'
const quiet = { showErrorMessage: false }
export const getCooperationDashboard = () => request.get(`${root}/dashboard`, quiet)
export const getCooperationScope = () => request.get(`${root}/scope`, quiet)
export const getCooperationOwners = (params: CoopRow = {}) => request.get(`${root}/owners`, { ...quiet, params })
export const getCooperationConfig = () => request.get(`${root}/config`, quiet)
export const saveCooperationConfig = (data: CoopRow) => request.put(`${root}/config`, data, quiet)
export const getProviderChannels = () => request.get(`${root}/config`, { ...quiet, params: { section: 'erp_channels' } })
export const saveProviderChannels = (data: CoopRow) => request.put(`${root}/config`, { ...data, section: 'erp_channels' }, quiet)
export const getCooperationLeads = (params: CoopRow = {}) => request.get(`${root}/leads`, { ...quiet, params })
export const getCooperationLead = (id: number) => request.get(`${root}/leads/${id}`, quiet)
export const transferCooperationOwner = (id: number, data: CoopRow) => request.post(`${root}/leads/${id}/owner`, data, quiet)
export const getCooperationPartners = (params: CoopRow = {}) => request.get(`${root}/partners`, { ...quiet, params })
export const saveCooperationPartner = (id: number, data: CoopRow) => request.put(`${root}/partners/${id}`, data, quiet)
export const blockCooperationPartner = (id: number, data: CoopRow) => request.post(`${root}/partners/${id}/block`, data, quiet)
export const getCooperationOrders = (params: CoopRow = {}) => request.get(`${root}/orders`, { ...quiet, params })
export const getCooperationReviews = (params: CoopRow = {}) => request.get(`${root}/reviews`, { ...quiet, params })
export const addCooperationReview = (data: CoopRow) => request.post(`${root}/reviews`, data, quiet)
export const resolveCooperationReview = (id: number, data: CoopRow) => request.post(`${root}/reviews/${id}/resolve`, data, quiet)
export const uploadCooperationAttachment = (file: File) => { const data=new FormData();data.append('file',file);return request.post(`${root}/attachments`,data,{...quiet,headers:{'Content-Type':'multipart/form-data'}}) }
