import request from '@/utils/request'

/***************************************************** 售后表 ****************************************************/

/**
 * 获取师傅统计列表
 * @param params
 * @returns
 */
export function getTechnicianReport(params: Record<string, any>) {
    return request.get(`home_service/statistics/technician`, {params})
}

/**
 * 获取门店统计列表
 * @param params
 * @returns
 */
export function getStoreReport(params: Record<string, any>) {
    return request.get(`home_service/statistics/store`, {params})
}

											// 财务报表接口
/**
 * 财务报表-收入趋势
 * @param params
 * @returns
 */
export function getIncomeTrend(params: Record<string, any>) {
    return request.get(`home_service/statistics/finance/income_trend`, {params})
}

/**
 * 财务报表-门店收入趋势
 * @param params
 * @returns
 */
export function getStoreIncomeTrend(params: Record<string, any>) {
    return request.get(`home_service/statistics/finance/store_income_trend`, {params})
}

/**
 * 获取门店统计列表
 * @param params
 * @returns
 */
export function getTechnicianIncomeTrend(params: Record<string, any>) {
    return request.get(`home_service/statistics/finance/technician_income_trend`, {params})
}

/**
 * 财务报表-售后支出趋势
 * @param params
 * @returns
 */
export function getRefundExpenditure(params: Record<string, any>) {
    return request.get(`home_service/statistics/finance/refund_expenditure`, {params})
}

/**
 * 财务报表-收支盈利分析
 * @param params
 * @returns
 */
export function getReceiptExpenditure(params: Record<string, any>) {
    return request.get(`home_service/statistics/finance/receipt_expenditure`, {params})
}

/**
 * 客户报表
 * @param params
 * @returns
 */
export function getMemberStats(params: Record<string, any>) {
    return request.get(`home_service/statistics/finance/member_stats`, {params})
}