import request from '@/utils/request'

// USER_CODE_BEGIN -- aiimage_package
/**
 * 获取套餐列列表
 * @param params
 * @returns
 */
export function getAiimagePackageList(params: Record<string, any>) {
    return request.get(`ai_image/aiimagepackage`, {params})
}

/**
 * 获取套餐列详情
 * @param id 套餐列id
 * @returns
 */
export function getAiimagePackageInfo(id: number) {
    return request.get(`ai_image/aiimagepackage/${id}`);
}

/**
 * 添加套餐列
 * @param params
 * @returns
 */
export function addAiimagePackage(params: Record<string, any>) {
    return request.post('ai_image/aiimagepackage', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑套餐列
 * @param id
 * @param params
 * @returns
 */
export function editAiimagePackage(params: Record<string, any>) {
    return request.put(`ai_image/aiimagepackage/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除套餐列
 * @param id
 * @returns
 */
export function deleteAiimagePackage(id: number) {
    return request.delete(`ai_image/aiimagepackage/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}



// USER_CODE_END -- aiimage_package
