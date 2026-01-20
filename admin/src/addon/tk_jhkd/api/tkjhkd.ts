import request from '@/utils/request'
/**
 * 验证分销权限
 * @returns 
 */
export function checkFenxiao() {
    return request.get(`tk_jhkd/checkfenxiao`)
}
export function addPlugin() {
    return request.get(`tk_jhkd/addplugin`)
}
/***************************************************** 聚合快递接口 ****************************************************/
/**
 * 获取余额
 * @param params
 * @returns
 */
export function getBalance() {
    return request.get(`tk_jhkd/getbalance`)
}
/**
 * 获取快递接口配置
 * @param params
 * @returns
 */
export function getJhkdConfig(params: Record<string, any>) {
    return request.get(`tk_jhkd/getconfig`, { params })
}
/**
 * 设置快递接口配置
 * @param params
 * @returns
 */
export function setJhkdConfig(params: Record<string, any>) {
    return request.post(`tk_jhkd/setconfig`, params, { showSuccessMessage: true })
}

/***************************************************** 聚合快递品牌列表 ****************************************************/

/**
 * 获取聚合快递品牌列表列表
 * @param params
 * @returns
 */
export function getTkjhkdBrandList(params: Record<string, any>) {
    return request.get(`tk_jhkd/brand`, { params })
}

//***************************************************** 聚合快递通知列表 ****************************************************/

/**
 * 获取聚合快递通知列表列表
 * @param params
 * @returns
 */
export function getTkjhkdNoticeList(params: Record<string, any>) {
    return request.get(`tk_jhkd/notice`, { params })
}

/**
 * 获取聚合快递通知列表详情
 * @param id 聚合快递通知列表id
 * @returns
 */
export function getTkjhkdNoticeInfo(id: number) {
    return request.get(`tk_jhkd/notice/${id}`);
}

/**
 * 添加聚合快递通知列表
 * @param params
 * @returns
 */
export function addTkjhkdNotice(params: Record<string, any>) {
    return request.post('tk_jhkd/notice', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑聚合快递通知列表
 * @param id
 * @param params
 * @returns
 */
export function editTkjhkdNotice(params: Record<string, any>) {
    return request.put(`tk_jhkd/notice/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除聚合快递通知列表
 * @param id
 * @returns
 */
export function deleteTkjhkdNotice(id: number) {
    return request.delete(`tk_jhkd/notice/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}
