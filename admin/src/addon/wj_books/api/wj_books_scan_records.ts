import request from '@/utils/request'

// USER_CODE_BEGIN -- wj_books_scan_records
/**
 * 获取图书扫描记录列表
 * @param params
 * @returns
 */
export function getWjBooksScanRecordsList(params: Record<string, any>) {
    return request.get(`wj_books/wj_books_scan_records`, {params})
}

/**
 * 获取图书扫描记录详情
 * @param id 图书扫描记录id
 * @returns
 */
export function getWjBooksScanRecordsInfo(id: number) {
    return request.get(`wj_books/wj_books_scan_records/${id}`);
}

/**
 * 添加图书扫描记录
 * @param params
 * @returns
 */
export function addWjBooksScanRecords(params: Record<string, any>) {
    return request.post('wj_books/wj_books_scan_records', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑图书扫描记录
 * @param id
 * @param params
 * @returns
 */
export function editWjBooksScanRecords(params: Record<string, any>) {
    return request.put(`wj_books/wj_books_scan_records/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除图书扫描记录
 * @param id
 * @returns
 */
export function deleteWjBooksScanRecords(id: number) {
    return request.delete(`wj_books/wj_books_scan_records/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getWithMemberList(params: Record<string,any>){
    return request.get('wj_books/member_all', {params})
}

// USER_CODE_END -- wj_books_scan_records
