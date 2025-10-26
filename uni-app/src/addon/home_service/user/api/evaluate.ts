import request from '@/utils/request'

/**
 * 提交服务评价
 */
export function submitEvaluate(params: Record<string, any>) {
    return request.post(`home_service/order/evaluate`, params, { showErrorMessage: true, showSuccessMessage: true })
}



/**
 * 获取服务评价列表
 * @param params 查询参数
 * @returns 评价列表数据（包含总数）
 */
export function getEvaluateList(params: Record<string, any>) {
    return request.get('home_service/order/evaluate', params)
}