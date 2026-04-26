import request from '@/utils/request'

/**
 * 创建回收订单
 * @param {Object} data 订单数据
 * @returns {Promise}
 */
export function createOrder(data: Record<string, any>) {
  return request.post('wj_books/order/create', data)
}

/**
 * 获取订单详情
 * @param {Object} params 查询参数
 * @returns {Promise}
 */
export function getOrderDetail(params: Record<string, any>) {
  return request.get('wj_books/order/detail', params)
}

/**
 * 获取订单列表
 * @param {Object} params 查询参数
 * @returns {Promise}
 */
export function getOrderList(params: Record<string, any>) {
  return request.get('wj_books/order/list', params)
}

/**
 * 取消订单
 * @param {Object} data 取消参数
 * @returns {Promise}
 */
export function cancelOrder(data: Record<string, any>) {
  return request.post('wj_books/order/cancel', data)
}

/**
 * 删除订单
 * @param {Object} data 删除参数
 * @returns {Promise}
 */
export function deleteOrder(data: Record<string, any>) {
  return request.post('wj_books/order/delete', data)
}

/**
 * 获取物流信息
 * @param {Object} params 查询参数
 * @returns {Promise}
 */
export function getExpressInfo(params: Record<string, any>) {
  return request.get('wj_books/order/express', params)
}

/**
 * 申请取回不合格书籍
 * @param {Object} data 申请参数
 * @returns {Promise}
 */
export function applyRetrieve(data: Record<string, any>) {
  return request.post('wj_books/order/retrieve', data)
} 