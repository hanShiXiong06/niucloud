import request from '@/utils/request'

/***************************************************** 订单 ***************************************************/
/**
 * 订单确认
 */
export function orderConfirm(params: Record<string, any>) {
    return request.get(`home_service/order/confirm`, params)
}

/**
 * 订单计算
 */
export function orderCalculate(params: Record<string, any>) {
    return request.post(`home_service/order/calculate`, params)
}

/**
 * 订单创建
 */
export function orderCreate(params: Record<string, any>) {
    return request.post(`home_service/order/create`, params, { showSuccessMessage: true })
}

/**
 * 获取订单列表
 * @param params
 * @returns
 *
 */
export function getOrderList(params: Record<string, any>) {
    return request.get('home_service/store/order', params);
}

/**
 * 获取订单列表
 * @param params
 * @returns
 *
 */
export function getGrapOrderList(params: Record<string, any>) {
    return request.get('home_service/store/grab', params);
}

/**
 * 获取订单详情
 * @param orderId
 * @returns
 *
 */
export function getOrderDetail(orderId: number) {
    return request.get(`home_service/store/order/${ orderId }`);
}

/**
 * 师傅端获取退款详情
 * @param refundId
 * @returns
 */
export function getOrderRefundDetail(refundId: number) {
    return request.get(`home_service/refund/orderRefund/${ refundId }`)
}

/**
 * 获取订单角标数据
 */
export function getOrderNum() {
    return request.get(`home_service/order/getNum`)
}


/**
 * 查询订单可用优惠券
 */
export function orderCoupon(params: Record<string, any>) {
    return request.get('home_service/order_create/coupon', params)
}


/***************************************************** 师傅订单管理 ****************************************************/

/**
 * 分类列表
 * @param params
 * @returns
 */
export function getCategoryOrderList(params: Record<string, any>) {
    return request.get(`home_service/category`, params)
}

/**
 * 订单状态
 * @param params
 * @returns
 */
export function getOrderStatus(params: Record<string, any>) {
    return request.get(`home_service/store/order/task_status`, params)
}

/**
 * 普通订单列表(分页)
 * @param params
 * @returns
 */
export function getAllTechnicianOrderList(params: Record<string, any>) {
    return request.get(`home_service/technician/order`, params)
}


/**
 * 订单超时数量
 * @param params
 * @returns
 */
export function getOrderTimeOut(params: Record<string, any>) {
    return request.get(`home_service/technician/order/order_time_num`)
}


/**
 * 出发
 * @param params
 * @returns
 */
export function departOrder(params: Record<string, any>) {
    return request.post(`home_service/technician/order/depart`,params)
}

/**
 * 拍照
 * @param params
 * @returns
 */
export function photoTaken(params: Record<string, any>) {
    return request.post(`home_service/technician/order/phototaken`,params)
}


/**
 * 开始服务
 * @param params
 * @returns
 */
export function startService(params: Record<string, any>) {
    return request.post(`home_service/technician/order/start`,params)
}


/**
 * 服务完成
 * @param params
 * @returns
 */
export function saveCheck(params: Record<string, any>) {
    return request.post(`home_service/technician/order/savecheck`,params)
}

/**
 * 抢单
 */
export function grabOrder(id: number) {
    return request.put(`home_service/store/grab/${ id }`)
}

/**
 * 派单
 */
export function dispatchOrder(params: Record<string, any>) {
    return request.post(`home_service/store/order/dispatch`,params,{ showSuccessMessage: true })
}

/**
 * 重新派单
 */
export function transferOrder(params: Record<string, any>) {
    return request.post(`home_service/store/order/transfer`,params,{ showSuccessMessage: true })
}


/**
 * 催单
 */
export function reminderOrder(params: Record<string, any>) {
    return request.post(`home_service/store/order/reminder`,params,{ showSuccessMessage: true })
}

/**
 * 订单超时数量
 * @param params
 * @returns
 */
export function getGrapOrderDetail(id: number) {
    return request.get(`home_service/store/grab/${id}`)
}



/**
 * 订单超时数量
 * @param params
 * @returns
 */
export function getTechnicianOrderList(params: Record<string, any>) {
    return request.post(`home_service/store/order/selecttechnician`,params)
}
