import request from '@/utils/request'

// USER_CODE_BEGIN -- fastpay_business_member
/**
 * 获取商户会员列表
 * @param params
 * @returns
 */
export function getBusinessMemberList(params: Record<string, any>) {
    return request.get(`fast_pay/businessmember`, {params})
}

/**
 * 获取商户会员详情
 * @param id 商户会员id
 * @returns
 */
export function getBusinessMemberInfo(id: number) {
    return request.get(`fast_pay/businessmember/${id}`);
}

/**
 * 添加商户会员
 * @param params
 * @returns
 */
export function addBusinessMember(params: Record<string, any>) {
    return request.post('fast_pay/businessmember', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑商户会员
 * @param id
 * @param params
 * @returns
 */
export function editBusinessMember(params: Record<string, any>) {
    return request.put(`fast_pay/businessmember/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除商户会员
 * @param id
 * @returns
 */
export function deleteBusinessMember(id: number) {
    return request.delete(`fast_pay/businessmember/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getWithBusinessList(params: Record<string,any>){
    return request.get('fast_pay/business_all', {params})
}export function getWithMemberList(params: Record<string,any>){
    return request.get('fast_pay/member_all', {params})
}

// USER_CODE_END -- fastpay_business_member
