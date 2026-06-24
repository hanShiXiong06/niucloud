import request from '@/utils/request'

// 获取失物招领列表
export function getLostFoundList(params: any) {
    return request.get('sd_xiaoyuan/lost_found/list', { params })
}

// 获取失物招领详情
export function getLostFoundInfo(id: number) {
    return request.get('sd_xiaoyuan/lost_found/info', { params: { id } })
}

// 获取类型列表
export function getLostFoundTypeList() {
    return request.get('sd_xiaoyuan/lost_found/type_list')
}

// 获取分类列表
export function getLostFoundCategoryList() {
    return request.get('sd_xiaoyuan/lost_found/category_list')
}
