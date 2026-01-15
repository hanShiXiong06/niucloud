import request from '@/utils/request'


/**
 * 会员信息修改
 */
export function modifyMember(data: AnyObject) {
    return request.put(`member/modify/${ data.field }`, data, { showErrorMessage: true })
}

/**
 * 登录会员绑定手机号
 */
export function bindMobile(data: AnyObject) {
    if (uni.getStorageSync('pid')) {
        data.pid = uni.getStorageSync('pid');
    }
    return request.put('member/mobile', data, { showErrorMessage: true })
}

/**
 * 获取手机号
 */
export function getMobile(data: AnyObject) {
    if (uni.getStorageSync('pid')) {
        data.pid = uni.getStorageSync('pid');
    }
    return request.put('member/getmobile', data, { showErrorMessage: true })
}

/***************************************************** 会员列表 ****************************************************/
/**
 * 获取店铺会员列表
 * @param params
 * @returns
 */
export function getShopMember(params: Record<string, any>) {
    return request.get(`shop/site/member`, params)
}

/**
 * 获取店铺会员详情
 * @param member_id 会员id
 * @returns
 */
export function getShopMemberInfo(member_id: number) {
    return request.get(`shop/site/member/${member_id}`);
}

/**
 * 获取店铺会员优惠券
 * @param member_id 会员id
 * @returns
 */
export function getShopMemberCoupon(member_id: number) {
    return request.get(`mall/site/member/coupon/list/${member_id}`);
}