import request from '@/utils/request'

/**
 * 优惠券列表
 */
export function getCouponList(params: Record<string, any>) {
    return request.get(`tk_jhkd/coupon`, params)
}

/**
 * 优惠券详情
 */
export function getCouponInfo(id: number) {
    return request.get(`tk_jhkd/coupon/${id}`)
}

/**
 * 优惠券二维码
 */
export function getCouponQrocde(id: number) {
    return request.get(`tk_jhkd/coupon/qrcode/${id}`)
}

/**
 * 领取优惠券
 */
export function getCoupon(params: Record<string, any>) {
    return request.post(`tk_jhkd/coupon`, params, { showSuccessMessage: true })
}

/**
 * 获取我的优惠券
 */
export function getMyCouponList(params: Record<string, any>) {
    return request.get(`tk_jhkd/member/coupon`, params)
}

/**
 * 获取优惠券列表供组件调用
 */
export function getCouponComponents(params: Record<string, any>) {
    return request.get(`tk_jhkd/coupon/components`, params)
}

/**
 * 获取我的优惠券数量
 * status 1：待使用，2：已使用，3：已过期，4：已失效
 */
export function getMyCouponCount(params: Record<string, any>) {
    return request.get(`tk_jhkd/member/coupon/count`, params)
}

/**
 * 获取我的优惠券类型
 */
export function getMyCouponType() {
    return request.get(`tk_jhkd/coupon_type`)
}

/**
 * 获取我的优惠数量
 */
export function getMyCouponStatusCount() {
    return request.get(`tk_jhkd/member/coupon/status_count`)
}
export function getUseMemberCouponList() {
    return request.get(`tk_jhkd/member/coupon/list`)
}