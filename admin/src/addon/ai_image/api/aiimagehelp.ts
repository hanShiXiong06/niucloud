import request from '@/utils/request'

// USER_CODE_BEGIN -- aiimage_help
/**
 * 获取帮助中心列表
 * @param params
 * @returns
 */
export function getAiimageHelpList(params: Record<string, any>) {
    return request.get(`ai_image/aiimagehelp`, {params})
}

/**
 * 获取帮助中心详情
 * @param id 帮助中心id
 * @returns
 */
export function getAiimageHelpInfo(id: number) {
    return request.get(`ai_image/aiimagehelp/${id}`);
}

/**
 * 添加帮助中心
 * @param params
 * @returns
 */
export function addAiimageHelp(params: Record<string, any>) {
    return request.post('ai_image/aiimagehelp', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑帮助中心
 * @param id
 * @param params
 * @returns
 */
export function editAiimageHelp(params: Record<string, any>) {
    return request.put(`ai_image/aiimagehelp/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除帮助中心
 * @param id
 * @returns
 */
export function deleteAiimageHelp(id: number) {
    return request.delete(`ai_image/aiimagehelp/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}



// USER_CODE_END -- aiimage_help
