import request from '@/utils/request'

/**
 * 获取优惠券列表
 * @param params
 * @returns
 */
export function getCouponList(params: Record<string, any>) {
    return request.get('home_service/coupon', params);
}

/**
 * 获取优惠券详情
 * @param id
 * @returns
 */
export function getCouponDetail(id: number) {
    return request.get(`home_service/coupon/${id}`);
}

/**
 * 领取优惠券
 * @param params
 * @returns
 */
export function receiveCoupon(params: Record<string, any>) {
    return request.post(`home_service/coupon`, params, { showSuccessMessage: true });
}

/**
 * 获取优惠券类型列表
 * @returns
 */
export function getCouponType() {
    return request.get('home_service/coupon_type');
}

/**
 * 获取我的优惠券数量
 * status 1：待使用，2：已使用，3：已过期，4：已失效
 */
export function getMyCouponCount(params: Record<string, any>) {
    return request.get(`home_service/member/coupon/count`, params)
}

/**
 * 获取我的优惠券类型
 */
export function getMyCouponType() {
    return request.get(`home_service/coupon_type`)
}

/**
 * 获取我的优惠数量
 */
export function getMyCouponStatusCount() {
    return request.get(`home_service/member/coupon/status_count`)
}


/**
 * 获取我的优惠券
 */
export function getMyCouponList(params: Record<string, any>) {
    return request.get(`home_service/member/coupon`, params)
}