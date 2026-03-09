import request from '@/utils/request'

// 获取任务列表
export function getTaskList(params: any) {
    return request.get('sd_xiaoyuan/task/list', params)
}

// 获取任务详情
export function getTaskInfo(id: number) {
    return request.get('sd_xiaoyuan/task/info', { id })
}

// 获取任务类型列表
export function getTaskTypeList() {
    return request.get('sd_xiaoyuan/task/type_list')
}

// 获取任务状态列表
export function getTaskStatusList() {
    return request.get('sd_xiaoyuan/task/status_list')
}
