
import request from '@/utils/request'

/***************************************************** 快递API前端信息 ****************************************************/
export function getConfig() {
    return request.get(`kd_api/getconfig`)
}
export function restKey(){
    return request.post(`kd_api/restkey`)
}

export function getOrder(params:any) {
    return request.get(`kd_api/getorder`, params)
}
export function getLink() {
    return request.get(`kd_api/getlink`)
}