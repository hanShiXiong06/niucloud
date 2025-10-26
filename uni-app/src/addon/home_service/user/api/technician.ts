import request from '@/utils/request'

/***************************************************** 师傅 ****************************************************/
/**
 * 获取师傅列表(分页)
 * @returns
 */
export function getTechnicianList(params: Record<string, any>) {
    return request.get(`home_service/technician`, params)
}

/**
 * 获取师傅列表(不分页)
 * @returns
 */
export function getTechnicianListTo() {
    return request.get(`home_service/technician/list`)
}

/**
 * 获取师傅列表支持商品
 * @param id
 * @returns
 */
export function getTechnicianGoods(id: number) {
    return request.get(`home_service/technician/goods/${ id }`)
}

/**
 * 师傅详情
 * @param id
 * @returns
 */
export function getTechnicianDetail(id: number) {
    return request.get(`home_service/technician/${ id }`)
}

/**
 * 验证是否是师傅
 */
export function checkTechnician() {
    return request.get(`home_service/technician/checkTechnician`)
}

/**
 * 师傅入驻申请提交
 * @param params
 * @returns
 */
export function submitTechnicianApply(params: Record<string, any>) {
    return request.post(`home_service/technician/apply`, params, { showSuccessMessage: true })
}

/**
 * 获取师傅入驻申请状态
 * @returns
 */
export function getTechnicianApplyStatus() {
    return request.get(`home_service/technician/apply/status`)
}

/**
 * 获取师傅服务品类列表
 * @returns
 */
export function getTechnicianCategories() {
    return request.get(`home_service/technician/categories`)
}