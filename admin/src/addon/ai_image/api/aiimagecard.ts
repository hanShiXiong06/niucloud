import request from '@/utils/request'
export function delselect(params: Record<string, any>) {
    return request.post(`ai_image/delcardselect`, params, { showErrorMessage: true, showSuccessMessage: true })
}
// USER_CODE_BEGIN -- aiimage_card
/**
 * 获取卡密兑换列表
 * @param params
 * @returns
 */
export function getAiimageCardList(params: Record<string, any>) {
    return request.get(`ai_image/aiimagecard`, { params })
}

/**
 * 获取卡密兑换详情
 * @param id 卡密兑换id
 * @returns
 */
export function getAiimageCardInfo(id: number) {
    return request.get(`ai_image/aiimagecard/${id}`);
}

/**
 * 添加卡密兑换
 * @param params
 * @returns
 */
export function addAiimageCard(params: Record<string, any>) {
    return request.post('ai_image/aiimagecard', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑卡密兑换
 * @param id
 * @param params
 * @returns
 */
export function editAiimageCard(params: Record<string, any>) {
    return request.put(`ai_image/aiimagecard/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除卡密兑换
 * @param id
 * @returns
 */
export function deleteAiimageCard(id: number) {
    return request.delete(`ai_image/aiimagecard/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getWithMemberList(params: Record<string, any>) {
    return request.get('ai_image/member_all', { params })
}

// USER_CODE_END -- aiimage_card
