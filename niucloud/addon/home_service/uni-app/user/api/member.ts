import request from '@/utils/request'

/**
 * 次卡套餐
 * @param orderId
 * @returns
 */
export function getFirstCard(orderId: number) {
    return request.get(`home_service/member/firstCard`)
}

/**
 * 获取优惠券、次卡
 * @param orderId
 * @returns
 */
export function getCouponCard(orderId: number) {
    return request.get(`home_service/member/memberDiscountCount`)
}



/**
 * 获取用户浏览历史记录
 * @param params 查询参数(page: 页码, limit: 每页数量等)
 * @returns 浏览历史记录列表
 */
export function getBrowseHistory(params?: Record<string, any>) {
    return request.get(`home_service/goods/browse`, params)
}

/**
 * 删除用户浏览历史记录
 * @param params 删除参数，包含goods_ids数组
 * @returns 删除结果
 */
export function deleteBrowseHistory(params: Record<string, any>) {
    return request.delete(`home_service/goods/browse`, params)
}






/**
 * 获取师傅帮助中心数据
 * @param params 查询参数
 * @returns 帮助中心分类和问题列表
 */
export function getTechnicianHelpData(params?: Record<string, any>) {
    return request.get(`home_service/member/help`, params)
}

/**
 * 获取师傅帮助中心详情
 * @param helpId 帮助文档ID
 * @returns 帮助文档详情
 */
export function getTechnicianHelpDetail(helpId: number) {
    return request.get(`home_service/member/help/info`, { help_id: helpId })
}

/**
 * 提交反馈
 * @param params 反馈数据，包含标题、内容和图片
 * @returns 提交结果
 */
export function submitFeedback(params: Record<string, any>) {
    return request.post(`home_service/member/feedback`, params, { showSuccessMessage: true })
}