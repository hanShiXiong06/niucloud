import request from '@/utils/request'
/**
 * 获取平台商户配置
 * @return 
 */
export function getConfig() {
	return request.get(`fast_pay/config/getconfig`)
}
/**
 * 获取商户配置
 * @param {any} id 
 * @return 
 */
export function getBusinessConfig(id) {
	return request.get(`fast_pay/config/getbusinessconfig/${id}`)
}