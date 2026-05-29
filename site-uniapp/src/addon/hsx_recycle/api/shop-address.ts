import request from '@/utils/request'

export function getShopAddressList(params: Record<string, any> = {}) {
    return request.get('recycle/shop_address', params)
}

export function getShopDefaultDeliveryAddressInfo() {
    return request.get('recycle/shop_address/default/delivery')
}
