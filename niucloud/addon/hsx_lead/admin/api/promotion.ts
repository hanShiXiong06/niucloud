import request from '@/utils/request'
import type { CoopRow } from '../composables/cooperation'
const root='hsx_lead/promotion',quiet={showErrorMessage:false}
export const getPromotionConfig=()=>request.get(`${root}/config`,quiet)
export const savePromotionConfig=(data:CoopRow)=>request.put(`${root}/config`,data,quiet)
export const getPromotionProviders=(params:CoopRow)=>request.get(`${root}/providers`,{...quiet,params})
export const savePromotionProvider=(memberId:number,data:CoopRow)=>request.put(`${root}/providers/${memberId}`,data,quiet)
export const getPromotionRewards=(params:CoopRow)=>request.get(`${root}/rewards`,{...quiet,params})
export const settlePromotionRewards=()=>request.post(`${root}/settle`,{limit:50},quiet)
export const holdPromotionReward=(id:number,reason:string)=>request.post(`${root}/rewards/${id}/hold`,{reason},quiet)
export const resumePromotionReward=(id:number,reason:string)=>request.post(`${root}/rewards/${id}/resume`,{reason},quiet)
