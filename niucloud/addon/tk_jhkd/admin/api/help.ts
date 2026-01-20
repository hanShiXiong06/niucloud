import request from '@/utils/request'



// USER_CODE_BEGIN -- tkjhkd_help
/**
 * 获取帮助中心列表
 * @param params
 * @returns
 */
export function getHelpList(params: Record<string, any>) {
    return request.get(`tk_jhkd/help`, {params})
}
/**
 * 同步help
 * @returns
 */
export function asyncHelp() {
    return request.get(`tk_jhkd/asynchelp`,{ showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 获取帮助中心详情
 * @param id 帮助中心id
 * @returns
 */
export function getHelpInfo(id: number) {
    return request.get(`tk_jhkd/help/${id}`);
}

/**
 * 添加帮助中心
 * @param params
 * @returns
 */
export function addHelp(params: Record<string, any>) {
    return request.post('tk_jhkd/help', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑帮助中心
 * @param id
 * @param params
 * @returns
 */
export function editHelp(params: Record<string, any>) {
    return request.put(`tk_jhkd/help/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除帮助中心
 * @param id
 * @returns
 */
export function deleteHelp(id: number) {
    return request.delete(`tk_jhkd/help/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}



// USER_CODE_END -- tkjhkd_help
