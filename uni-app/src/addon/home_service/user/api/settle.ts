import request from '@/utils/request'

/**
 * 获取入驻分类
 * @param params
 * @returns
 */
export function getSettleCategoryList(params: Record<string, any>) {
    return request.get(`home_service/category/list`, params)
}

/**
 * 获取可入驻门店
 * @param params
 * @returns
 */
export function getSettleStoreList(params: Record<string, any>) {
    return request.get(`home_service/technician/store/list`, params)
}

/**
 * 师傅入驻申请
 * @param params 入驻申请参数
 * @returns
 */
export function applyTechnician(params: Record<string, any>) {
    return request.post(`home_service/technician/apply`, params, { showErrorMessage: true })
}

/**
 * 门店入驻申请
 * @param params 门店入驻申请参数
 * @returns
 */
export function applyStore(params: Record<string, any>) {
    return request.post(`home_service/member/store/application`, params, { showErrorMessage: true })
}

/**
 * 重新提交门店入驻申请（编辑功能）
 * @param id 门店ID
 * @param params 门店入驻申请参数
 * @returns
 */
export function reapplyStore(id: number, params: Record<string, any>) {
    return request.put(`home_service/member/store/application/${id}`, params, { showErrorMessage: true })
}


/**
 * 师傅入驻申请资料
 * @param params 师傅入驻申请参数
 * @returns
 */
export function getTechnicianApply() {
    return request.get(`home_service/technician/apply`)
}


/**
 * 门店入驻申请资料
 * @param params 门店入驻申请参数
 * @returns
 */
export function getStoreApply() {
    return request.get(`home_service/member/store/application`)
}

