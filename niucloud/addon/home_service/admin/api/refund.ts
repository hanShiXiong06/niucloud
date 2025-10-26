import request from '@/utils/request'

/***************************************************** 售后表 ****************************************************/

/**
 * 获取售后表列表
 * @param params
 * @returns
 */
export function getRefundList(params: Record<string, any>) {
    return request.get(`home_service/refund`, {params})
}

/**
 * 获取售后表详情
 * @param id 售后表id
 * @returns
 */
export function getRefundInfo(id: number) {
    return request.get(`home_service/Refund/${id}`);
}

/**
 * 添加售后表
 * @param params
 * @returns
 */
export function addRefund(params: Record<string, any>) {
    return request.post('home_service/Refund', params, {showSuccessMessage: true})
}

/**
 * 编辑售后表
 * @param params
 */
export function editRefund(params: Record<string, any>) {
    return request.put(`home_service/Refund/${params.Refund_id}`, params, {showSuccessMessage: true})
}

/**
 * 删除售后表
 * @param id
 * @returns
 */
export function deleteRefund(id: number) {
    return request.delete(`home_service/Refund/${id}`, {showSuccessMessage: true})
}

/***************************************************** 售后分类管理 ****************************************************/

/**
 * 获取售后分类列表
 * @param params
 * @returns
 */
export function getRefundCategoryList(params: Record<string, any>) {
    return request.get(`home_service/Refund/category`, {params})
}

/**
 * 获取售后全部分类
 * @param params
 * @returns
 */
export function getRefundCategoryAll(params: Record<string, any>) {
    return request.get(`home_service/category/all`, params)
}

/**
 * 获取售后分类详情
 * @param params
 */
export function getRefundCategoryInfo(category_id: number) {
    return request.get(`home_service/category/${category_id}`);
}

/**
 * 添加售后分类
 * @param params
 * @returns
 */
export function addRefundCategory(params: Record<string, any>) {
    return request.post('home_service/Refund/category', params, {showSuccessMessage: true})
}

/**
 * 编辑售后分类
 * @param params
 * @returns
 */
export function editRefundCategory(params: Record<string, any>) {
    return request.put(`home_service/Refund/category/${params.category_id}`, params, {showSuccessMessage: true})
}

/**
 * 售后分类删除
 * @param params
 */
export function deleteRefundCategory(category_id: number) {
    return request.delete(`home_service/Refund/category/${category_id}`, {showSuccessMessage: true});
}

/**
 * 获取状态
 * @param status
 */
export function getRefundType() {
    return request.get(`home_service/refund/taskstatus`,);
}



/**
 * 拒绝
 * @param status
 */
export function setRefundadoptStatus(params: Record<string, any>) {
    return request.put(`home_service/refund/refuse/${params.refund_id}`, params,{showSuccessMessage: true});
}

/**
 * 通过
 * @param status
 */
export function setRefundSuccessStatus(params: Record<string, any>) {
    return request.put(`home_service/refund/${params.refund_id}`,params, {showSuccessMessage: true});
}

/**
 * 获取售后配置
 * @param status
 */
export function getRefundConfig(params: Record<string, any>) {
    return request.get(`home_service/order_refund/config`,params, {showSuccessMessage: true});
}


/**
 * 设置售后
 * @param status
 */
export function setRefundConfig(params: Record<string, any>) {
    return request.post(`home_service/order_refund/config`,params, {showSuccessMessage: true});
}

