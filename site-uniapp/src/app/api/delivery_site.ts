import request from '@/utils/request'
/**
 * 获取 物流管理配置
 * @returns
 */
export function getShopDeliveryList() {
    return request.get('delivery/site/delivery/deliveryList')
}

/**
 * 获取运费模版列表
 * @param params
 * @returns
 */
export function getShippingTemplateList(params: Record<string, any>) {
    return request.get(`delivery/site/shipping/template/list`, params)
}

/**
 * 获取物流公司列表
 * @param params
 * @returns
 */
export function getCompanyList(params: Record<string, any>) {
    return request.get(`delivery/site/delivery/company`, params)
}

/**
 * 获取商家收货地址
 * @returns
 */
export function getOrderRefundAddress() {
    return request.get('delivery/site/order/refund/address')
}

// TODO
export function getDeliveryStoreListAll (){
    return true
}

// TODO
export function getShopDeliverList (){
    return true
}

// TODO
export function getInUseLocalDeliveryList (){
    return true
}
// getDeliveryStoreListAll

