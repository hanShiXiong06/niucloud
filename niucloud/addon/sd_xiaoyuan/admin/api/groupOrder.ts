import request from '@/utils/request'

// 获取拼单列表
export function getGroupOrderList(params: any) {
    return request.get('sd_xiaoyuan/group_order/list', params)
}

// 获取拼单详情
export function getGroupOrderInfo(id: number) {
    return request.get('sd_xiaoyuan/group_order/info', { id })
}

// 获取拼单类型列表
export function getGroupOrderTypeList() {
    return request.get('sd_xiaoyuan/group_order/type_list')
}

// 获取拼单状态列表
export function getGroupOrderStatusList() {
    return request.get('sd_xiaoyuan/group_order/status_list')
}
