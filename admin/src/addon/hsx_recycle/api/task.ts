import request from '@/utils/request'

/** 我负责的环节 */
export function getMyStages() {
    return request.get('recycle/stat/task/my_stages')
}

/** 我的工单列表 */
export function getTaskList(params: Record<string, any> = {}) {
    return request.get('recycle/stat/task/list', { params })
}

export function getAssignableUsers(stageKey: string) {
    return request.get('recycle/stat/task/assignable_users', { params: { stage_key: stageKey } })
}

export function assignTask(data: Record<string, any>) {
    return request.put('recycle/stat/task/assign', data, { showSuccessMessage: true })
}

/** 各流程环节的一次性默认负责人设置 */
export function getTaskAssignmentSettings() {
    return request.get('recycle/stat/task/assignment_settings')
}

export function saveTaskAssignmentSettings(defaults: Record<string, number>) {
    return request.post('recycle/stat/task/assignment_settings', { defaults }, { showSuccessMessage: true })
}

/** 认领 */
export function claimTask(data: Record<string, any>) {
    return request.put('recycle/stat/task/claim', data, { showSuccessMessage: true })
}

/** 释放 */
export function releaseTask(data: Record<string, any>) {
    return request.put('recycle/stat/task/release', data, { showSuccessMessage: true })
}

/** 经营看板（各环节在途 + 今日数字 + 趋势） */
export function getStatBoard(params: Record<string, any> = {}) {
    return request.get('recycle/stat/board', { params })
}

/** 一次性回填在途计数 */
export function rebuildStat() {
    return request.post('recycle/stat/rebuild', {}, { showSuccessMessage: true })
}
