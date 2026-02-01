import request from '@/utils/request'

// USER_CODE_BEGIN -- fastpay_business_active
/**
 * 获取商户活动列表
 * @param params
 * @returns
 */
export function getBusinessActiveList(params: Record<string, any>) {
    return request.get(`fast_pay/businessactive`, {params})
}

/**
 * 获取商户活动详情
 * @param id 商户活动id
 * @returns
 */
export function getBusinessActiveInfo(id: number) {
    return request.get(`fast_pay/businessactive/${id}`);
}

/**
 * 添加商户活动
 * @param params
 * @returns
 */
export function addBusinessActive(params: Record<string, any>) {
    return request.post('fast_pay/businessactive', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑商户活动
 * @param id
 * @param params
 * @returns
 */
export function editBusinessActive(params: Record<string, any>) {
    return request.put(`fast_pay/businessactive/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除商户活动
 * @param id
 * @returns
 */
export function deleteBusinessActive(id: number) {
    return request.delete(`fast_pay/businessactive/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getWithBusinessList(params: Record<string,any>){
    return request.get('fast_pay/business_all', {params})
}

// USER_CODE_END -- fastpay_business_active
