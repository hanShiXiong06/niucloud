import request from '@/utils/request'
const root = 'hsx_lead/cooperation/entity'
const quiet = { showErrorMessage: false }
export const entityTeam = () => request.get(root, {}, quiet)
export const entityLookup = (code: string) => request.get(`${root}/lookup`, { code }, quiet)
export const entityJoin = (subject_code: string) => request.post(`${root}/join`, { subject_code }, quiet)
export const entityReview = (memberId: number, approve: boolean) => request.post(`${root}/members/${memberId}/review`, { approve }, quiet)
export const entityRemove = (memberId: number) => request.delete(`${root}/members/${memberId}`, {}, quiet)
export const entityLeave = () => request.post(`${root}/leave`, {}, quiet)
export const entityMembership = () => request.get(`${root}/membership`, {}, quiet)
export const entityQuote = (level_code: string, cycle: string, buy_experience = false) => request.get(`${root}/quote`, { level_code, cycle, buy_experience: buy_experience === true ? 1 : 0 }, quiet)
export const entityCloseOrder = (id: number) => request.post(`${root}/orders/${id}/close`, {}, quiet)
export const entityDeposit = () => request.get(`${root}/deposit`, {}, quiet)
export const entityApplyDepositRefund = (request_id: string, reason = '') => request.post(`${root}/deposit/apply`, { request_id, confirm: true, reason }, quiet)
