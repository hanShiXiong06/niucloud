import request from '@/utils/request'

// ========== 班级管理 ==========

// 获取班级列表(分页)
export function getSchoolClassList(params: any) {
    return request.get('sd_xiaoyuan/school_class/list', { params })
}

// 获取班级列表(不分页)
export function getAllSchoolClass(params?: any) {
    return request.get('sd_xiaoyuan/school_class/all', { params })
}

// 获取班级详情
export function getSchoolClassInfo(id: number) {
    return request.get('sd_xiaoyuan/school_class/info', { params: { id } })
}

// 添加班级
export function addSchoolClass(data: any) {
    return request.post('sd_xiaoyuan/school_class/add', data)
}

// 编辑班级
export function editSchoolClass(data: any) {
    return request.post('sd_xiaoyuan/school_class/edit', data)
}

// 删除班级
export function delSchoolClass(id: number) {
    return request.post('sd_xiaoyuan/school_class/del', { id })
}

// 修改班级状态
export function setSchoolClassStatus(id: number, status: number) {
    return request.post('sd_xiaoyuan/school_class/set_status', { id, status })
}

// ========== 班级课表管理 ==========

// 获取班级课表列表
export function getClassScheduleList(params: any) {
    return request.get('sd_xiaoyuan/class_schedule/list', { params })
}

// 添加课程
export function addClassSchedule(data: any) {
    return request.post('sd_xiaoyuan/class_schedule/add', data)
}

// 编辑课程
export function editClassSchedule(data: any) {
    return request.post('sd_xiaoyuan/class_schedule/edit', data)
}

// 删除课程
export function delClassSchedule(id: number) {
    return request.post('sd_xiaoyuan/class_schedule/del', { id })
}

// 清空班级课表
export function clearClassSchedule(class_id: number, semester: string) {
    return request.post('sd_xiaoyuan/class_schedule/clear', { class_id, semester })
}

// 导入课表（文件上传）
export function importClassSchedule(data: FormData) {
    return request.post('sd_xiaoyuan/class_schedule/import', data, { headers: { 'Content-Type': 'multipart/form-data' } })
}
