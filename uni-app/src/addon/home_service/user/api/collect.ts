import request from '@/utils/request'


/***************************************************** 收藏 ****************************************************/


/**
 * 添加收藏
 */
export function setCollect(data: Record<string, any>) {
    return request.post(`home_service/goods/collect`, data, { showSuccessMessage: true })
}



/**
 * 取消商品收藏
 */
export function cancelCollect(params: Record<string, any>) {
    return request.put(`home_service/goods/collect`, params,{ showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 查询收藏（单条）  ok
 */
export function getCollect(data: Record<string, any>) {
    return request.get(`home_service/goods/collect`, data)
}


/**
 * 获取商品收藏列表
 */
export function getCollectList(params?: Record<string, any>) {
    return request.get(`home_service/goods/collect`, params)
}



/**
 * 获取师傅收藏列表
 */
export function getTechnicianCollectList(params?: Record<string, any>) {
    return request.get(`home_service/technician/collect`, params)
}

/**
 * 取消师傅收藏
 */
export function cancelTechnicianCollect(params: Record<string, any>) {
    return request.put(`home_service/technician/collect`, params, { showErrorMessage: true, showSuccessMessage: true })
}


/**
 * 收藏师傅
 * @returns
 */
export function collectTechnician(params: Record<string, any>) {
    return request.post(`home_service/technician/collect`,params ,{ showSuccessMessage: true })
}


