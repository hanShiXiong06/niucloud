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
    return request.post(`home_service/technician/order/depart`,params)
}

/**
 * 拍照
 * @param params
 * @returns
 */
export function photoTaken(params: Record<string, any>) {
    return request.post(`home_service/technician/order/phototaken`,params)
}


/**
 * 开始服务
 * @param params
 * @returns
 */
export function startService(params: Record<string, any>) {
    return request.post(`home_service/technician/order/start`,params)
}


/**
 * 服务完成
 * @param params
 * @returns
 */
export function saveCheck(params: Record<string, any>) {
    return request.post(`home_service/technician/order/savecheck`,params)
}

/**
 * 更改师傅状态
 * @param params
 * @returns
 */
export function setTechnicianStatus(params: Record<string, any>) {
    return request.put(`home_service/technician/modify/status`,params)
}

/**
 * 师傅排行榜
 * @param params 包含type(area/store)和category_id参数
 * @returns 排行榜数据
 */
export function getTechnicianRank(params: Record<string, any>) {
    return request.get(`home_service/technician/rank`, params)
}

/**
 * 师傅信息
 * @param params
 * @returns
 */
export function getTechnicianInfo(params: Record<string, any>) {
    return request.get(`home_service/technician/info`,params,{  showErrorMessage: false })
}

/**
 * 师傅信息(今日数据)
 * @param params
 * @returns
 */
export function getTechnicianTotadyInfo(params: Record<string, any>) {
    return request.get(`home_service/technician/statistics/todayData`,params)
}

/***************************************************** 师傅排班管理 ****************************************************/
/**
 * 获取师傅休息记录
 * @param params 包含 date 参数（格式：YYYY-MM）
 * @returns 休息记录列表
 */
export function getTechnicianRestRecords(params: Record<string, any>) {
    return request.get(`home_service/technician/rest`, params)
}

/**
 * 提交师傅休息记录
 * @param params 包含 date（日期）、hour（时间段）、notes（备注）等参数
 * @returns 提交结果
 */
export function submitTechnicianRestRecord(params: Record<string, any>) {
    return request.post(`home_service/technician/rest`, params, { showSuccessMessage: true })
}

/**
 * 取消师傅休息记录
 * @param params 包含 date（日期）参数
 * @returns 取消结果
 */
export function cancelTechnicianRestRecord(params: Record<string, any>) {
    return request.post(`home_service/technician/cancelrest`, params, { showSuccessMessage: true })
}

/**
 * 获取请假理由列表
 * @returns 请假理由列表
 */
export function getTechnicianRestReasons() {
    return request.get(`home_service/technician/restreason`)
}


/**
 * 编辑师傅信息
 * @param params 包含headimg(头像)、intro(简介)、mobile(手机号)、real_name(姓名)等参数
 * @returns
 */
export function editTechnicianInfo(params: Record<string, any>) {
    return request.put(`home_service/technician/edit`, params, { showSuccessMessage: true })
}



