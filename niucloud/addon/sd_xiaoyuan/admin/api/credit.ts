import request from '@/utils/request'

// 获取信誉分列表
export function getCreditList(params: any) {
    return request.get('sd_xiaoyuan/credit/list', { params })
}

// 获取用户信誉分详情
export function getCreditInfo(member_id: number) {
    return request.get('sd_xiaoyuan/credit/info', { params: { member_id } })
}

// 获取信誉分变动记录
export function getCreditLogList(params: any) {
    return request.get('sd_xiaoyuan/credit/log_list', { params })
}

// 管理员调整信誉分
export function adjustCredit(data: any) {
    return request.post('sd_xiaoyuan/credit/adjust', data)
}

// 获取信誉分统计
export function getCreditStat() {
    return request.get('sd_xiaoyuan/credit/stat')
}

// 获取变动类型列表
export function getCreditTypeList() {
    return request.get('sd_xiaoyuan/credit/type_list')
}
