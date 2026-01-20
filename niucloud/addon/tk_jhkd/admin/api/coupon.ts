import request from '@/utils/request'
/**
 * 添加优惠券
 * @param params
 * @returns
 */
export function addCoupon(params: Record<string, any>) {
    return request.post(`tk_jhkd/coupon`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 获取优惠券状态列表
 * @returns
 */
export function getCouponStatusList() {
    return request.get(`tk_jhkd/coupon/status`)
}

/**
 * 获取优惠券列表
 * @param params
 * @returns
 */
export function getCouponList(params: Record<string, any>) {
    return request.get(`tk_jhkd/coupon`, { params })
}

/**
 * 获取优惠券列表
 * @param params
 * @returns
 */
export function getCouponSelectList(params: Record<string, any>) {
    return request.get(`tk_jhkd/coupon/select`, { params })
}

/**
 * 获取优惠券领取记录
 * @param params
 * @returns
 */
export function getCouponRecords(params: Record<string, any>) {
    return request.get(`tk_jhkd/coupon/records`, { params });
}

/**
 * 获取优惠券详情
 * @param id
 * @returns
 */
export function getCouponInfo(id: number) {
    return request.get(`tk_jhkd/coupon/detail/${ id }`);
}

/**
 * 优惠券状态变更
 * @param params
 * @returns
 */
export function editCouponStatus(params: Record<string, any>) {
    return request.put(`tk_jhkd/coupon/setstatus/${ params.status }`, params, { showSuccessMessage: true })
}

/**
 * 编辑优惠券
 * @param params
 * @returns
 */
export function editCoupon(params: Record<string, any>) {
    return request.put(`tk_jhkd/coupon/edit/${ params.id }`, params, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 删除优惠券
 * @param id
 * @returns
 */
export function deleteCoupon(id: number) {
    return request.delete(`tk_jhkd/coupon/${ id }`, { showSuccessMessage: true })
}

/**
 * 关闭优惠券
 * @param id
 * @returns
 */
export function closeCoupon(id: number) {
    return request.put(`tk_jhkd/coupon/invalid/${ id }`, { showSuccessMessage: true })
}