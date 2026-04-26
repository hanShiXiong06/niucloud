import request from '@/utils/request'

// USER_CODE_BEGIN -- wj_books_info
/**
 * 获取图书信息列表
 * @param params
 * @returns
 */
export function getWjBooksInfoList(params: Record<string, any>) {
    return request.get(`wj_books/wj_books_info`, {params})
}

/**
 * 获取图书信息详情
 * @param id 图书信息id
 * @returns
 */
export function getWjBooksInfoInfo(id: number) {
    return request.get(`wj_books/wj_books_info/${id}`);
}

/**
 * 添加图书信息
 * @param params
 * @returns
 */
export function addWjBooksInfo(params: Record<string, any>) {
    return request.post('wj_books/wj_books_info', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑图书信息
 * @param id
 * @param params
 * @returns
 */
export function editWjBooksInfo(params: Record<string, any>) {
    return request.put(`wj_books/wj_books_info/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除图书信息
 * @param id
 * @returns
 */
export function deleteWjBooksInfo(id: number) {
    return request.delete(`wj_books/wj_books_info/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 通过ISBN查询图书信息
 * @param isbn ISBN编码
 * @returns
 */
export function queryBookInfoByIsbn(isbn: string) {
    return request.get(`wj_books/wj_books_info/query_by_isbn`, { params: { isbn } })
}

// USER_CODE_END -- wj_books_info
