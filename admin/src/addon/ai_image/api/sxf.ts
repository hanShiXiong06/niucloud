
import request from '@/utils/request'

export function getConfig() {
    return request.get('ai_image/config/getsxfconfig')
}

/**
 *配置修改
 * @param params
 */
export function setConfig(params: Record<string, any>) {
    return request.post(`ai_image/config/setsxfconfig`, params, { showSuccessMessage: true })
}

