import request from '@/utils/request'

/**
 * 获取设备查询配置列表
 * @param params
 * @returns
 */
export function getDeviceQueryConfigList(params: Record<string, any>) {
    return request.get(`recycle/device_query_config/lists`, { params })
}

export function getDeviceQueryConfigCenter() {
    return request.get('recycle/device_query_config/config')
}

/**
 * 获取设备查询配置详情
 * @param id
 * @returns
 */
export function getDeviceQueryConfigInfo(id: number | string) {
    return request.get(`recycle/device_query_config/${encodeURIComponent(String(id))}`)
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
export function editDeviceQueryConfig(id: number | string, params: Record<string, any>) {
    return request.put(`recycle/device_query_config/${encodeURIComponent(String(id))}`, params)
}

/**
 * 删除设备查询配置
 * @param id
 * @returns
 */
export function deleteDeviceQueryConfig(id: number | string) {
    return request.delete(`recycle/device_query_config/${encodeURIComponent(String(id))}`)
}

/**
 * 修改设备查询配置状态
 * @param id
 * @param status
 * @returns
 */
export function updateDeviceQueryConfigStatus(id: number | string, status: number) {
    return request.put(`recycle/device_query_config/status/${encodeURIComponent(String(id))}`, { status })
}

export function saveDeviceQueryConfigCenter(params: Record<string, any>) {
    return request.post('recycle/device_query_config/config', params)
}

/**
 * 测试API连接
 * @param id
 * @returns
 */
export function testDeviceQueryConnection(id: number | string, params: Record<string, any> = {}) {
    return request.post(`recycle/device_query_config/test/${encodeURIComponent(String(id))}`, params, { showErrorMessage: false })
}

/**
 * 获取查询统计
 * @param id
 * @param params
 * @returns
 */
export function getDeviceQueryStats(id: number | string, params: Record<string, any> = {}) {
    return request.get(`recycle/device_query_config/stats/${encodeURIComponent(String(id))}`, { params })
} 

export function getDeviceQueryChannelBalance(channelKey: number | string) {
    return request.get(`recycle/device_query_config/balance/${encodeURIComponent(String(channelKey))}`, { showErrorMessage: false })
}
