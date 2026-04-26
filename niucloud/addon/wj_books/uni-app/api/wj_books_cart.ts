import request from '@/utils/request'

/**
 * 添加图书到回收车
 * @param {Object} data 参数
 * @returns {Promise}
 */
export function addToCart(data: Record<string, any>) {
  return request.post('wj_books/cart/add', data)
}

/**
 * 获取回收车列表
 * @returns {Promise}
 */
export function getCartList() {
  return request.get('wj_books/cart/list')
}

/**
 * 从回收车中移除图书
 * @param {Object} data 参数，包含cart_id、scan_id和status
 * @returns {Promise}
 */
export function removeFromCart(data: Record<string, any>) {
  return request.post('wj_books/cart/remove', data)
}

/**
 * 提交回收
 * @returns {Promise}
 */
export function submitRecycle() {
  return request.post('wj_books/cart/submit')
}

/**
 * 更新回收书籍数量
 * @param {Object} data 参数，包含cart_id和quantity
 * @returns {Promise}
 */
export function updateCartQuantity(data: Record<string, any>) {
  return request.post('wj_books/cart/update_quantity', data)
} 