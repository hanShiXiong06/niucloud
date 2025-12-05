import request from '@/utils/request'
export function asyncModel() {
    return request.post(`ai_image/aiimagemodel/asyncmodel`)
}
// USER_CODE_BEGIN -- aiimage_model
/**
 * 获取智能体列表
 * @param params
 * @returns
 */
export function getAiimageModelList(params: Record<string, any>) {
    return request.get(`ai_image/aiimagemodel`, { params })
}

/**
 * 获取智能体详情
 * @param id 智能体id
 * @returns
 */
export function getAiimageModelInfo(id: number) {
    return request.get(`ai_image/aiimagemodel/${id}`);
}

/**
 * 添加智能体
 * @param params
 * @returns
 */
export function addAiimageModel(params: Record<string, any>) {
    return request.post('ai_image/aiimagemodel', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑智能体
 * @param id
 * @param params
 * @returns
 */
export function editAiimageModel(params: Record<string, any>) {
    return request.put(`ai_image/aiimagemodel/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除智能体
 * @param id
 * @returns
 */
export function deleteAiimageModel(id: number) {
    return request.delete(`ai_image/aiimagemodel/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}



// USER_CODE_END -- aiimage_model
