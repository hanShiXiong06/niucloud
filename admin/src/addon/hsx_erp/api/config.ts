import request from '@/utils/request'

// ERP / 财务 设置:总开关、现结开关
export function getErpConfig() {
    return request.get('erp/config')
}

export function saveErpConfig(data: Record<string, any>) {
    return request.post('erp/config', data, { showSuccessMessage: true })
}
