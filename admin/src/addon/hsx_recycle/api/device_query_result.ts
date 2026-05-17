import request from '@/utils/request'

/**
 * 获取设备查询结果列表
 * @param params
 * @returns
 */
export function getDeviceQueryResultList(params: Record<string, any>) {
    return request.get(`recycle/device_query_result/lists`, { params })
}

/**
 * 获取设备查询结果详情
 * @param id
 * @returns
 */
export function getDeviceQueryResultInfo(id: number) {
    return request.get(`recycle/device_query_result/${id}`)
}

/**
 * 删除设备查询结果
 * @param id
 * @returns
 */
export function deleteDeviceQueryResult(id: number) {
    return request.delete(`recycle/device_query_result/${id}`)
}

/**
 * 批量删除设备查询结果
 * @param ids
 * @returns
 */
export function batchDeleteDeviceQueryResult(ids: number[]) {
    return request.post('recycle/device_query_result/batch_del', { ids })
}

/**
 * 获取查询统计
 * @param params
 * @returns
 */
export function getDeviceQueryResultStats(params: Record<string, any> = {}) {
    return request.get('recycle/device_query_result/stats', { params })
}

/**
 * 清理过期缓存
 * @param expireTime
 * @returns
 */
export function cleanDeviceQueryCache(expireTime: number = 604800) {
    return request.post('recycle/device_query_result/clean_cache', { expire_time: expireTime })
}

/**
 * 重新查询
 * @param id
 * @returns
 */
export function requeryDeviceQuery(id: number) {
    return request.post(`recycle/device_query_result/requery/${id}`)
}

/**
 * 导出查询结果
 * @param params
 * @returns
 */
export function exportDeviceQueryResult(params: Record<string, any> = {}) {
    return request.post('recycle/device_query_result/export', params)
} 



/**
 * 查询当前站点的总消费
 * @param params
 * @returns
 */
export function getTotalConsumption(params: Record<string, any> = {}) {
    return request.get('recycle/device_query_result/total_consumption', { params })
}