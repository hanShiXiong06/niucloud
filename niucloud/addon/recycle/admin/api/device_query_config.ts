import request from '@/utils/request'

/**
 * 获取设备查询配置列表
 * @param params
 * @returns
 */
export function getDeviceQueryConfigList(params: Record<string, any>) {
    return request.get(`recycle/device_query_config/lists`, { params })
}

/**
 * 获取设备查询配置详情
 * @param id
 * @returns
 */
export function getDeviceQueryConfigInfo(id: number) {
    return request.get(`recycle/device_query_config/${id}`)
}

/**
 * 添加设备查询配置
 * @param params
 * @returns
 */
export function addDeviceQueryConfig(params: Record<string, any>) {
    return request.post('recycle/device_query_config', params)
}

/**
 * 编辑设备查询配置
 * @param id
 * @param params
 * @returns
 */
export function editDeviceQueryConfig(id: number, params: Record<string, any>) {
    return request.put(`recycle/device_query_config/${id}`, params)
}

/**
 * 删除设备查询配置
 * @param id
 * @returns
 */
export function deleteDeviceQueryConfig(id: number) {
    return request.delete(`recycle/device_query_config/${id}`)
}

/**
 * 修改设备查询配置状态
 * @param id
 * @param status
 * @returns
 */
export function updateDeviceQueryConfigStatus(id: number, status: number) {
    return request.put(`recycle/device_query_config/status/${id}`, { status })
}

/**
 * 测试API连接
 * @param id
 * @returns
 */
export function testDeviceQueryConnection(id: number) {
    return request.post(`recycle/device_query_config/test/${id}`)
}

/**
 * 获取查询统计
 * @param id
 * @param params
 * @returns
 */
export function getDeviceQueryStats(id: number, params: Record<string, any> = {}) {
    return request.get(`recycle/device_query_config/stats/${id}`, { params })
} 