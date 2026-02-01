import request from '@/utils/request'

/**
 * 支付
 */
export function pay(data : AnyObject) {
	return request.post(`fast_pay/pay`, data, { showErrorMessage: true })
}
export function partnerpay(data : AnyObject) {
	return request.post(`fast_pay/partnerpay`, data, { showErrorMessage: true })
}
export function businesspay(data : AnyObject) {
	return request.post(`fast_pay/businesspay`, data, { showErrorMessage: true })
}
/**
 * 获取支付信息
 */
export function getPayInfo(tradeType : string, tradeId : number) {
	return request.get(`fast_pay/info/${tradeType}/${tradeId}`, {}, { showErrorMessage: true })
}
/**
 * 支付订单创建
 */
export function createOrder(data : AnyObject) {
	return request.post(`fast_pay/order/create`, data, { showErrorMessage: true })
}
export function createBusinessOrder(data : AnyObject) {
	return request.post(`fast_pay/business/order/create`, data, { showErrorMessage: true })
}
export function getOrderList(param) {
	return request.get(`fast_pay/order/lists`, param)
}