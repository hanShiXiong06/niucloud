import request from '@/utils/request'

// USER_CODE_BEGIN -- aiimage_order
/**
 * 获取订单列列表
 * @param params
 * @returns
 */
export function getAiimageOrderList(params: Record<string, any>) {
    return request.get(`ai_image/aiimageorder`, {params})
}

/**
 * 获取订单列详情
 * @param id 订单列id
 * @returns
 */
export function getAiimageOrderInfo(id: number) {
    return request.get(`ai_image/aiimageorder/${id}`);
}

/**
 * 添加订单列
 * @param params
 * @returns
 */
export function addAiimageOrder(params: Record<string, any>) {
    return request.post('ai_image/aiimageorder', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑订单列
 * @param id
 * @param params
 * @returns
 */
export function editAiimageOrder(params: Record<string, any>) {
    return request.put(`ai_image/aiimageorder/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除订单列
 * @param id
 * @returns
 */
export function deleteAiimageOrder(id: number) {
    return request.delete(`ai_image/aiimageorder/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getWithMemberList(params: Record<string,any>){
    return request.get('ai_image/member_all', {params})
}export function getWithAiimagePackageList(params: Record<string,any>){
    return request.get('ai_image/aiimage_package_all', {params})
}

// USER_CODE_END -- aiimage_order
