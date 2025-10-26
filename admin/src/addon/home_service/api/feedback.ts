import request from '@/utils/request'

/***************************************************** 反馈表 ****************************************************/

/**
 * 获取反馈表列表
 * @param params
 * @returns
 */
export function getfeedbackList(params: Record<string, any>) {
    return request.get(`home_service/feedback`, {params})
}

/**
 * 获取反馈全部分类
 * @param params
 * @returns
 */
export function getfeedbackCategoryAll(params: Record<string, any>) {
    return request.get(`home_service/feedback/source`, params)
}
