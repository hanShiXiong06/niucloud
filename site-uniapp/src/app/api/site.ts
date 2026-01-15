import request from '@/utils/request'

/**
 * 获取用户信息
 * @returns
 */
export function getUserInfo() {
    return request.get(`auth/get`)
}

/**
 * 设置用户信息
 * @returns
 */
export function setUserInfo(params: Record<string, any>) {
    return request.put(`auth/edit`, params, {showSuccessMessage: true});
}


/**
 * 获取店铺基础信息
 */
export function getShopBaseInfo() {
    return request.get(`shop/site/setting`)
}

/**
 * 设置店铺收款方式
 */
export function setShopBaseInfo(params: Record<string, any>) {
    return request.put(`shop/site/setting/set`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 获取店铺信息
 */
export function getShopInfo() {
    return request.get(`shop/site/setting/get_shop`)
}


/**
 * 获取关联店铺
 */
export function getHomeSite(params: Record<string, any>) {
    return request.get(`home/site`, params)
}

/**
 * 获取店铺基础信息
 */
export function getSiteCenter() {
    return request.get(`adminapp/site/apps_of_user_center`)
}