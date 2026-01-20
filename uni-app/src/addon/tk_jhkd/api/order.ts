import request from '@/utils/request'

/**
 * 获取订单列表
 */
export function getOrderList(data : AnyObject) {
	return request.get('tk_jhkd/getorderlist', data)
}
/**
 * 获取订单状态列表
 */
export function getOrderStatus() {
	return request.get('tk_jhkd/getorderstatus')
}
/**
 * 获取订单信息
 */
export function getOrderDetail(id : number) {
	return request.get(`tk_jhkd/getorderinfo/${id}`)
}
/**
 * 关闭订单
 */
export function closeOrder(id : number) {
	return request.get(`tk_jhkd/closeorder/${id}`)
}
/**
 * 获取订单轨迹
 */
export function getDeliveryInfo(deliveryid) {
	return request.get(`tk_jhkd/getdeliveryinfo/${deliveryid}`)
}
/**
 * 删除订单
 */
export function deleteOrder(id : number) {
	return request.delete(`tk_jhkd/deleteorder/${id}`, { showSuccessMessage: true, showErrorMessage: true })
}
/**
 * 申请退款
 */
export function applyRefund(data : AnyObject) {
	return request.post('tk_jhkd/applyrefund', data, { showSuccessMessage: true, showErrorMessage: true })
}