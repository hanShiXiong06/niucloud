import request from '@/utils/request'

/***************************************************** hello world ****************************************************/
export function getHelloWorld() {
    return request.get(`recycle/hello_world`)
}

/**
 * 获取质检员设备数量统计
 * @returns
 */
export function getStaffCount(params?: Record<string, any>) {
    return request.get(`recycle/recycle_order/staff_count`, { params })
}

/**
 * 获取估价员（价格确认员）绩效统计
 * @returns
 */
export function getPriceConfirmerPerformance(params?: Record<string, any>) {
    return request.get(`recycle/recycle_order/price_confirmer_performance`, { params })
}