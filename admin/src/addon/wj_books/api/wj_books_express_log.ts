import request from '@/utils/request'

/**
 * 获取物流回调日志列表
 * @param params
 * @returns
 */
export function getWjBooksExpressLogLists(params: Record<string, any>) {
  return request.get('wj_books/wj_books_express_log/lists', { params })
}

/**
 * 获取物流回调日志详情
 * @param id
 * @returns
 */
export function getWjBooksExpressLogInfo(id: number) {
  return request.get(`wj_books/wj_books_express_log/info/${id}`)
}

/**
 * 删除物流回调日志
 * @param id
 * @returns
 */
export function deleteWjBooksExpressLog(id: number) {
  return request.delete(`wj_books/wj_books_express_log/del/${id}`)
}

/**
 * 清空物流回调日志
 * @returns
 */
export function clearWjBooksExpressLog() {
  return request.post('wj_books/wj_books_express_log/clear')
} 