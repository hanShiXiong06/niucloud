import request from '@/utils/request'

// USER_CODE_BEGIN -- kdapi_order
/**
 * 获取订单列列表
 * @param params
 * @returns
 */
export function getKdapiOrderList(params: Record<string, any>) {
    return request.get(`kd_api/kdapi_order`, {params})
}

/**
 * 获取订单列详情
 * @param id 订单列id
 * @returns
 */
export function getKdapiOrderInfo(id: number) {
    return request.get(`kd_api/kdapi_order/${id}`);
}

/**
 * 添加订单列
 * @param params
 * @returns
 */
export function addKdapiOrder(params: Record<string, any>) {
    return request.post('kd_api/kdapi_order', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑订单列
 * @param id
 * @param params
 * @returns
 */
export function editKdapiOrder(params: Record<string, any>) {
    return request.put(`kd_api/kdapi_order/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除订单列
 * @param id
 * @returns
 */
export function deleteKdapiOrder(id: number) {
    return request.delete(`kd_api/kdapi_order/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getWithMemberList(params: Record<string,any>){
    return request.get('kd_api/member_all', {params})
}

// USER_CODE_END -- kdapi_order
