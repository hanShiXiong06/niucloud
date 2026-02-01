import request from '@/utils/request'

// USER_CODE_BEGIN -- fastpay_business_order
/**
 * 获取商户订单列列表
 * @param params
 * @returns
 */
export function getBusinessOrderList(params: Record<string, any>) {
    return request.get(`fast_pay/businessorder`, { params })
}

/**
 * 获取商户订单列详情
 * @param id 商户订单列id
 * @returns
 */
export function getBusinessOrderInfo(id: number) {
    return request.get(`fast_pay/businessorder/${id}`);
}

/**
 * 添加商户订单列
 * @param params
 * @returns
 */
export function addBusinessOrder(params: Record<string, any>) {
    return request.post('fast_pay/businessorder', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑商户订单列
 * @param id
 * @param params
 * @returns
 */
export function editBusinessOrder(params: Record<string, any>) {
    return request.put(`fast_pay/businessorder/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除商户订单列
 * @param id
 * @returns
 */
export function deleteBusinessOrder(id: number) {
    return request.delete(`fast_pay/businessorder/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getWithMemberList(params: Record<string, any>) {
    return request.get('fast_pay/member_all', { params })
} export function getWithBusinessList(params: Record<string, any>) {
    return request.get('fast_pay/business_all', { params })
}

// USER_CODE_END -- fastpay_business_order
