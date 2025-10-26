import request from '@/utils/request'



/**
 * 获取师傅帮助中心数据
 * @param params 查询参数
 * @returns 帮助中心分类和问题列表
 */
export function getHelpData(params?: Record<string, any>) {
    return request.get(`home_service/store/help`, params)
}

/**
 * 获取师傅帮助中心详情
 * @param helpId 帮助文档ID
 * @returns 帮助文档详情
 */
export function getHelpDetail(helpId: number) {
    return request.get(`home_service/store/help/info`, { help_id: helpId })
}


/**
 * 提交师傅反馈
 * @param params 反馈数据，包含标题、内容和图片
 * @returns 提交结果
 */
export function submitFeedback(params: Record<string, any>) {
    return request.post(`home_service/store/feedback`, params, { showSuccessMessage: true })
}



/**
 * 获取师傅动态数据
 * @param 
 * @returns 
 */
export function getTechnicianDynamic(helpId: number) {
    return request.get(`home_service/store/statistics/technicianDynamic`, { help_id: helpId })
}




/**
 * 获取提现记录列表
 */
export function getCashOutList(data: AnyObject) {
    return request.get(`home_service/store/cash_out`, data)
}

/**
 * 获取提现记录详情
 */
export function getCashOutDetail(id: number) {
    return request.get(`home_service/store/cash_out/${ id }`)
}


/**
 * 获取提现转账
 */
export function getCashOutTransfer(data: AnyObject) {
    return request.post(`home_service/store/cash_out/transfer_type/${ data.id }`, data)
}



/**
 * 会员取消提现
 * @param params
 */
export function memberCancel(params: Record<string, any>) {
    return request.put(`home_service/store/cash_out/cancel/${params.id}`, params, { showSuccessMessage: true,showErrorMessage: true })
}