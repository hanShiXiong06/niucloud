import request from '@/utils/request'

export function getCheckTemplateAll(params: Record<string, any> = {}) {
    return request.get('recycle/check_template/all', params)
}

export function getCheckTemplateSchema(params: Record<string, any> = {}) {
    return request.get('recycle/check_template/schema', params)
}
