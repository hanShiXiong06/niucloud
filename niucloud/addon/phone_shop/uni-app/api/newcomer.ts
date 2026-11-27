import request from '@/utils/request'

// 新人专区列表
export function getNewcomerGoodsList(params: Record<string, any>) {
    return request.get(`phone_shop/newcomer/goods`, params)
}
export function getNewcomersConfig() {
    return request.get(`phone_shop/newcomer/config`)
}

// 首页新人专享列表
export function getNewcomersComponentsList(params: Record<string, any>) {
    return request.get(`phone_shop/newcomer/goods/components`, params)
}