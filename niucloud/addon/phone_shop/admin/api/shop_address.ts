import request from '@/utils/request'

/**
 * 获取商家地址库列表
 * @param params
 * @returns
 */
export function getShopAddressList(params: Record<string, any>) {
    return request.get(`phone_shop/shop_address`, { params })
}

/**
 * 获取商家地址库详情
 * @param id 商家地址库id
 * @returns
 */
export function getShopAddressInfo(id: number) {
    return request.get(`phone_shop/shop_address/${ id }`);
}

/**
 * 添加商家地址库
 * @param params
 * @returns
 */
export function addShopAddress(params: Record<string, any>) {
    return request.post('phone_shop/shop_address', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑商家地址库
 * @param params
 * @returns
 */
export function editShopAddress(params: Record<string, any>) {
    return request.put(`phone_shop/shop_address/${ params.id }`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除商家地址库
 * @param id
 * @returns
 */
export function deleteShopAddress(id: number) {
    return request.delete(`phone_shop/shop_address/${ id }`, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 获取商家默认发货地址
 * @returns
 */
export function getShopDefaultDeliveryAddressInfo() {
    return request.get('phone_shop/shop_address/default/delivery');
}

/**
 * 获取商家收货地址
 * @returns
 */
export function getOrderRefundAddress() {
    return request.get('phone_shop/order/refund/address');
}