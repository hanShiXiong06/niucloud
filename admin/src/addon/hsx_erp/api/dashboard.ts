import request from '@/utils/request'

/**
 * 运营看板数据
 * @param params { start, end, warehouse_id }
 */
export function getErpDashboard(params: Record<string, any> = {}) {
    return request.get('erp/dashboard/data', { params })
}
