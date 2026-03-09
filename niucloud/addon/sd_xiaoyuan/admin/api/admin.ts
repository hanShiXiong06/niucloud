import request from '@/utils/request'

// 数据概览
export function getDashboardOverview() {
    return request.get('sd_xiaoyuan/dashboard/index')
}

export function getDashboardStat(params: any) {
    return request.get('sd_xiaoyuan/dashboard/stat', { params })
}

// 订单管理
export function getOrderList(params: any) {
    return request.get('sd_xiaoyuan/order/list', { params })
}

export function getOrderDetail(id: number) {
    return request.get(`sd_xiaoyuan/order/detail/${id}`)
}

export function getOrderStat() {
    return request.get('sd_xiaoyuan/order/stat')
}

export function cancelOrder(data: any) {
    return request.post('sd_xiaoyuan/order/cancel', data)
}

export function refundOrder(data: any) {
    return request.post('sd_xiaoyuan/order/refund', data)
}

export function assignRunner(data: any) {
    return request.post('sd_xiaoyuan/order/assign_runner', data)
}

export function getOnlineRunners(params: any) {
    return request.get('sd_xiaoyuan/runner/online_list', { params })
}

// 接单员管理
export function getRunnerList(params: any) {
    return request.get('sd_xiaoyuan/runner/list', { params })
}

export function getRunnerDetail(id: number) {
    return request.get(`sd_xiaoyuan/runner/detail/${id}`)
}

export function auditRunner(data: any) {
    return request.post('sd_xiaoyuan/runner/audit', data)
}

export function disableRunner(data: any) {
    return request.post('sd_xiaoyuan/runner/disable', data)
}

export function enableRunner(data: any) {
    return request.post('sd_xiaoyuan/runner/enable', data)
}

export function getRunnerStat() {
    return request.get('sd_xiaoyuan/runner/stat')
}

export function getRunnerBalanceLog(id: number, params: any) {
    return request.get(`sd_xiaoyuan/runner/balance_log/${id}`, { params })
}

// 校园认证
export function getCampusAuthList(params: any) {
    return request.get('sd_xiaoyuan/campus_auth/list', { params })
}

export function getCampusAuthDetail(id: number) {
    return request.get(`sd_xiaoyuan/campus_auth/detail/${id}`)
}

export function auditCampusAuth(data: any) {
    return request.post('sd_xiaoyuan/campus_auth/audit', data)
}

export function getCampusAuthStat() {
    return request.get('sd_xiaoyuan/campus_auth/stat')
}

export function cancelCampusAuth(id: number) {
    return request.post('sd_xiaoyuan/campus_auth/cancel', { id })
}

// 提现管理
export function getWithdrawList(params: any) {
    return request.get('sd_xiaoyuan/withdraw/list', { params })
}

export function getWithdrawDetail(id: number) {
    return request.get(`sd_xiaoyuan/withdraw/detail/${id}`)
}

export function auditWithdraw(data: any) {
    return request.post('sd_xiaoyuan/withdraw/audit', data)
}

export function transferWithdraw(data: any) {
    return request.post('sd_xiaoyuan/withdraw/transfer', data)
}

export function getWithdrawStat() {
    return request.get('sd_xiaoyuan/withdraw/stat')
}

// 评价管理
export function getEvaluateList(params: any) {
    return request.get('sd_xiaoyuan/evaluate/list', { params })
}

export function getEvaluateDetail(id: number) {
    return request.get(`sd_xiaoyuan/evaluate/detail/${id}`)
}

export function deleteEvaluate(data: any) {
    return request.post('sd_xiaoyuan/evaluate/delete', data)
}

// 申诉管理
export function getAppealList(params: any) {
    return request.get('sd_xiaoyuan/appeal/list', { params })
}

export function getAppealDetail(id: number) {
    return request.get(`sd_xiaoyuan/appeal/detail/${id}`)
}

export function handleAppeal(data: any) {
    return request.post('sd_xiaoyuan/appeal/handle', data)
}

// 优惠券管理
export function getCouponList(params: any) {
    return request.get('sd_xiaoyuan/coupon/list', { params })
}

export function getCouponDetail(id: number) {
    return request.get(`sd_xiaoyuan/coupon/detail/${id}`)
}

export function addCoupon(data: any) {
    return request.post('sd_xiaoyuan/coupon/add', data)
}

export function editCoupon(data: any) {
    return request.post('sd_xiaoyuan/coupon/edit', data)
}

export function deleteCoupon(data: any) {
    return request.post('sd_xiaoyuan/coupon/delete', data)
}

export function setCouponStatus(data: any) {
    return request.post('sd_xiaoyuan/coupon/status', data)
}

// 配置管理
export function getConfig() {
    return request.get('sd_xiaoyuan/config/get')
}

export function saveConfig(data: any) {
    return request.post('sd_xiaoyuan/config/save', data)
}

export const setConfig = saveConfig

export function getFeeConfig() {
    return request.get('sd_xiaoyuan/config/fee')
}

export function setFeeConfig(data: any) {
    return request.post('sd_xiaoyuan/config/fee', data)
}

export function getRangeConfig() {
    return request.get('sd_xiaoyuan/config/range')
}

export function setRangeConfig(data: any) {
    return request.post('sd_xiaoyuan/config/range', data)
}

// 社区管理
export function getCommunityList(params: any) {
    return request.get('sd_xiaoyuan/community/list', { params })
}

export function auditCommunity(data: any) {
    return request.post('sd_xiaoyuan/community/audit', data)
}

export function setTopCommunity(data: any) {
    return request.post('sd_xiaoyuan/community/set_top', data)
}

export function deleteCommunity(data: any) {
    return request.post('sd_xiaoyuan/community/delete', data)
}

// 表白墙管理
export function getConfessionList(params: any) {
    return request.get('sd_xiaoyuan/confession/list', { params })
}

export function auditConfession(data: any) {
    return request.post('sd_xiaoyuan/confession/audit', data)
}

export function setTopConfession(data: any) {
    return request.post('sd_xiaoyuan/confession/set_top', data)
}

export function deleteConfession(data: any) {
    return request.post('sd_xiaoyuan/confession/delete', data)
}

// 签到管理
export function getSignStat() {
    return request.get('sd_xiaoyuan/sign/stat')
}

export function getSignList(params: any) {
    return request.get('sd_xiaoyuan/sign/list', { params })
}

export function getSignConfig() {
    return request.get('sd_xiaoyuan/sign/config')
}

export function saveSignConfig(data: any) {
    return request.post('sd_xiaoyuan/sign/config', data)
}

// 邀请分销管理
export function getInviteStat() {
    return request.get('sd_xiaoyuan/invite/stat')
}

export function getInviteList(params: any) {
    return request.get('sd_xiaoyuan/invite/list', { params })
}

export function getInviteConfig() {
    return request.get('sd_xiaoyuan/invite/config')
}

export function saveInviteConfig(data: any) {
    return request.post('sd_xiaoyuan/invite/config', data)
}

// 房屋租赁管理
export function getHouseList(params: any) {
    return request.get('sd_xiaoyuan/house/list', { params })
}

export function getHouseInfo(id: number) {
    return request.get(`sd_xiaoyuan/house/info/${id}`)
}

export function auditHouse(data: any) {
    return request.post('sd_xiaoyuan/house/audit', data)
}

export function addHouse(data: any) {
    return request.post('sd_xiaoyuan/house/add', data)
}

export function editHouse(data: any) {
    return request.post('sd_xiaoyuan/house/edit', data)
}

export function deleteHouse(data: any) {
    return request.post('sd_xiaoyuan/house/delete', data)
}

export function getHouseOrderList(params: any) {
    return request.get('sd_xiaoyuan/house_order/list', { params })
}

export function handleHouseOrder(data: any) {
    return request.post('sd_xiaoyuan/house_order/handle', data)
}

export function refundHouseDeposit(data: any) {
    return request.post('sd_xiaoyuan/house_order/refund_deposit', data)
}

export function refundHouseAll(data: any) {
    return request.post('sd_xiaoyuan/house_order/refund_all', data)
}

// 社区分类管理
export function getCategoryList() {
    return request.get('sd_xiaoyuan/community_category/list')
}

export function addCategory(data: any) {
    return request.post('sd_xiaoyuan/community_category/add', data)
}

export function editCategory(data: any) {
    return request.post('sd_xiaoyuan/community_category/edit', data)
}

export function deleteCategory(data: any) {
    return request.post('sd_xiaoyuan/community_category/delete', data)
}

// 评论管理
export function getCommunityCommentList(params: any) {
    return request.get('sd_xiaoyuan/comment/community', { params })
}

export function getConfessionCommentList(params: any) {
    return request.get('sd_xiaoyuan/comment/confession', { params })
}

export function auditComment(data: any) {
    return request.post('sd_xiaoyuan/comment/audit', data)
}

export function deleteComment(data: any) {
    return request.post('sd_xiaoyuan/comment/delete', data)
}

// 数据统计
export function getStatOverview() {
    return request.get('sd_xiaoyuan/stat/overview')
}

export function getStatTrend(params: any) {
    return request.get('sd_xiaoyuan/stat/trend', { params })
}

// 接单员等级管理
export function getRunnerLevelList() {
    return request.get('sd_xiaoyuan/runner_level/list')
}

export function saveRunnerLevels(data: any) {
    return request.post('sd_xiaoyuan/runner_level/save', data)
}

export function getRunnerInviteConfig() {
    return request.get('sd_xiaoyuan/runner_level/invite_config')
}

export function saveRunnerInviteConfig(data: any) {
    return request.post('sd_xiaoyuan/runner_level/invite_config', data)
}

export function getRunnerInviteRewardList(params: any) {
    return request.get('sd_xiaoyuan/runner_level/invite_rewards', { params })
}

// 游戏陪玩管理
export function getGameCompanionList(params: any) {
    return request.get('sd_xiaoyuan/game_companion/list', { params })
}

export function getGameCompanionDetail(id: number) {
    return request.get(`sd_xiaoyuan/game_companion/detail/${id}`)
}

export function auditGameCompanion(data: any) {
    return request.post('sd_xiaoyuan/game_companion/audit', data)
}

export function setTopGameCompanion(data: any) {
    return request.post('sd_xiaoyuan/game_companion/set_top', data)
}

export function deleteGameCompanion(data: any) {
    return request.post('sd_xiaoyuan/game_companion/del', data)
}

export function getGameCompanionStat() {
    return request.get('sd_xiaoyuan/game_companion/stat')
}

// 学校管理
export function getSchoolList(params?: any) {
    return request.get('sd_xiaoyuan/school/list', { params })
}

export function getAllSchools() {
    return request.get('sd_xiaoyuan/school/all')
}
