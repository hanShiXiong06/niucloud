import request from '@/utils/request'



// USER_CODE_BEGIN -- phone_shop_site
/**
 * 获取商户信息列表
 * @param params
 * @returns
 */
export function getPhoneShopList(params: Record<string, any>) {
    return request.get(`phone_shop/phone_shop`, {params})
}

/**
 * 获取商户信息详情
 * @param id 商户信息id
 * @returns
 */
export function getPhoneShopInfo(id: number) {
    return request.get(`phone_shop/phone_shop/${id}`);
}

/**
 * 添加商户信息
 * @param params
 * @returns
 */
export function addPhoneShop(params: Record<string, any>) {
    return request.post('phone_shop/phone_shop', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑商户信息
 * @param id
 * @param params
 * @returns
 */
export function editPhoneShop(params: Record<string, any>) {
    return request.put(`phone_shop/phone_shop/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除商户信息
 * @param id
 * @returns
 */
export function deletePhoneShop(id: number) {
    return request.delete(`phone_shop/phone_shop/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getWithSiteList(params: Record<string,any>){
    return request.get('phone_shop/site_all', {params})
}

// USER_CODE_END -- phone_shop_site
