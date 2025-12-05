import request from '@/utils/request'



// USER_CODE_BEGIN -- aiimage_create
/**
 * 获取作品列列表
 * @param params
 * @returns
 */
export function getAiimageCreateList(params: Record<string, any>) {
    return request.get(`ai_image/aiimagecreate`, {params})
}

/**
 * 获取作品列详情
 * @param id 作品列id
 * @returns
 */
export function getAiimageCreateInfo(id: number) {
    return request.get(`ai_image/aiimagecreate/${id}`);
}

/**
 * 添加作品列
 * @param params
 * @returns
 */
export function addAiimageCreate(params: Record<string, any>) {
    return request.post('ai_image/aiimagecreate', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑作品列
 * @param id
 * @param params
 * @returns
 */
export function editAiimageCreate(params: Record<string, any>) {
    return request.put(`ai_image/aiimagecreate/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除作品列
 * @param id
 * @returns
 */
export function deleteAiimageCreate(id: number) {
    return request.delete(`ai_image/aiimagecreate/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getWithMemberList(params: Record<string,any>){
    return request.get('ai_image/member_all', {params})
}export function getWithAiimageModelList(params: Record<string,any>){
    return request.get('ai_image/aiimage_model_all', {params})
}

// USER_CODE_END -- aiimage_create
