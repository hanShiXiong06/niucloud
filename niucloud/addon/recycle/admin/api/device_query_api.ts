import request from '@/utils/request'

/**
 * 获取设备查询API接口清单列表
 * @param params
 * @returns
 */
export function getDeviceQueryApiList(params: Record<string, any>) {
    return request.get(`recycle/device_query_api/lists`, { params })
}

/**
 * 获取设备查询API接口清单详情
 * @param id
 * @returns
 */
export function getDeviceQueryApiInfo(id: number) {
    return request.get(`recycle/device_query_api/${id}`)
}

/**
 * 添加设备查询API接口清单
 * @param params
 * @returns
 */
export function addDeviceQueryApi(params: Record<string, any>) {
    return request.post('recycle/device_query_api', params)
}

/**
 * 编辑设备查询API接口清单
 * @param id
 * @param params
 * @returns
 */
export function editDeviceQueryApi(id: number, params: Record<string, any>) {
    return request.put(`recycle/device_query_api/${id}`, params)
}

/**
 * 删除设备查询API接口清单
 * @param id
 * @returns
 */
export function deleteDeviceQueryApi(id: number) {
    return request.delete(`recycle/device_query_api/${id}`)
}

/**
 * 修改设备查询API接口清单状态
 * @param id
 * @param status
 * @returns
 */
export function updateDeviceQueryApiStatus(id: number, status: number) {
    return request.put(`recycle/device_query_api/status/${id}`, { status })
}

/**
 * 获取默认API清单
 * @returns
 */
export function getDefaultApiList() {
    return request.get('recycle/device_query_api/default')
}

/**
 * 初始化默认API清单
 * @returns
 */
export function initDefaultApiList() {
    return request.post('recycle/device_query_api/init')
}

/**
 * 根据端点获取API信息
 * @param endpoint
 * @returns
 */
export function getApiByEndpoint(endpoint: string) {
    return request.get('recycle/device_query_api/by_endpoint', { params: { endpoint } })
}

/**
 * 获取分类下的API列表
 * @param category
 * @returns
 */
export function getApisByCategory(category: string = '') {
    return request.get('recycle/device_query_api/by_category', { params: { category } })
} 

// getCoverage
export function getCoverage(params: Record<string, any>) {
    return request.get('recycle/device_query_api/coverage', { params })

}
// getActivationlock
export function getActivationlock(imei :string = ''){
    return request.get('recycle/device_query_api/activationlock', { params: { imei } })

}

// getMdm
export function getMdm(imei :string = ''){
    return request.get('recycle/device_query_api/mdm', { params: { imei } })
}

// 查询快递的物流信息
export function getExpress(express_code: string = '', mobile: string = '') {
     return request.get('recycle/device_query_api/express', { params: { express_code, mobile } })
}
    




