import request from '@/utils/request'

/**
 * 获取发票配置
 */
export function getInvoiceConfig() {
	return request.get(`phone_shop/config/invoice`)
}