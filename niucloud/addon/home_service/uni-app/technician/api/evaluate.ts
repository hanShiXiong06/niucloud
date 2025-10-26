import request from '@/utils/request'

/***************************************************** 师傅订单管理 ****************************************************/


/**
 * 获取服务评价列表
 * @param params 查询参数
 * @returns 评价列表数据（包含总数）
 */
export function getEvaluateList(params: Record<string, any>) {
    return request.get('home_service/technician/evaluate', params)
}