import request from '@/utils/request'
import type { CoopRow } from '../composables/cooperation'

const root = 'hsx_lead/entities'
const quiet = { showErrorMessage: false }
export const getEntities = (params: CoopRow = {}) => request.get(root, { ...quiet, params })
export const getEntity = (id: number) => request.get(`${root}/${id}`, quiet)
export const reviewEntityMember = (id: number, memberId: number, approve: boolean) => request.post(`${root}/${id}/members/${memberId}/review`, { approve }, quiet)
export const removeEntityMember = (id: number, memberId: number) => request.delete(`${root}/${id}/members/${memberId}`, quiet)
