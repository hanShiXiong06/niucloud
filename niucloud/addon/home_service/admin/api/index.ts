import request from '@/utils/request'

/***************************************************** 售后表 ****************************************************/

/**
 * 基础数据
 * @param params
 * @returns
 */
export function getBasicData(params: Record<string, any>) {
    return request.get(`home_service/statistics/basicData`)
}


/**
 * 工单数据
 * @param params
 * @returns
 */
export function getWorkOrderData(params: Record<string, any>) {
    return request.get(`home_service/statistics/workOrderData`)
}


/**
 * 待办总览
 * @param params
 * @returns
 */
export function getWorkTodoData(params: Record<string, any>) {
    return request.get(`home_service/statistics/todoData`)
}


/**
 * 交易趋势
 * @param params
 * @returns
 */
export function tradingTrendData(params: Record<string, any>) {
    return request.get(`home_service/statistics/tradingTrend`,{params})
}



/**
 * 服务类型占比
 * @param params
 * @returns
 */
export function categoryRatedData(params: Record<string, any>) {
    return request.get(`home_service/statistics/categoryRate`)
}


/**
 * 热门服务排行
 * @param params
 * @returns
 */
export function popularServiceRank(params: Record<string, any>) {
    return request.get(`home_service/statistics/popularServiceRank`,params)
}



/**
 * 师傅排行榜
 * @param params
 * @returns
 */
export function technicianRankData(params: Record<string, any>) {
    return request.get(`home_service/statistics/technicianRank`,{params})
}
