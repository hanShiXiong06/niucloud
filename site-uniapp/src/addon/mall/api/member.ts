import request from '@/utils/request'

/**
 * 获取绑定会员数据
 */
export function getBindInfo() {
    return request.get(`wuxinggou/site/shop/bind_info`)
}

/**
 * 更换绑定人
 */
export function updateMobile(params: Record<string, any>) {
    return request.post('wuxinggou/site/shop/login/mobile', params)
}

/**
 * 设置用户信息
 * @returns
 */
export function setUserInfo(params: Record<string, any>) {
    return request.put(`auth/edit`, params);
}

/**
 * 获取注销详情
 */
export function getLogoutDetail() {
    return request.get(`wuxinggou/site/logout/detail`)
}

/**
 * 获取注销统计
 */
export function getLogoutCount() {
    return request.get(`wuxinggou/site/logout/count`)
}

/**
 * 申请注销
 */
export function submitOff() {
    return request.post('wuxinggou/site/logout/submitlogoff')
}


/**
 * 取消注销
 */
export function logoutCancel() {
    return request.put(`wuxinggou/site/logout/cancel`);
}

/**
 * 获取订单消息
 */
export function getNoticeOrder(data: AnyObject) {
    return request.get('site/notice/order_list', data)
}

/**
 * 获取系统消息
 */
export function getNoticeSys(data: AnyObject) {
    return request.get('site/notice/sys_list', data)
}
