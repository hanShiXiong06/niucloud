import request from '@/utils/request'

/***************************************************** 师傅订单管理 ****************************************************/
/**
 * 抢单订单列表(分页)
 * @param params
 * @returns
 */
export function getTechnicianOrderList(params: Record<string, any>) {
    return request.get(`home_service/technician/grab`, params)
}

/**
 * 分类列表
 * @param params
 * @returns
 */
export function getCategoryOrderList(params: Record<string, any>) {
    return request.get(`home_service/technician/grabcategory`, params)
}

/**
 * 距离列表
 * @param params
 * @returns
 */
export function getCategorygrabdistanceList(params: Record<string, any>) {
    return request.get(`home_service/technician/grabdistance`, params)
}

/**
 * 订单状态
 * @param params
 * @returns
 */
export function getOrderStatus(params: Record<string, any>) {
    return request.get(`home_service/technician/order/task_status`, params)
}

/**
 * 抢单
 */
export function grabOrder(id: number) {
    return request.put(`home_service/technician/grab/${ id }`)
}

/**
 * 普通订单列表(分页)
 * @param params
 * @returns
 */
export function getAllTechnicianOrderList(params: Record<string, any>) {
    return request.get(`home_service/technician/order`, params)
}


/**
 * 订单超时数量
 * @param params
 * @returns
 */
export function getOrderTimeOut(params: Record<string, any>) {
    return request.get(`home_service/technician/order/order_time_num`)
}


/**
 * 出发
 * @param params
 * @returns
 */
export function departOrder(params: Record<string, any>) {
    return request.post(`home_service/technician/order/depart`,params,{ showSuccessMessage: true, showErrorMessage: true })
}

/**
 * 拍照
 * @param params
 * @returns
 */
export function photoTaken(params: Record<string, any>) {
    return request.post(`home_service/technician/order/phototaken`,params,{ showSuccessMessage: true, showErrorMessage: true })
}


/**
 * 开始服务
 * @param params
 * @returns
 */
export function startService(params: Record<string, any>) {
    return request.post(`home_service/technician/order/start`,params,{ showSuccessMessage: true, showErrorMessage: true })
}


/**
 * 服务完成
 * @param params
 * @returns
 */
export function saveCheck(params: Record<string, any>) {
    return request.post(`home_service/technician/order/savecheck`,params,{ showSuccessMessage: true, showErrorMessage: true })
}


/**
 * 获取订单详情
 */
export function grabOrderDetail(id: number) {
    return request.get(`home_service/technician/order/${ id }`)
}



/**
 * 获取订单增项服务详情
 */
export function grabAddOrderDetail(params: Record<string, any>) {
    return request.get(`home_service/technician/order/item`,params)
}

/**
 * 获取抢单详情
 */
export function grabgrapOrderDetail(id: number) {
    return request.get(`home_service/technician/grab/${ id }`)
}


/**
 * 获取增项服务详情
 */
export function getGoodsItem(params: Record<string, any>) {
    return request.get(`home_service/technician/order/goodsItem`,params)
}

/**
 * 编辑增项服务
 */
export function editGoodsItem(params: Record<string, any>) {
    return request.put(`home_service/technician/order/item`,params,{ showSuccessMessage: true, showErrorMessage: true })
}


/**
 * 新增增项服务
 */
export function addGoodsItem(params: Record<string, any>) {
    return request.post(`home_service/technician/order/item`,params,{ showSuccessMessage: true, showErrorMessage: true })
}

/**
 * 修改服务时间
 */
export function editServiceTime(params: Record<string, any>) {
    return request.put(`home_service/technician/order/editServiceTime/${params.order_id}`,params,{ showSuccessMessage: true, showErrorMessage: true })
}

