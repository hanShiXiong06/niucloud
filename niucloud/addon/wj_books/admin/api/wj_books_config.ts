import request from '@/utils/request'

/**
 * 获取旧书回收系统配置
 * @returns
 */
export function getWjBooksConfig() {
    return request.get(`wj_books/wj_books_config`)
}

/**
 * 更新旧书回收系统配置
 * @param params
 * @returns
 */
export function updateWjBooksConfig(params: Record<string, any>) {
    return request.put(`wj_books/wj_books_config`, params, { showErrorMessage: true, showSuccessMessage: true })
} 