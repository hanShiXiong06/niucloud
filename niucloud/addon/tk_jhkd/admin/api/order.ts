import request from '@/utils/request'
export function getStat(){
    return request.get(`tk_jhkd/order/getstat`)
}
export function editOrderMoney(params: Record<string, any>) {
    return request.post(`tk_jhkd/editordermoney`, params, { showErrorMessage: true, showSuccessMessage: true })
}
export function uploadWeapOrder() {
    return request.post(`tk_jhkd/uploadweappdelivery`, { showErrorMessage: true, showSuccessMessage: true })
}
export function sendNotice(id) {
    return request.get(`tk_jhkd/addorder/sendnotice/${id}`, { showSuccessMessage: true, showErrorMessage: true })
}
export function remarkAddOrder(params: Record<string, any>) {
    return request.post(`tk_jhkd/remarkadd`, params, { showErrorMessage: true, showSuccessMessage: true })
}
export function remarkOrder(params: Record<string, any>) {
    return request.post(`tk_jhkd/remark`, params, { showErrorMessage: true, showSuccessMessage: true })
}
export function getLink() {
    return request.get(`tk_jhkd/getlink`)
}
export function changeStatus(params: Record<string, any>) {
    return request.post(`tk_jhkd/changestatus`, params, { showErrorMessage: true, showSuccessMessage: true })
}
// USER_CODE_BEGIN -- tkjhkd_order
/**
 * 获取订单列列表
 * @param params
 * @returns
 */
export function getOrderList(params: Record<string, any>) {
    return request.get(`tk_jhkd/order`, { params })
}
/**
 * 发单
 * @param order_id 订单order_id
 * @returns
 */
export function sendOrder(order_id) {
    return request.get(`tk_jhkd/sendorder/${order_id}`, { showErrorMessage: true, showSuccessMessage: true });
}
/**
 * 取消订单
 * @param params
 * @returns
 */
export function cancelOrder(params: Record<string, any>) {
    return request.post('tk_jhkd/cancelorder', params, { showErrorMessage: true, showSuccessMessage: true })
}
/**
 * 手动结算佣金
 * @param id 
 * @returns 
 */
export function commissionOrder(id) {
    return request.post(`tk_jhkd/order/commissionorder/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}
/**
 * 获取订单列详情
 * @param id 订单列id
 * @returns
 */
export function getOrderInfo(id: number) {
    return request.get(`tk_jhkd/order/${id}`);
}

/**
 * 添加订单列
 * @param params
 * @returns
 */
export function addOrder(params: Record<string, any>) {
    return request.post('tk_jhkd/order', params, { showErrorMessage: true, showSuccessMessage: true })
}
/**
 * 获取订单轨迹
 */
export function getDeliveryInfo(deliveryid) {
    return request.get(`tk_jhkd/getdeliveryinfo/${deliveryid}`)
}

/**
 * 编辑订单列
 * @param id
 * @param params
 * @returns
 */
export function editOrder(params: Record<string, any>) {
    return request.put(`tk_jhkd/order/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除订单列
 * @param id
 * @returns
 */
export function deleteOrder(id: number) {
    return request.delete(`tk_jhkd/order/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getWithMemberList(params: Record<string, any>) {
    return request.get('tk_jhkd/member', { params })
}

// USER_CODE_END -- tkjhkd_order

/**
 * 获取支付类型
 */
export function getOrderPayType() {
    return request.get(`tk_jhkd/order/pay/type`)
}

/**
 * 获取订单来源
 */
export function getOrderFrom() {
    return request.get(`tk_jhkd/order/from`)
}






// USER_CODE_BEGIN -- tkjhkd_order_add
/**
 * 获取订单列列表
 * @param params
 * @returns
 */
export function getOrderAddList(params: Record<string, any>) {
    return request.get(`tk_jhkd/orderadd`, { params })
}

/**
 * 获取订单列详情
 * @param id 订单列id
 * @returns
 */
export function getOrderAddInfo(id: number) {
    return request.get(`tk_jhkd/orderadd/${id}`);
}

/**
 * 添加订单列
 * @param params
 * @returns
 */
export function addOrderAdd(params: Record<string, any>) {
    return request.post('tk_jhkd/orderadd', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑订单列
 * @param id
 * @param params
 * @returns
 */
export function editOrderAdd(params: Record<string, any>) {
    return request.put(`tk_jhkd/orderadd/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除订单列
 * @param id
 * @returns
 */
export function deleteOrderAdd(id: number) {
    return request.delete(`tk_jhkd/orderadd/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}


// USER_CODE_END -- tkjhkd_order_add




// USER_CODE_BEGIN -- tkjhkd_order_log
/**
 * 获取订单日志列表
 * @param params
 * @returns
 */
export function getOrderLogList(params: Record<string, any>) {
    return request.get(`tk_jhkd/orderlog`, { params })
}

/**
 * 获取订单日志详情
 * @param id 订单日志id
 * @returns
 */
export function getOrderLogInfo(id: number) {
    return request.get(`tk_jhkd/orderlog/${id}`);
}

/**
 * 添加订单日志
 * @param params
 * @returns
 */
export function addOrderLog(params: Record<string, any>) {
    return request.post('tk_jhkd/orderlog', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑订单日志
 * @param id
 * @param params
 * @returns
 */
export function editOrderLog(params: Record<string, any>) {
    return request.put(`tk_jhkd/orderlog/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除订单日志
 * @param id
 * @returns
 */
export function deleteOrderLog(id: number) {
    return request.delete(`tk_jhkd/orderlog/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}



// USER_CODE_END -- tkjhkd_order_log
