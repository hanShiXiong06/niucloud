import request from '@/utils/request'

/**
 * 取回申请数据接口类型定义
 */
export interface RetrieveApply {
  id: number
  site_id: number
  order_id: number
  member_id: number
  address_id: number
  rejected_book_ids: string
  express_waybill?: string
  express_company?: string
  status: number
  remark?: string
  create_time: string
  ship_time?: string
  complete_time?: string
  order_no?: string
  member_name?: string
  member_mobile?: string
  address_info?: {
    name: string
    mobile: string
    full_address: string
  }
  rejected_books?: Array<{
    id: number
    book_id: number
    title: string
    author?: string
    img?: string
    isbn?: string
    rejected_quantity: number
    reject_reason: string
  }>
}

/**
 * 获取取回申请列表
 * @param params 查询参数
 * @returns {Promise}
 */
export function getRetrieveApplyList(params: Record<string, any>) {
  return request.get('wj_books/wj_books_retrieve_apply', { params })
}

/**
 * 获取取回申请详情
 * @param id 申请ID
 * @returns {Promise}
 */
export function getRetrieveApplyDetail(id: string | number) {
  return request.get(`wj_books/wj_books_retrieve_apply/${id}`)
}

/**
 * 更新取回申请状态
 * @param id 申请ID
 * @param data 状态数据
 * @returns {Promise}
 */
export function updateRetrieveApplyStatus(id: string | number, data: Record<string, any>) {
  return request.put(`wj_books/wj_books_retrieve_apply/${id}/status`, data)
}

/**
 * 发货处理
 * @param id 申请ID
 * @param data 发货数据
 * @returns {Promise}
 */
export function shipRetrieveApply(id: string | number, data: {express_company: string, express_waybill: string}) {
  return request.put(`wj_books/wj_books_retrieve_apply/${id}/ship`, data)
}

/**
 * 完成取回申请
 * @param id 申请ID
 * @returns {Promise}
 */
export function completeRetrieveApply(id: string | number) {
  return request.put(`wj_books/wj_books_retrieve_apply/${id}/complete`)
}

/**
 * 取消取回申请
 * @param id 申请ID
 * @param data 取消原因
 * @returns {Promise}
 */
export function cancelRetrieveApply(id: string | number, data: Record<string, any>) {
  return request.put(`wj_books/wj_books_retrieve_apply/${id}/cancel`, data)
}

/**
 * 删除取回申请
 * @param id 申请ID
 * @returns {Promise}
 */
export function deleteRetrieveApply(id: string | number) {
  return request.delete(`wj_books/wj_books_retrieve_apply/${id}`)
} 