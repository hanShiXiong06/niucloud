import request from '@/utils/request'

/**
 * 获取今日统计数据
 */
export function getTodayStats(params: Record<string, any>) {
    return request.get(`recycle/stats/getTodayStats`, { params })
}

/**
 * 获取用户统计数据（包含签收、质检、定价、打款的完整统计）
 */
export function getUserStats(params: Record<string, any>) {
    return request.get(`recycle/stats/getUserStats`, { params })
}

/**
 * 获取分类统计
 */
export function getCategoryStats(params: Record<string, any>) {
    return request.get(`recycle/stats/getCategoryStats`, { params })
}

/**
 * 获取排行榜统计
 */
export function getRankingStats(params: Record<string, any>) {
    return request.get(`recycle/stats/getRankingStats`, { params })
}

/**
 * 获取质检员分类统计
 */
export function getCheckerCategoryStats(params: any) {
    return request.get('recycle/stats/getCheckerCategoryStats', { params })
}

/**
 * 获取质检员今日工作量
 */
export function getCheckerTodayWork(params: any) {
    return request.get('recycle/stats/getCheckerTodayWork', { params })
}

/**
 * 获取统计概览
 */
export function getDashboardStats(params: any) {
    return request.get('recycle/stats/getDashboardStats', { params })
}

/**
 * 获取首页组件配置
 */
export function getDashboardWidgets() {
    return request.get('recycle/dashboard/widgets')
}

/**
 * 保存首页组件配置
 */
export function saveDashboardWidgets(data: any) {
    return request.post('recycle/dashboard/widgets', data, { showSuccessMessage: true })
}

/**
 * 获取当前用户可见首页组件
 */
export function getVisibleDashboard(params: any) {
    return request.get('recycle/dashboard/visible', { params })
}

/**
 * 获取 0.0.2 经营看板概览
 */
export function getRecycleDashboardOverview(params: any) {
    return request.get('recycle/dashboard/overview', { params })
}

/**
 * 获取 0.0.2 经营看板趋势
 */
export function getRecycleDashboardTrend(params: any) {
    return request.get('recycle/dashboard/trend', { params })
}

/**
 * 获取 0.0.2 看板指标字典
 */
export function getRecycleDashboardMetrics() {
    return request.get('recycle/dashboard/metrics')
}

/**
 * 获取 0.0.2 看板下钻过滤字典
 */
export function getRecycleDashboardFilters() {
    return request.get('recycle/dashboard/filters')
}

/**
 * 获取质检员绩效统计（兼容旧接口）
 */
export function getInspectorPerformance(params: any) {
    return request.get('recycle/stats/inspectorPerformance', { params })
}

/**
 * 获取估价员绩效统计（兼容旧接口）
 */
export function getPriceConfirmerPerformance(params: any) {
    return request.get('recycle/stats/priceConfirmerPerformance', { params })
}

/**
 * 获取签收统计
 */
export function getSignStats(params: Record<string, any>) {
    return request.get(`recycle/stats/getSignStats`, { params })
}

/**
 * 获取签收分类统计
 */
export function getSignCategoryStats(params: Record<string, any>) {
    return request.get(`recycle/stats/getSignCategoryStats`, { params })
}

/**
 * 获取管理员概况统计
 */
export function getOverviewStats(params: Record<string, any>) {
    return request.get(`recycle/stats/getOverviewStats`, { params })
}

/**
 * 获取用户列表
 */
export function getUserList() {
    return request.get(`recycle/stats/getUserList`)
}

/**
 * 获取用户详细统计
 */
export function getUserDetailStats(params: Record<string, any>) {
    return request.get(`recycle/stats/getUserDetailStats`, { params })
}

/**
 * 员工考核看板：每人计数 + 各环节平均时效 + 金额贡献
 */
export function getStaffKpiBoard(params: Record<string, any>) {
    return request.get(`recycle/stats/getStaffKpiBoard`, { params })
}

/**
 * 获取会员统计概览
 */
export function getMemberStatsOverview(params: Record<string, any>) {
    return request.get(`recycle/stats/getMemberStatsOverview`, { params })
}

/**
 * 获取会员注册趋势
 */
export function getMemberRegisterTrend(params: Record<string, any>) {
    return request.get(`recycle/stats/getMemberRegisterTrend`, { params })
}

/**
 * 获取会员注册渠道分布
 */
export function getMemberChannelStats(params: Record<string, any>) {
    return request.get(`recycle/stats/getMemberChannelStats`, { params })
}

/**
 * 获取拉新排行榜
 */
export function getMemberInviteRank(params: Record<string, any>) {
    return request.get(`recycle/stats/getMemberInviteRank`, { params })
}

/**
 * 获取会员活跃度统计
 */
export function getMemberActivityStats(params: Record<string, any>) {
    return request.get(`recycle/stats/getMemberActivityStats`, { params })
}
