import request from '@/utils/request'


// USER_CODE_BEGIN -- hsx_phone_query_config
/**
 * 获取配置信息列表
 * @param params
 * @returns
 */
export function getHsxPhoneQueryConfigList(params: Record<string, any>) {
    return request.get(`hsx_phone_query/hsx_phone_query_config`, {params})
}

/**
 * 获取服务商配置
 */
export function getHsxPhoneQueryProviderConfig() {
    return request.get(`hsx_phone_query/hsx_phone_query_config/provider`)
}

/**
 * 保存服务商配置
 */
export function saveHsxPhoneQueryProviderConfig(params: Record<string, any>) {
    return request.post(`hsx_phone_query/hsx_phone_query_config/provider`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 获取配置信息详情
 * @param id 配置信息id
 * @returns
 */
export function getHsxPhoneQueryConfigInfo(id: number) {
    return request.get(`hsx_phone_query/hsx_phone_query_config/${id}`);
}

/**
 * 添加配置信息
 * @param params
 * @returns
 */
export function addHsxPhoneQueryConfig(params: Record<string, any>) {
    return request.post('hsx_phone_query/hsx_phone_query_config', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑配置信息
 * @param id
 * @param params
 * @returns
 */
export function editHsxPhoneQueryConfig(params: Record<string, any>) {
    return request.put(`hsx_phone_query/hsx_phone_query_config/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除配置信息
 * @param id
 * @returns
 */
export function deleteHsxPhoneQueryConfig(id: number) {
    return request.delete(`hsx_phone_query/hsx_phone_query_config/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}



// USER_CODE_END -- hsx_phone_query_config
