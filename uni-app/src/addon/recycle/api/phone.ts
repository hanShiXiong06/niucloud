import request from '@/utils/request'

// 获取手机型号列表
export function getPhoneModels() {
    return request.get('addon/recycle/phone/models')
}

// 获取手机型号详情
export function getPhoneModelDetail(id: number) {
    return request.get(`addon/recycle/phone/model/${id}`)
}

// 获取手机型号价格区间
export function getPhoneModelPriceRange(id: number) {
    return request.get(`addon/recycle/phone/price_range/${id}`)
}

// 获取手机型号配置选项
export function getPhoneModelOptions(id: number) {
    return request.get(`addon/recycle/phone/options/${id}`)
} 