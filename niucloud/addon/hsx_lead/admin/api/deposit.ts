import request from '@/utils/request'
import type { CoopRow } from '../composables/cooperation'

const root = 'hsx_lead/cooperation/deposits'
const quiet = { showErrorMessage: false }
export const getDeposits = (params: CoopRow = {}) => request.get(root, { ...quiet, params })
export const getDeposit = (id: number) => request.get(`${root}/${id}`, quiet)
export const reviewDeposit = (id: number, decision: 'approve' | 'reject', reason: string, requestVersion: string) =>
    request.post(`${root}/${id}/review`, { decision, reason, request_version: requestVersion }, quiet)
export const refreshDeposit = (id: number) => request.post(`${root}/${id}/refresh`, {}, quiet)
