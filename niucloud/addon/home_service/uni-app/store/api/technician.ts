import request from '@/utils/request'

/**
 * 获取师傅分类
 * @param params
 * @returns
 */
export function getCategoryList() {
    return request.get(`home_service/category/list`)
}


/**
 * 获取师傅列表
 * @param params
 * @returns
 */
export function getTechnicianList(params: Record<string, any>) {
    return request.get(`home_service/store/technician`,params)
}


/**
 * 获取师傅详情
 * @param params
 * @returns
 */
export function getTechnicianDetail(id: number) {
    return request.get(`home_service/store/technician/${id}`)
}



/**
 * 获取师傅在线状态
 * @param params
 * @returns
 */
export function getTechnicianStatus(id: number) {
    return request.get(`home_service/store/technician/status`)
}





/***************************************************** 师傅排班管理 ****************************************************/
/**
 * 获取师傅休息记录
 * @param params 包含 date 参数（格式：YYYY-MM）
 * @returns 休息记录列表
 */
export function getTechnicianRestRecords(params: Record<string, any>) {
    return request.get(`home_service/store/technician/rest`, params)
}

/**
 * 提交师傅休息记录
 * @param params 包含 date（日期）、hour（时间段）、notes（备注）等参数
 * @returns 提交结果
 */
export function submitTechnicianRestRecord(params: Record<string, any>) {
    return request.post(`home_service/store/technician/rest`, params, { showSuccessMessage: true })
}

/**
 * 取消师傅休息记录
 * @param params 包含 date（日期）参数
 * @returns 取消结果
 */
export function cancelTechnicianRestRecord(params: Record<string, any>) {
    return request.post(`home_service/store/technician/cancelrest`, params, { showSuccessMessage: true })
}

/**
 * 获取请假理由列表
 * @returns 请假理由列表
 */
export function getTechnicianRestReasons() {
    return request.get(`home_service/store/technician/restreason`)
}



/**
 * 师傅比例设置
 * @returns 请假理由列表
 */
export function setRate(params: Record<string, any>) {
    return request.post(`home_service/store/technician/rate`,params, { showSuccessMessage: true })
}



