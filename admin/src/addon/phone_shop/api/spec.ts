import request from '@/utils/request'

// 规格分组（绑分类）
export function getSpecGroups(params: Record<string, any> = {}) {
    return request.get('phone_shop/goods/spec/group', { params })
}
export function addSpecGroup(data: Record<string, any>) {
    return request.post('phone_shop/goods/spec/group', data, { showSuccessMessage: true })
}
export function editSpecGroup(id: number, data: Record<string, any>) {
    return request.put(`phone_shop/goods/spec/group/${id}`, data, { showSuccessMessage: true })
}
export function delSpecGroup(id: number) {
    return request.delete(`phone_shop/goods/spec/group/${id}`, { showSuccessMessage: true })
}

// 规格子项
export function addSpecItem(data: Record<string, any>) {
    return request.post('phone_shop/goods/spec/item', data)
}
export function editSpecItem(id: number, data: Record<string, any>) {
    return request.put(`phone_shop/goods/spec/item/${id}`, data)
}
export function delSpecItem(id: number) {
    return request.delete(`phone_shop/goods/spec/item/${id}`)
}

// 成色等级（扁平）
export function getGrades() {
    return request.get('phone_shop/goods/grade')
}
export function addGrade(data: Record<string, any>) {
    return request.post('phone_shop/goods/grade', data, { showSuccessMessage: true })
}
export function editGrade(id: number, data: Record<string, any>) {
    return request.put(`phone_shop/goods/grade/${id}`, data, { showSuccessMessage: true })
}
export function delGrade(id: number) {
    return request.delete(`phone_shop/goods/grade/${id}`, { showSuccessMessage: true })
}
