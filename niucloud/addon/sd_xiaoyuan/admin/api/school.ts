import request from '@/utils/request'

// 获取学校列表
export function getSchoolList(params: any) {
    return request.get('sd_xiaoyuan/school/list', { params })
}

// 获取学校详情
export function getSchoolInfo(id: number) {
    return request.get('sd_xiaoyuan/school/info', { params: { id } })
}

// 添加学校
export function addSchool(data: any) {
    return request.post('sd_xiaoyuan/school/add', data)
}

// 编辑学校
export function editSchool(data: any) {
    return request.post('sd_xiaoyuan/school/edit', data)
}

// 删除学校
export function delSchool(id: number) {
    return request.post('sd_xiaoyuan/school/del', { id })
}

// 修改学校状态
export function setSchoolStatus(id: number, status: number) {
    return request.post('sd_xiaoyuan/school/set_status', { id, status })
}

// 获取所有学校(不分页)
export function getAllSchools() {
    return request.get('sd_xiaoyuan/school/all')
}

// 别名，兼容其他页面引用
export const getSchoolAll = getAllSchools
