import request from '@/utils/request'


/**
 * 获取数据统计
 */
export function getstat() {
    return request.get(`adminapp/site/stat`)
}

/**
 * 获取代办事项统计
 */
export function getTodo() {
    return request.get(`adminapp/site/todo`)
}

/**
 * 获取统计值
 */
export function geTotal(params: Record<string, any>) {
    return request.get(`${params.url}`)
}


/**
 * 首页应用
 */
export function getAppOfIndex() {
    return request.get(`adminapp/site/apps_of_index`)
}


/**
 * 应用
 */
export function getApp() {
    return request.get(`adminapp/site/apps`)
}