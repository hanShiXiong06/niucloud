import request from '@/utils/request'

// 获取二手商品列表
export function getSecondhandList(params: any) {
    return request.get('sd_xiaoyuan/secondhand/list', params)
}

// 获取二手商品详情
export function getSecondhandInfo(id: number) {
    return request.get('sd_xiaoyuan/secondhand/info', { id })
}

// 获取分类列表
export function getSecondhandCategoryList() {
    return request.get('sd_xiaoyuan/secondhand/category_list')
}

// 添加分类
export function addSecondhandCategory(data: any) {
    return request.post('sd_xiaoyuan/secondhand/add_category', data)
}

// 编辑分类
export function editSecondhandCategory(data: any) {
    return request.post('sd_xiaoyuan/secondhand/edit_category', data)
}

// 删除分类
export function delSecondhandCategory(id: number) {
    return request.post('sd_xiaoyuan/secondhand/del_category', { id })
}

// 编辑商品
export function editSecondhand(data: any) {
    return request.post('sd_xiaoyuan/secondhand/edit', data)
}
