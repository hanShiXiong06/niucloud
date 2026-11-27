import request from '@/utils/request'
import type { CoopRow } from '../composables/cooperation'
const root='hsx_lead/promotion',quiet={showErrorMessage:false}
export const getPromotionConfig=()=>request.get(`${root}/config`,quiet)
export const savePromotionConfig=(data:CoopRow)=>request.put(`${root}/config`,data,quiet)
export const getPromotionProviders=(params:CoopRow)=>request.get(`${root}/providers`,{...quiet,params})
export const getPromotionRelations=(params:CoopRow)=>request.get(`${root}/relations`,{...quiet,params})
export const getPromotionTree=(params:CoopRow)=>request.get(`${root}/relations`,{...quiet,params:{...params,view:'tree'}})
export const getPromotionInviters=(params:CoopRow)=>request.get(`${root}/relations`,{...quiet,params:{...params,view:'inviters'}})
export const savePromotionProvider=(memberId:number,data:CoopRow)=>request.put(`${root}/providers/${memberId}`,data,quiet)
export const reviewPromotionProvider=(memberId:number,data:{decision:'approve'|'reject';version:number;reason:string})=>request.post(`${root}/providers/${memberId}/review`,data,quiet)
export const getPromotionRewards=(params:CoopRow)=>request.get(`${root}/rewards`,{...quiet,params})
export const settlePromotionRewards=()=>request.post(`${root}/settle`,{limit:50},quiet)
export const holdPromotionReward=(id:number,reason:string)=>request.post(`${root}/rewards/${id}/hold`,{reason},quiet)
export const resumePromotionReward=(id:number,reason:string)=>request.post(`${root}/rewards/${id}/resume`,{reason},quiet)
export const retryPromotionPayment=(id:number)=>request.post(`${root}/orders/${id}/retry`,{},quiet)
