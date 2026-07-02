import request from '@/utils/request'

export function getErpConfig() {
    return request.get('erp/config')
}
