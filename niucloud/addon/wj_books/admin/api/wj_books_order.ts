import request from '@/utils/request'

// USER_CODE_BEGIN -- wj_books_order
/**
 * 获取订单列表
 * @param params
 * @returns
 */
export function getWjBooksOrderList(params: Record<string, any>) {
    return request.get(`wj_books/wj_books_order`, {params})
}

/**
 * 获取订单详情
 * @param id 订单id
 * @returns
 */
export function getWjBooksOrderInfo(id: number) {
    return request.get(`wj_books/wj_books_order/${id}`);
}

/**
 * 更新订单状态
 * @param id 订单id
 * @param status 状态值
 * @returns
 */
export function updateWjBooksOrderStatus(id: number, status: number) {
    return request.put(`wj_books/wj_books_order/status`, { id, status }, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 更新订单审核进度
 * @param id 订单id
 * @param progress 进度值(0-100)
 * @returns
 */
export function updateWjBooksOrderAuditProgress(id: number, progress: number) {
    return request.put(`wj_books/wj_books_order/audit_progress`, { id, audit_progress: progress }, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 获取订单书籍列表
 * @param order_id 订单id
 * @returns
 */
export function getWjBooksOrderBookList(order_id: number) {
    return request.get(`wj_books/wj_books_order_book`, { params: { order_id } })
}

/**
 * 更新订单书籍最终价格
 * @param id 订单书籍id
 * @param final_price 最终价格
 * @param accepted_quantity 接收数量
 * @returns
 */
export function updateWjBooksOrderBookPrice(id: number, final_price: number, accepted_quantity: number) {
    return request.put(`wj_books/wj_books_order_book/price`, { id, final_price, accepted_quantity }, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 添加拒收书籍
 * @param params
 * @returns
 */
export function addWjBooksRejectedBook(params: Record<string, any>) {
    return request.post('wj_books/wj_books_rejected_book', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 获取拒收书籍列表
 * @param order_id 订单id
 * @returns
 */
export function getWjBooksRejectedBookList(order_id: number) {
    return request.get(`wj_books/wj_books_rejected_book`, { params: { order_id } })
}

/**
 * 上传拒收书籍审核图片
 * @param params
 * @returns
 */
export function uploadWjBooksRejectedImage(params: FormData) {
    return request.post('wj_books/wj_books_rejected_images/upload', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 获取拒收书籍审核图片列表
 * @param rejected_book_id 拒收书籍id
 * @returns
 */
export function getWjBooksRejectedImageList(rejected_book_id: number) {
    return request.get(`wj_books/wj_books_rejected_images`, { params: { rejected_book_id } })
}

/**
 * 获取取回申请列表
 * @param params
 * @returns
 */
export function getWjBooksRetrieveApplyList(params: Record<string, any>) {
    return request.get(`wj_books/wj_books_retrieve_apply`, { params })
}

/**
 * 更新取回申请状态
 * @param id 申请id
 * @param status 状态值
 * @returns
 */
export function updateWjBooksRetrieveApplyStatus(id: number, status: number) {
    return request.put(`wj_books/wj_books_retrieve_apply/status`, { id, status }, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 更新物流信息
 * @param order_id 订单id
 * @param params 物流信息
 * @returns
 */
export function updateWjBooksOrderExpress(order_id: number, params: Record<string, any>) {
    return request.put(`wj_books/wj_books_order/express/${order_id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 获取物流渠道列表
 * @returns
 */
export function getWjBooksExpressChannelList() {
    return request.get(`wj_books/wj_books_express_channel`)
}

/**
 * 获取物流日志列表
 * @param order_id 订单id
 * @returns
 */
export function getWjBooksExpressLogList(order_id: number) {
    return request.get(`wj_books/wj_books_express_log`, { params: { order_id } })
}

/**
 * 完成订单
 * @param id 订单id
 * @param params
 * @returns
 */
export function completeWjBooksOrder(id: number, params: Record<string, any>) {
    return request.put(`wj_books/wj_books_order/complete/${id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 取消订单
 * @param id 订单id
 * @param cancel_reason 取消原因
 * @returns
 */
export function cancelWjBooksOrder(id: number, cancel_reason: string) {
    return request.put(`wj_books/wj_books_order/cancel/${id}`, { cancel_reason }, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 更新拒收书籍数量
 * @param params 参数
 * @returns
 */
export function updateWjBooksRejectedBookQuantity(params: Record<string, any>) {
    return request.put(`wj_books/wj_books_rejected_book/quantity`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 更新拒收书籍信息
 * @param params 参数，包含id、reject_reason等
 * @returns
 */
export function updateWjBooksRejectedBook(params: Record<string, any>) {
    return request.put(`wj_books/wj_books_rejected_book`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除拒收书籍图片
 * @param id 图片ID
 * @returns
 */
export function deleteWjBooksRejectedImage(id: number) {
    return request.delete(`wj_books/wj_books_rejected_images/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除拒收书籍
 * @param id 拒收书籍ID
 * @returns
 */
export function deleteWjBooksRejectedBook(id: number) {
    return request.delete(`wj_books/wj_books_rejected_book/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

// USER_CODE_END -- wj_books_order 