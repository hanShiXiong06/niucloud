import request from '@/utils/request'

// USER_CODE_BEGIN -- tkjhkd_shop_order
/**
 * 获取商城发单列表
 * @param params
 * @returns
 */
export function getShopOrderList(params: Record<string, any>) {
    return request.get(`tk_jhkd/shop_order`, {params})
}

/**
 * 获取商城发单详情
 * @param id 商城发单id
 * @returns
 */
export function getShopOrderInfo(id: number) {
    return request.get(`tk_jhkd/shop_order/${id}`);
}

/**
 * 添加商城发单
 * @param params
 * @returns
 */
export function addShopOrder(params: Record<string, any>) {
    return request.post('tk_jhkd/shop_order', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑商城发单
 * @param id
 * @param params
 * @returns
 */
export function editShopOrder(params: Record<string, any>) {
    return request.put(`tk_jhkd/shop_order/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除商城发单
 * @param id
 * @returns
 */
export function deleteShopOrder(id: number) {
    return request.delete(`tk_jhkd/shop_order/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}



// USER_CODE_END -- tkjhkd_shop_order
