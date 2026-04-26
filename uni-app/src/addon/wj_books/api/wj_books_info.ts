import request from '@/utils/request'

/**
 * 通过ISBN查询图书信息
 * @param {Object} params 参数
 * @returns {Promise}
 */
export function queryBookInfo(params: Record<string, any>) {
  // 确保scan_type是整数类型
  const scanType = parseInt(params.scan_type);
  // 直接拼接参数到URL中，避免被包装成params[xxx]的形式
  return request.get(`wj_books/books/query?isbn=${params.isbn}&scan_type=${scanType}`)
} 

/**
 * 获取系统配置
 * @returns {Promise}
 */
export function getConfig() {
  return request.get('wj_books/config')
} 

 