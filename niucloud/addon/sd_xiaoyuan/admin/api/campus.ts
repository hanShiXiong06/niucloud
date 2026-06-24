import request from '@/utils/request'

// 获取校区列表
export function getCampusList(params: any) {
    return request.get('sd_xiaoyuan/campus/list', { params })
}

// 获取校区详情
export function getCampusDetail(id: number) {
    return request.get(`sd_xiaoyuan/campus/info/${id}`)
}

// 添加校区
export function addCampus(data: any) {
    return request.post('sd_xiaoyuan/campus/add', data)
}

// 编辑校区
export function editCampus(data: any) {
    return request.post('sd_xiaoyuan/campus/edit', data)
}

// 删除校区
export function deleteCampus(id: number) {
    return request.post(`sd_xiaoyuan/campus/delete`, { id })
}

// 设置校区状态
export function setCampusStatus(data: any) {
    return request.post('sd_xiaoyuan/campus/set_status', data)
}

// 获取校区列表(不分页)
export function getCampusAll(params: any) {
    return request.get('sd_xiaoyuan/campus/all', { params })
}
