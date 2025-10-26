import request from '@/utils/request'

/***************************************************** 师傅列表 ****************************************************/
/**
 * 获取师傅列表状态
 * @returns
 *
 */
export function getTechnicianStatus() {
    return request.get(`home_service/technician/order/status`);
}

/**
 * 获取师傅订单列表(分页)
 * @returns
 */
export function getTechnicianOrder(params: Record<string, any>) {
    return request.get(`home_service/technician/order`, params)
}

/**
 * 获取师傅订单详情
 * @param orderId
 * @returns
 *
 */
export function getTechnicianOrderDetail(orderId: number) {
    return request.get(`home_service/technician/order/detail/${ orderId }`);
}

/**
 * 订单转单
 */
export function TransferOrder(params: Record<string, any>) {
    return request.post(`home_service/technician/order/transfer`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 订单开始服务
 */
export function beginService(params: Record<string, any>) {
    return request.post(`home_service/technician/order/start`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 订单结束服务
 */
export function finishService(params: Record<string, any>) {
    return request.post(`home_service/technician/order/savecheck`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 订单添加服务项
 */
export function addService(params: Record<string, any>) {
    return request.post(`home_service/technician/order/addItem`, params, { showSuccessMessage: true })
}

/**
 * 订单编辑服务项
 */
export function editService(params: Record<string, any>) {
    return request.put(`home_service/technician/order/editItem`, params, { showSuccessMessage: true })
}

/**
 * 订单删除服务项
 */
export function deleteService(params: Record<string, any>) {
    return request.delete(`home_service/technician/order/delItem`, params, { showSuccessMessage: true })
}

/**
 * 获取师傅统计
 */
export function getStat() {
    return request.get(`home_service/technician/order/getNum`);
}
