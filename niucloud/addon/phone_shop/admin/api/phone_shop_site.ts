import request from '@/utils/request'

// USER_CODE_BEGIN -- phone_shop_site
/**
 * 获取二手机商户信息列表
 * @param params
 * @returns
 */
export function getPhoneShopSiteList(params: Record<string, any>) {
    return request.get(`phone_shop/phone_shop_site`, {params})
}

/**
 * 获取二手机商户信息详情
 * @param id 二手机商户信息id
 * @returns
 */
export function getPhoneShopSiteInfo(id: number) {
    return request.get(`phone_shop/phone_shop_site/${id}`);
}

/**
 * 添加二手机商户信息
 * @param params
 * @returns
 */
export function addPhoneShopSite(params: Record<string, any>) {
    return request.post('phone_shop/phone_shop_site', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑二手机商户信息
 * @param id
 * @param params
 * @returns
 */
export function editPhoneShopSite(params: Record<string, any>) {
    return request.put(`phone_shop/phone_shop_site/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除二手机商户信息
 * @param id
 * @returns
 */
export function deletePhoneShopSite(id: number) {
    return request.delete(`phone_shop/phone_shop_site/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}



// USER_CODE_END -- phone_shop_site
