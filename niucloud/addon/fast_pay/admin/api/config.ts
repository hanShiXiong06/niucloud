import request from '@/utils/request'


/***************************************************** 快速支付配置接口 ****************************************************/


export function getBusinessConfig() {
    return request.get(`fast_pay/config/getbusinessconfig`)
}
/**
 * 设置配置
 * @param params
 * @returns
 */
export function setBusinessConfig(params: Record<string, any>) {
    return request.post(`fast_pay/config/setbusinessconfig`, params, { showErrorMessage: true, showSuccessMessage: true })
}
export function getAdminConfig() {
    return request.get(`fast_pay/config/getadminconfig`)
}
/**
 * 设置配置
 * @param params
 * @returns
 */
export function setAdminConfig(params: Record<string, any>) {
    return request.post(`fast_pay/config/setadminconfig`, params, { showErrorMessage: true, showSuccessMessage: true })
}
/**
 * 获取接口配置
 * @param params
 * @returns
 */
export function getConfig() {
    return request.get(`fast_pay/config/getconfig`)
}
/**
 * 设置配置
 * @param params
 * @returns
 */
export function setConfig(params: Record<string, any>) {
    return request.post(`fast_pay/config/setconfig`, params, { showErrorMessage: true, showSuccessMessage: true })
}
export function poster(params: Record<string, any>) {
    return request.post(`fast_pay/config/poster`, params, { showErrorMessage: true, showSuccessMessage: true })
}

