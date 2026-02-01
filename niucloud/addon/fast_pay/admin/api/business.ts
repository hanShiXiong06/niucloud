import request from '@/utils/request'

// USER_CODE_BEGIN -- fastpay_business
/**
 * 获取商户列列表
 * @param params
 * @returns
 */
export function getBusinessList(params: Record<string, any>) {
    return request.get(`fast_pay/business`, {params})
}

/**
 * 获取商户列详情
 * @param id 商户列id
 * @returns
 */
export function getBusinessInfo(id: number) {
    return request.get(`fast_pay/business/${id}`);
}

/**
 * 添加商户列
 * @param params
 * @returns
 */
export function addBusiness(params: Record<string, any>) {
    return request.post('fast_pay/business', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑商户列
 * @param id
 * @param params
 * @returns
 */
export function editBusiness(params: Record<string, any>) {
    return request.put(`fast_pay/business/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除商户列
 * @param id
 * @returns
 */
export function deleteBusiness(id: number) {
    return request.delete(`fast_pay/business/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getWithMemberList(params: Record<string,any>){
    return request.get('fast_pay/member_all', {params})
}

// USER_CODE_END -- fastpay_business
