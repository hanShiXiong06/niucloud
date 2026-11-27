import request from '@/utils/request'

// ==================== 第三方配置中心 ====================

export function apiThirdPartyConfig() {
    return request.get('recycle/third_party_config')
}

export function apiThirdPartyConfigOverview() {
    return request.get('recycle/third_party_config/overview')
}

export function parseThirdPartyAddress(params: any) {
    return request.post('recycle/third_party/address_parse', params)
}

export function apiThirdPartyConfigSave(params: Record<string, any>) {
    return request.post('recycle/third_party_config', params)
}

export function apiThirdPartyConfigDefault() {
    return request.get('recycle/third_party_config/default')
}

export function apiThirdPartyConfigTest(capability: string) {
    return request.post('recycle/third_party_config/test', { capability }, { showErrorMessage: false })
}

// ==================== 第三方服务配置 ====================

/**
 * 获取第三方服务列表
 * @param params
 * @returns
 */
export function apiThirdPartyServiceLists(params: Record<string, any>) {
    return request.get(`recycle/third_party_service/lists`, { params })
}

/**
 * 获取第三方服务详情
 * @param id
 * @returns
 */
export function apiThirdPartyServiceInfo(id: number) {
    return request.get(`recycle/third_party_service/${id}`)
}

/**
 * 添加第三方服务
 * @param params
 * @returns
 */
export function apiThirdPartyServiceAdd(params: Record<string, any>) {
    return request.post('recycle/third_party_service', params)
}

/**
 * 编辑第三方服务
 * @param params
 * @returns
 */
export function apiThirdPartyServiceEdit(params: Record<string, any>) {
    return request.put(`recycle/third_party_service/${params.id}`, params)
}

/**
 * 删除第三方服务
 * @param id
 * @returns
 */
export function apiThirdPartyServiceDelete(id: number) {
    return request.delete(`recycle/third_party_service/${id}`)
}

/**
 * 修改第三方服务状态
 * @param id
 * @param params
 * @returns
 */
export function apiThirdPartyServiceStatus(id: number, params: Record<string, any>) {
    return request.put(`recycle/third_party_service/status/${id}`, params)
}

// ==================== API调用日志 ====================

/**
 * 获取API调用日志列表
 * @param params
 * @returns
 */
export function apiThirdPartyApiLogLists(params: Record<string, any>) {
    return request.get(`recycle/third_party_api_log/lists`, { params })
}

/**
 * 获取API调用日志详情
 * @param id
 * @returns
 */
export function apiThirdPartyApiLogInfo(id: number) {
    return request.get(`recycle/third_party_api_log/${id}`)
}

/**
 * 清理API调用日志
 * @param params
 * @returns
 */
export function apiThirdPartyApiLogClean(params: Record<string, any>) {
    return request.delete(`recycle/third_party_api_log/clean`, { data: params })
}

// ==================== 费用统计 ====================

/**
 * 获取费用统计列表
 * @param params
 * @returns
 */
export function apiThirdPartyCostStatsLists(params: Record<string, any>) {
    return request.get(`recycle/third_party_cost_stats/lists`, { params })
}
