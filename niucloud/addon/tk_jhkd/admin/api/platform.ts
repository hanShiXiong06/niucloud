import request from '@/utils/request'

/***************************************************** 平台管理 ****************************************************/


/**
 * 配置列表
 * @returns
 */
export function getPlatformList() {
    return request.get('tk_jhkd/platform/list')
}

/**
 * 配置详情
 * @param sms_type
 * @returns
 */
export function getPlatformInfo(type: string) {
    return request.get(`tk_jhkd/platform/config/${type}`,)
}

/**
 * 配置修改
 * @param params
 */
export function editPlatform(params: Record<string, any>) {
    return request.put(`tk_jhkd/platform/config/${params.type}`, params, { showSuccessMessage: true })
}
