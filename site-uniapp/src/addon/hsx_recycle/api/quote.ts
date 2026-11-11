import request from '@/utils/request'

/** 管理员报价单：能力由后端按本站员工权限返回，不推测角色。 */
export function getQuoteOptions() {
    return request.get('recycle/recycle_category/quote-options', {}, { showErrorMessage: false, showLoading: false })
}

export function updateQuoteImage(categoryId: number, data: { images: string, expected_images: string, remark?: string }) {
    return request.put(`recycle/recycle_category/${categoryId}/quote-image`, data, { showErrorMessage: false, showLoading: false })
}

export function getQuoteHistory(categoryId: number, page = 1) {
    return request.get(`recycle/recycle_category/${categoryId}/quote_history`, { page, limit: 10 }, { showErrorMessage: false, showLoading: false })
}
