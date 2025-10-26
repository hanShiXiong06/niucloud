import request from '@/utils/request'

/***************************************************** 帮助表 ****************************************************/

/**
 * 获取帮助表列表
 * @param params
 * @returns
 */
export function gethelpList(params: Record<string, any>) {
    return request.get(`home_service/help`, {params})
}

/**
 * 获取帮助表详情
 * @param id 帮助表id
 * @returns
 */
export function gethelpInfo(id: number) {
    return request.get(`home_service/help/${id}`);
}

/**
 * 添加帮助表
 * @param params
 * @returns
 */
export function addhelp(params: Record<string, any>) {
    return request.post('home_service/help', params, {showSuccessMessage: true})
}

/**
 * 编辑帮助表
 * @param params
 */
export function edithelp(params: Record<string, any>) {
    return request.put(`home_service/help/${params.help_id}`, params, {showSuccessMessage: true})
}

/**
 * 删除帮助表
 * @param id
 * @returns
 */
export function deletehelp(id: number) {
    return request.delete(`home_service/help/${id}`, {showSuccessMessage: true})
}

/***************************************************** 帮助分类管理 ****************************************************/

/**
 * 获取帮助分类列表
 * @param params
 * @returns
 */
export function gethelpCategoryList(params: Record<string, any>) {
    return request.get(`home_service/help/category`, {params})
}

/**
 * 获取帮助全部分类
 * @param params
 * @returns
 */
export function gethelpCategoryAll(params: Record<string, any>) {
    return request.get(`home_service/category/all`, params)
}

/**
 * 获取帮助分类详情
 * @param category_id
 */
export function gethelpCategoryInfo(category_id: number) {
    return request.get(`home_service/category/${category_id}`);
}

/**
 * 添加帮助分类
 * @param params
 * @returns
 */
export function addhelpCategory(params: Record<string, any>) {
    return request.post('home_service/help/category', params, {showSuccessMessage: true})
}

/**
 * 编辑帮助分类
 * @param params
 * @returns
 */
export function edithelpCategory(params: Record<string, any>) {
    return request.put(`home_service/help/category/${params.category_id}`, params, {showSuccessMessage: true})
}

/**
 * 帮助分类删除
 * @param category_id
 */
export function deletehelpCategory(category_id: number) {
    return request.delete(`home_service/help/category/${category_id}`, {showSuccessMessage: true});
}

/**
 * 获取类型
 * @param category_id
 */
export function gethelpType(category_id: number) {
    return request.get(`home_service/help/type`,);
}