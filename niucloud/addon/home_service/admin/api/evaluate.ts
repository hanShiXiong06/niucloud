import request from '@/utils/request'

/***************************************************** 评价表 ****************************************************/

/**
 * 获取评价表列表
 * @param params
 * @returns
 */
export function getEvaluateList(params: Record<string, any>) {
    return request.get(`home_service/order/evaluate`, {params})
}

/**
 * 获取评价表详情
 * @param id 评价表id
 * @returns
 */
export function getEvaluateInfo(id: number) {
    return request.get(`home_service/order/evaluate/${id}`);
}

/**
 * 添加评价表
 * @param params
 * @returns
 */
export function addEvaluate(params: Record<string, any>) {
    return request.post('home_service/order/evaluate', params, {showSuccessMessage: true})
}

/**
 * 编辑评价表
 * @param params
 */
export function editEvaluate(params: Record<string, any>) {
    return request.put(`home_service/order/evaluate/${params.evaluate_id}`, params, {showSuccessMessage: true})
}

/**
 * 删除评价表
 * @param id
 * @returns
 */
export function deleteEvaluate(id: number) {
    return request.delete(`home_service/order/evaluate/${id}`, {showSuccessMessage: true})
}

/***************************************************** 评价分类管理 ****************************************************/

/**
 * 获取评价分类列表
 * @param params
 * @returns
 */
export function getEvaluateCategoryList(params: Record<string, any>) {
    return request.get(`home_service/order/evaluate/category`, {params})
}

/**
 * 获取评价全部分类
 * @param params
 * @returns
 */
export function getEvaluateCategoryAll(params: Record<string, any>) {
    return request.get(`home_service/order/category/all`, params)
}

/**
 * 获取评价分类详情
 * @param params
 */
export function getEvaluateCategoryInfo(category_id: number) {
    return request.get(`home_service/order/category/${category_id}`);
}

/**
 * 添加评价分类
 * @param params
 * @returns
 */
export function addEvaluateCategory(params: Record<string, any>) {
    return request.post('home_service/order/evaluate/category', params, {showSuccessMessage: true})
}

/**
 * 编辑评价分类
 * @param params
 * @returns
 */
export function editEvaluateCategory(params: Record<string, any>) {
    return request.put(`home_service/order/evaluate/category/${params.category_id}`, params, {showSuccessMessage: true})
}

/**
 * 评价分类删除
 * @param params
 */
export function deleteEvaluateCategory(category_id: number) {
    return request.delete(`home_service/order/evaluate/category/${category_id}`, {showSuccessMessage: true});
}

/**
 * 获取状态
 * @param status
 */
export function getEvaluateType(store_id: number) {
    return request.get(`home_service/order/evaluate/taskstatus`,{params:{store_id:store_id}});
}


/**
 * 通过
 * @param status
 */
export function setEvaluateadoptStatus(params: Record<string, any>) {
    return request.put(`home_service/order/evaluate/adopt/${params.evaluate_id}`,params, {showSuccessMessage: true});
}

/**
 * 拒绝
 * @param status
 */
export function setEvaluaterefuseStatus(params: Record<string, any>) {
    return request.put(`home_service/order/evaluate/refuse/${params.evaluate_id}`,params, {showSuccessMessage: true});
}


/**
 * 获取评价配置
 * @param status
 */
export function getEvaluateConfig(params: Record<string, any>) {
    return request.get(`home_service/evaluate/config`,params, {showSuccessMessage: true});
}


/**
 * 设置评价
 * @param status
 */
export function setEvaluateConfig(params: Record<string, any>) {
    return request.post(`home_service/evaluate/config`,params);
}

