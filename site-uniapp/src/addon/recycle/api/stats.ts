import request from '@/utils/request'

/**
 * 获取经营看板数据
 */
export function getDashboardOverview(params?: Record<string, any>) {
    return request.get('recycle/dashboard/overview', { params })
}

/**
 * 获取当前用户可见的看板组件
 */
export function getVisibleDashboard(params?: Record<string, any>) {
    return request.get('recycle/dashboard/visible', { params })
}
