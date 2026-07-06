import request from '@/utils/request'

export function getErpConfig() {
    return request.get('erp/config')
}

export function saveErpConfig(data: Record<string, any>) {
    return request.post('erp/config', data)
}
