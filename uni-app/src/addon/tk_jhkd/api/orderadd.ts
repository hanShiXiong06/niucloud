import request from '@/utils/request'

/**
 * 获取订单列表
 */
export function getOrderList(data : AnyObject) {
	return request.get('tk_jhkd/getorderaddlist', data)
}
/**
 * 获取订单状态列表
 */
export function getOrderStatus() {
	return request.get('tk_jhkd/getorderaddstatus')
}

/**
 * 删除订单
 */
export function deleteOrder(id : number) {
	return request.delete(`tk_jhkd/deleteorderadd/${id}`, { showSuccessMessage: true, showErrorMessage: true })
}
export function checkAddPay() {
	return request.get('tk_jhkd/checkaddpay')
}