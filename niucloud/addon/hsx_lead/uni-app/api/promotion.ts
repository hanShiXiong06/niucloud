import request from '@/utils/request'
import type { CoopRow } from './cooperation'

const root = 'hsx_lead/promotion'
const quiet = { showErrorMessage: false }
export const promotionOverview = () => request.get(`${root}/overview`, {}, quiet)
export const promotionTeam = (query: CoopRow) => request.get(`${root}/team`, query, quiet)
export const promotionRewards = (query: CoopRow) => request.get(`${root}/rewards`, query, quiet)
export const promotionInvitation = (mid: number) => request.get(`${root}/invitation`, { mid }, quiet)
export const bindPromotionInviter = (mid: number) => request.post(`${root}/bind`, { mid, confirm: true }, quiet)
