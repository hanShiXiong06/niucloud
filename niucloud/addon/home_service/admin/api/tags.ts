import request from '@/utils/request'

/***************************************************** 标签表 ****************************************************/

/**
 * 获取标签表列表
 * @param params
 * @returns
 */
export function getOrderlaberList(params: Record<string, any>) {
    return request.get(`home_service/orderlaber`, {params})
}

/**
 * 获取标签表详情
 * @param id 标签表id
 * @returns
 */
export function getOrderlaberInfo(id: number) {
    return request.get(`home_service/orderlaber/${id}`);
}

/**
 * 添加标签表
 * @param params
 * @returns
 */
export function addOrderlaber(params: Record<string, any>) {
    return request.post('home_service/orderlaber', params, {showSuccessMessage: true})
}

/**
 * 编辑标签表
 * @param params
 */
export function editOrderlaber(params: Record<string, any>) {
    return request.put(`home_service/orderlaber/${params.label_id}`, params, {showSuccessMessage: true})
}

/**
 * 删除标签表
 * @param id
 * @returns
 */
export function deleteOrderlaber(id: number) {
    return request.delete(`home_service/orderlaber/${id}`, {showSuccessMessage: true})
}

/***************************************************** 标签分类管理 ****************************************************/

/**
 * 获取标签分类列表
 * @param params
 * @returns
 */
export function getOrderlaberCategoryList(params: Record<string, any>) {
    return request.get(`home_service/orderlaber/category`, {params})
}

/**
 * 获取标签全部分类
 * @param params
 * @returns
 */
export function getOrderlaberCategoryAll(params: Record<string, any>) {
    return request.get(`home_service/category/all`, params)
}

/**
 * 获取标签分类详情
 * @param category_id
 */
export function getOrderlaberCategoryInfo(category_id: number) {
    return request.get(`home_service/category/${category_id}`);
}

/**
 * 添加标签分类
 * @param params
 * @returns
 */
export function addOrderlaberCategory(params: Record<string, any>) {
    return request.post('home_service/orderlaber/category', params, {showSuccessMessage: true})
}

/**
 * 编辑标签分类
 * @param params
 * @returns
 */
export function editOrderlaberCategory(params: Record<string, any>) {
    return request.put(`home_service/orderlaber/category/${params.category_id}`, params, {showSuccessMessage: true})
}

/**
 * 标签分类删除
 * @param category_id
 */
export function deleteOrderlaberCategory(category_id: number) {
    return request.delete(`home_service/orderlaber/category/${category_id}`, {showSuccessMessage: true});
}

/**
 * 获取类型
 * @param category_id
 */
export function getOrderlaberType(category_id: number) {
    return request.get(`home_service/orderlaber/type`,);
}