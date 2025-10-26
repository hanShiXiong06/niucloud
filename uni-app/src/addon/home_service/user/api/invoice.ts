import request from '@/utils/request'

/**
 * 获取发票订单列表
 * @param params 查询参数，包含page、limit、status等
 * @returns Promise<{data: {total: number, per_page: number, current_page: number, last_page: number, data: Array<any>}, msg: string, code: number}>
 */
export function getInvoiceOrderList(params: Record<string, any>) {
  return request.get('home_service/member/invoice/order', params)
}

/**
 * 创建发票申请
 * @param params 发票申请参数
 * @returns Promise<any>
 */
export function createInvoice(params: Record<string, any>) {
  // 简化API调用，移除任何业务逻辑处理
  return request.post('home_service/member/invoice', params, { showErrorMessage: true })
}

/**
 * 获取发票详情
 * @param invoiceId 发票ID
 * @returns Promise<any>
 */
export function getInvoiceDetail(invoiceId: number) {
  return request.get(`home_service/member/invoice/${invoiceId}`)
}

/**
 * 获取发票记录列表
 * @param params 查询参数，包含page、limit、status等  member/invoice
 * @returns Promise<any>
 */
export function getInvoiceList(params: Record<string, any>) {
  return request.get('home_service/member/invoice', params)
}

/**
 * 获取发票类型
 * @returns Promise<any>
 */
export function getInvoiceTypes() {
  return request.get('home_service/member/invoice/type')
}

/**
 * 获取发票内容选项
 * @returns Promise<any>
 */
export function getInvoiceContents() {
  return request.get('home_service/member/invoice/content')
}

/**
 * 获取发票抬头类型
 * @returns Promise<any>
 */
export function getInvoiceHeaderTypes() {
  return request.get('home_service/member/invoice/header_type')
}