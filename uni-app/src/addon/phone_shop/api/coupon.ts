import request from '@/utils/request'

/**
 * 优惠券列表
 */
export function getShopCouponList(params: Record<string, any>) {
    return request.get(`phone_shop/coupon`, params)
}

/**
 * 优惠券详情
 */
export function getShopCouponInfo(id: number) {
    return request.get(`phone_shop/coupon/${ id }`)
}

/**
 * 优惠券二维码
 */
export function getShopCouponQrocde(id: number) {
    return request.get(`phone_shop/coupon/qrcode/${ id }`)
}

/**
 * 领取优惠券
 */
export function getCoupon(params: Record<string, any>) {
    return request.post(`phone_shop/coupon`, params, { showSuccessMessage: true })
}

/**
 * 获取我的优惠券
 */
export function getMyCouponList(params: Record<string, any>) {
    return request.get(`phone_shop/member/coupon`, params)
}

/**
 * 获取优惠券列表供组件调用
 */
export function getShopCouponComponents(params: Record<string, any>) {
    return request.get(`phone_shop/coupon/components`, params)
}

/**
 * 获取我的优惠券数量
 * status 1：待使用，2：已使用，3：已过期，4：已失效
 */
export function getMyCouponCount(params: Record<string, any>) {
    return request.get(`phone_shop/member/coupon/count`, params)
}
/**
 * 获取我的优惠券类型
 */
export function getMyCouponType() {
    return request.get(`phone_shop/coupon_type`)
}
/**
 * 获取我的优惠数量
 */
export function getMyCouponStatusCount() {
    return request.get(`phone_shop/member/coupon/status_count`)
}