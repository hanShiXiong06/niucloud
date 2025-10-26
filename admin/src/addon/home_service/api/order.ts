import request from '@/utils/request'

/***************************************************** 订单管理 ****************************************************/

/**
 * 获取订单列表
 * @param params
 * @returns
 */
export function getOrderList(params: Record<string, any>) {
    return request.get(`home_service/order`, {params})
}

/**
 * 获取订单详情
 * @param orderId 订单orderId
 * @returns
 */
export function getOrderDetail(orderId: number) {
    return request.get(`home_service/order/${orderId}`);
}

/**
 * 获取订单状态
 */
export function getOrderStatus() {
    return request.get('home_service/order/taskstatus');
}
/**
 * 派单
 */
export function setSendOrders(params: Record<string, any>) {
    return request.post('home_service/order/dispatch',params, {showErrorMessage: true, showSuccessMessage: true});
}

/**
 * 获取交易设置
 */
export function getOrderConfig() {
    return request.get('home_service/order/config');
}
/**
 * 设置交易设置
 */
export function setOrderConfig(params: Record<string, any>) {
    return request.post('home_service/order/config',params, {showErrorMessage: true, showSuccessMessage: true});
}

/**
 * 获取售后列表
 */
export function getRefundList(params: Record<string, any>) {
    return request.get('home_service/refund', {params});
}

/**
 * 获取售后详情
 */
export function getRefundDetail(refundId: number) {
    return request.get(`home_service/refund/${refundId}`);
}

/**
 * 获取售后状态
 */
export function getRefundStatus() {
    return request.get('home_service/refund/status');
}

/**
 * 确认退款
 * @param params
 * @returns
 */
export function confirmRefund(params: Record<string, any>) {
    return request.put(`home_service/refund/${params.refund_id}`,params);
}

/**
 * 拒绝退款
 * @param params
 * @returns
 */
export function refuseRefund(params: Record<string, any>) {
    return request.put(`home_service/refund/refuse/${params.refund_id}`, params);
}
/**
 * 获取预约面板
 * @param params
 * @returns
 */
export function getReserveBoard(params: Record<string, any>) {
    return request.get(`home_service/order/board`, {params});
}


/**
 * 订单来源
 * @param params
 * @returns
 */
export function getOrderfrom(params: Record<string, any>) {
    return request.get(`home_service/order/orderfrom`, {params});
}


/**
 * 回访列表
 * @param params
 * @returns
 */
export function getOrderfollow(params: Record<string, any>) {
    return request.get(`home_service/orderfollow`, {params});
}


/**
 * 回访状态
 * @param params
 * @returns
 */
export function getOrderfollowStatus(params: Record<string, any>) {
    return request.get(`home_service/orderfollow/taskstatus`, {params});
}

/**
 * 回访状态
 * @param params
 * @returns
 */
export function setorderfollow(params: Record<string, any>) {
    return request.put(`/home_service/orderfollow/${params.order_id}`, params, {showErrorMessage: true, showSuccessMessage: true});
}

/**
 * 回访结果
 * @param params
 * @returns
 */
export function getOrderfollowresult(params: Record<string, any>) {
    return request.get(`home_service/orderfollow/followresult`, {params});
}


/**
 * 收费情况
 * @param params
 * @returns
 */
export function getOrderfeesituation(params: Record<string, any>) {
    return request.get(`/home_service/orderfollow/feesituation`, {params});
}


/**
 * 收费情况
 * @param params
 * @returns
 */
export function setOrderLabel(params: Record<string, any>) {
    return request.post(`home_service/order/label`, params, {showErrorMessage: true, showSuccessMessage: true});
}


/**
 * 收费情况
 * @param params
 * @returns
 */
export function setOrderDispatch(params: Record<string, any>) {
    return request.post(`home_service/order/dispatch`, params, {showErrorMessage: true, showSuccessMessage: true});
}

/**
 * 删除订单
 * @param params
 * @returns
 */
export function deleteOrder(params: Record<string, any>) {
    return request.delete(`home_service/order/delete`, {params}, {showErrorMessage: true, showSuccessMessage: true});
}




/**
 * 催单订单
 * @param params
 * @returns
 */
export function cuiOrder(params: Record<string, any>) {
    // 正确的写法应该是直接传递params，而不是{params}
    return request.post(`home_service/order/reminder`, params, {
        showErrorMessage: true,
        showSuccessMessage: true
    });
}


/**
 * 重新派单
 * @param params
 * @returns
 */
export function transferOrder(params: Record<string, any>) {
    return request.post(`home_service/order/transfer`, params, {showErrorMessage: true, showSuccessMessage: true});
}

/**
 * 获取可派单师傅
 * @param params
 * @returns
 */
export function getSelectTechnician(params: Record<string, any>) {
    return request.get(`home_service/order/selecttechnician`, {params}, {showErrorMessage: true, showSuccessMessage: true});
}


/**
 * 订单计算
 * @param params
 * @returns
 */
export function replaceCalculate(params: Record<string, any>) {
    return request.get(`home_service/replace_buy/calculate`, { params })
}

/**
 * 订单创建
 * @param params
 * @returns
 */
export function replaceCreate(params: Record<string, any>) {
    return request.post(`home_service/replace_buy/create`, params)
}
/**
 * 支付
 */
export function pay(params: Record<string, any>) {
    return request.post(`pay`, params, {
        showErrorMessage: false,
        showSuccessMessage: false
    })
}

/**
 * 优惠券
 * @param params
 * @returns
 */
export function getReplaceCoupon(params: Record<string, any>) {
    return request.get(`home_service/replace_buy/coupon`, { params })
}