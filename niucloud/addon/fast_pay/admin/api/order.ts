import request from '@/utils/request'

// USER_CODE_BEGIN -- fastpay_order
/**
 * 获取快速支付订单列表
 * @param params
 * @returns
 */
export function getOrderList(params: Record<string, any>) {
    return request.get(`fast_pay/order`, {params})
}

/**
 * 获取快速支付订单详情
 * @param id 快速支付订单id
 * @returns
 */
export function getOrderInfo(id: number) {
    return request.get(`fast_pay/order/${id}`);
}

/**
 * 添加快速支付订单
 * @param params
 * @returns
 */
export function addOrder(params: Record<string, any>) {
    return request.post('fast_pay/order', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑快速支付订单
 * @param id
 * @param params
 * @returns
 */
export function editOrder(params: Record<string, any>) {
    return request.put(`fast_pay/order/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除快速支付订单
 * @param id
 * @returns
 */
export function deleteOrder(id: number) {
    return request.delete(`fast_pay/order/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getWithMemberList(params: Record<string,any>){
    return request.get('fast_pay/member_all', {params})
}

// USER_CODE_END -- fastpay_order
