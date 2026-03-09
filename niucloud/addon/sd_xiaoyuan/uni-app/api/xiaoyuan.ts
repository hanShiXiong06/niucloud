import request from '@/utils/request'

// 公共接口
export function getConfig() {
    return request.get('sd_xiaoyuan/common/config')
}

export function getTaskTypes() {
    return request.get('sd_xiaoyuan/common/task_types')
}

export function getHomeStats(params?: any) {
    return request.get('sd_xiaoyuan/common/home_stats', params)
}

export function getNearbyRunners(params: any) {
    return request.get('sd_xiaoyuan/common/nearby_runners', params)
}

// 订单接口
export function getOrderList(params: any) {
    return request.get('sd_xiaoyuan/order/list', params)
}

export function getOrderHall(params: any) {
    return request.get('sd_xiaoyuan/order/hall', params)
}

export function getOrderDetail(id: number) {
    return request.get(`sd_xiaoyuan/order/detail/${id}`)
}

export function createOrder(data: any) {
    return request.post('sd_xiaoyuan/order/create', data)
}

export function cancelOrder(data: any) {
    return request.post('sd_xiaoyuan/order/cancel', data)
}

export function payOrder(data: any) {
    return request.post('sd_xiaoyuan/order/pay', data)
}

export function calculateFee(params: any) {
    return request.get('sd_xiaoyuan/order/calculate_fee', params)
}

export function getRunnerLocation(id: number) {
    return request.get(`sd_xiaoyuan/order/runner_location/${id}`)
}

export function confirmOrder(data: any) {
    return request.post('sd_xiaoyuan/order/confirm', data)
}

export function tipOrder(data: any) {
    return request.post('sd_xiaoyuan/order/tip', data)
}

// 校园认证接口
export function getCampusAuthInfo() {
    return request.get('sd_xiaoyuan/campus_auth/info')
}

export function applyCampusAuth(data: any) {
    return request.post('sd_xiaoyuan/campus_auth/apply', data)
}

export function getCampusAuthStatus() {
    return request.get('sd_xiaoyuan/campus_auth/status')
}

// 地址接口
export function getAddressList() {
    return request.get('sd_xiaoyuan/address/list')
}

export function getAddressDetail(id: number) {
    return request.get(`sd_xiaoyuan/address/detail/${id}`)
}

export function addAddress(data: any) {
    return request.post('sd_xiaoyuan/address/add', data)
}

export function editAddress(data: any) {
    return request.post('sd_xiaoyuan/address/edit', data)
}

export function deleteAddress(data: any) {
    return request.post('sd_xiaoyuan/address/delete', data)
}

export function setDefaultAddress(data: any) {
    return request.post('sd_xiaoyuan/address/set_default', data)
}

export function getDefaultAddress() {
    return request.get('sd_xiaoyuan/address/default')
}

// 优惠券接口
export function getCouponList(params: any) {
    return request.get('sd_xiaoyuan/coupon/list', params)
}

export function receiveCoupon(data: any) {
    return request.post('sd_xiaoyuan/coupon/receive', data)
}

export function getMyCoupons(params: any) {
    return request.get('sd_xiaoyuan/coupon/my', params)
}

export function getAvailableCoupons(params: any) {
    return request.get('sd_xiaoyuan/coupon/available', params)
}

// 评价接口
export function getEvaluateList(runnerId: number, params: any) {
    return request.get(`sd_xiaoyuan/evaluate/list/${runnerId}`, params)
}

export function addEvaluate(data: any) {
    return request.post('sd_xiaoyuan/evaluate/add', data)
}

export function getMyEvaluates(params: any) {
    return request.get('sd_xiaoyuan/evaluate/my', params)
}

// 社区接口
export function getCommunityList(params: any) {
    return request.get('sd_xiaoyuan/community/list', params)
}

export function getCommunityDetail(id: number) {
    return request.get(`sd_xiaoyuan/community/detail/${id}`)
}

export function publishCommunity(data: any) {
    return request.post('sd_xiaoyuan/community/publish', data)
}

export function deleteCommunity(data: any) {
    return request.post('sd_xiaoyuan/community/delete', data)
}

export function likeCommunity(data: any) {
    return request.post('sd_xiaoyuan/community/like', data)
}

export function getMyCommunity(params: any) {
    return request.get('sd_xiaoyuan/community/my', params)
}

// 表白墙接口
export function getConfessionList(params: any) {
    return request.get('sd_xiaoyuan/confession/list', params)
}

export function getConfessionDetail(id: number) {
    return request.get(`sd_xiaoyuan/confession/detail/${id}`)
}

export function publishConfession(data: any) {
    return request.post('sd_xiaoyuan/confession/publish', data)
}

export function deleteConfession(data: any) {
    return request.post('sd_xiaoyuan/confession/delete', data)
}

export function likeConfession(data: any) {
    return request.post('sd_xiaoyuan/confession/like', data)
}

// 课表接口
export function getSchedule(params: any) {
    return request.get('sd_xiaoyuan/schedule/index', params)
}

export function addCourse(data: any) {
    return request.post('sd_xiaoyuan/schedule/add', data)
}

export function getCourseDetail(params: any) {
    return request.get('sd_xiaoyuan/schedule/detail', params)
}

export function editCourse(data: any) {
    return request.post('sd_xiaoyuan/schedule/edit', data)
}

export function deleteCourse(data: any) {
    return request.post('sd_xiaoyuan/schedule/delete', data)
}

export function clearSchedule(data: any) {
    return request.post('sd_xiaoyuan/schedule/clear', data)
}

// 签到接口
export function doSign() {
    return request.post('sd_xiaoyuan/sign/sign', {})
}

export function getSignStatus() {
    return request.get('sd_xiaoyuan/sign/status')
}

export function getSignHistory(params: any) {
    return request.get('sd_xiaoyuan/sign/history', params)
}

// 邀请有礼接口
export function bindInvite(data: any) {
    return request.post('sd_xiaoyuan/invite/bind', data)
}

export function getInviteStat() {
    return request.get('sd_xiaoyuan/invite/stat')
}

export function getInviteRelation() {
    return request.get('sd_xiaoyuan/invite/relation')
}

export function getInvitePoster() {
    return request.get('sd_xiaoyuan/invite/poster')
}

export function getMyTeam(params: any) {
    return request.get('sd_xiaoyuan/invite/team', params)
}

export function getTeamStat() {
    return request.get('sd_xiaoyuan/invite/team_stat')
}

// 房屋租赁接口
export function getHouseList(params: any) {
    return request.get('sd_xiaoyuan/house/list', params)
}

export function getHouseDetail(id: number) {
    return request.get(`sd_xiaoyuan/house/detail/${id}`)
}

export function publishHouse(data: any) {
    return request.post('sd_xiaoyuan/house/publish', data)
}

export function editHouse(data: any) {
    return request.post('sd_xiaoyuan/house/edit', data)
}

export function deleteHouse(data: any) {
    return request.post('sd_xiaoyuan/house/delete', data)
}

export function offlineHouse(data: any) {
    return request.post('sd_xiaoyuan/house/offline', data)
}

export function getMyHouse(params: any) {
    return request.get('sd_xiaoyuan/house/my', params)
}

export function createHouseOrder(data: any) {
    return request.post('sd_xiaoyuan/house/order', data)
}

export function getMyHouseOrders(params: any) {
    return request.get('sd_xiaoyuan/house/my_orders', params)
}

// 评论接口
export function getCommunityComments(params: any) {
    return request.get('sd_xiaoyuan/comment/community', params)
}

export function addCommunityComment(data: any) {
    return request.post('sd_xiaoyuan/comment/community/add', data)
}

export function getConfessionComments(params: any) {
    return request.get('sd_xiaoyuan/comment/confession', params)
}

export function addConfessionComment(data: any) {
    return request.post('sd_xiaoyuan/comment/confession/add', data)
}

export function deleteComment(data: any) {
    return request.post('sd_xiaoyuan/comment/delete', data)
}

// 社区分类接口
export function getCommunityCategories() {
    return request.get('sd_xiaoyuan/community/categories')
}

export function getCommunityStats() {
    return request.get('sd_xiaoyuan/community/stats')
}

// 我的表白接口
export function getMyConfession(params: any) {
    return request.get('sd_xiaoyuan/confession/my', params)
}

export function getConfessionStats() {
    return request.get('sd_xiaoyuan/confession/stats')
}

// 骑手等级接口
export function getRunnerLevels() {
    return request.get('sd_xiaoyuan/runner_level/levels')
}

export function getRunnerLevelInfo(params: any) {
    return request.get('sd_xiaoyuan/runner_level/info', params)
}

export function updateRunnerLocation(data: any) {
    return request.post('sd_xiaoyuan/runner_level/update_location', data)
}

export function getRunnerLocationById(params: any) {
    return request.get('sd_xiaoyuan/runner_level/location', params)
}

export function getNearbyRunnersWithLocation(params: any) {
    return request.get('sd_xiaoyuan/runner_level/nearby', params)
}

export function bindRunnerInviter(data: any) {
    return request.post('sd_xiaoyuan/runner_level/bind_inviter', data)
}

export function getInvitedRunners(params: any) {
    return request.get('sd_xiaoyuan/runner_level/invited_runners', params)
}

export function getRunnerInviteRewards(params: any) {
    return request.get('sd_xiaoyuan/runner_level/invite_rewards', params)
}

export function getRunnerInviteStats(params: any) {
    return request.get('sd_xiaoyuan/runner_level/invite_stats', params)
}

// 学校接口
export function getSchoolList(params?: any) {
    return request.get('sd_xiaoyuan/school/list', params)
}

export function getSchoolInfo(id: number) {
    return request.get('sd_xiaoyuan/school/info', { id })
}

export function getSchoolCampusList(school_id: number) {
    return request.get('sd_xiaoyuan/school/campus_list', { school_id })
}

// 任务悬赏接口
export function getTaskTypeList() {
    return request.get('sd_xiaoyuan/task/type_list')
}

export function getTaskList(params: any) {
    return request.get('sd_xiaoyuan/task/list', params)
}

export function getTaskInfo(id: number) {
    return request.get('sd_xiaoyuan/task/info', { id })
}

export function publishTask(data: any) {
    return request.post('sd_xiaoyuan/task/publish', data)
}

export function payTask(data: any) {
    return request.post('sd_xiaoyuan/task/pay', data)
}

export function acceptTask(data: any) {
    return request.post('sd_xiaoyuan/task/accept', data)
}

export function startTask(data: any) {
    return request.post('sd_xiaoyuan/task/start', data)
}

export function submitCompleteTask(data: any) {
    return request.post('sd_xiaoyuan/task/submit_complete', data)
}

export function confirmCompleteTask(data: any) {
    return request.post('sd_xiaoyuan/task/confirm_complete', data)
}

export function cancelTask(data: any) {
    return request.post('sd_xiaoyuan/task/cancel', data)
}

export function getMyPublishTasks(params: any) {
    return request.get('sd_xiaoyuan/task/my_publish', params)
}

export function getMyAcceptTasks(params: any) {
    return request.get('sd_xiaoyuan/task/my_accept', params)
}

// 拼单好饭接口
export function getGroupOrderTypeList() {
    return request.get('sd_xiaoyuan/group_order/type_list')
}

export function getGroupOrderList(params: any) {
    return request.get('sd_xiaoyuan/group_order/list', params)
}

export function getGroupOrderStats() {
    return request.get('sd_xiaoyuan/group_order/stats')
}

export function getGroupOrderInfo(id: number) {
    return request.get('sd_xiaoyuan/group_order/info', { id })
}

export function createGroupOrder(data: any) {
    return request.post('sd_xiaoyuan/group_order/create', data)
}

export function joinGroupOrder(data: any) {
    return request.post('sd_xiaoyuan/group_order/join', data)
}

export function quitGroupOrder(data: any) {
    return request.post('sd_xiaoyuan/group_order/quit', data)
}

export function cancelGroupOrder(data: any) {
    return request.post('sd_xiaoyuan/group_order/cancel', data)
}

export function confirmGroupOrderSuccess(data: any) {
    return request.post('sd_xiaoyuan/group_order/confirm_success', data)
}

export function completeGroupOrder(data: any) {
    return request.post('sd_xiaoyuan/group_order/complete', data)
}

export function getMyCreateGroupOrders(params: any) {
    return request.get('sd_xiaoyuan/group_order/my_create', params)
}

export function getMyJoinGroupOrders(params: any) {
    return request.get('sd_xiaoyuan/group_order/my_join', params)
}

// 二手交易接口
export function getSecondhandCategoryList() {
    return request.get('sd_xiaoyuan/secondhand/category_list')
}

export function getSecondhandTradeMethodList() {
    return request.get('sd_xiaoyuan/secondhand/trade_method_list')
}

export function getSecondhandList(params: any) {
    return request.get('sd_xiaoyuan/secondhand/list', params)
}

export function getSecondhandInfo(id: number) {
    return request.get('sd_xiaoyuan/secondhand/info', { id })
}

export function getSecondhandDetail(id: number) {
    return request.get(`sd_xiaoyuan/secondhand/detail/${id}`)
}

export function publishSecondhand(data: any) {
    return request.post('sd_xiaoyuan/secondhand/publish', data)
}

export function editSecondhand(data: any) {
    return request.post('sd_xiaoyuan/secondhand/edit', data)
}

export function offSecondhand(data: any) {
    return request.post('sd_xiaoyuan/secondhand/off', data)
}

export function onSecondhand(data: any) {
    return request.post('sd_xiaoyuan/secondhand/on', data)
}

export function soldSecondhand(data: any) {
    return request.post('sd_xiaoyuan/secondhand/sold', data)
}

export function delSecondhand(data: any) {
    return request.post('sd_xiaoyuan/secondhand/del', data)
}

export function wantSecondhand(data: any) {
    return request.post('sd_xiaoyuan/secondhand/want', data)
}

export function getMyPublishSecondhand(params: any) {
    return request.get('sd_xiaoyuan/secondhand/my_publish', params)
}

// 失物招领接口
export function getLostFoundTypeList() {
    return request.get('sd_xiaoyuan/lost_found/type_list')
}

export function getLostFoundCategoryList() {
    return request.get('sd_xiaoyuan/lost_found/category_list')
}

export function getLostFoundList(params: any) {
    return request.get('sd_xiaoyuan/lost_found/list', params)
}

export function getLostFoundStats(params: any = {}) {
    return request.get('sd_xiaoyuan/lost_found/stats', params)
}

export function getLostFoundInfo(id: number) {
    return request.get('sd_xiaoyuan/lost_found/info', { id })
}

export function getLostFoundDetail(id: number) {
    return request.get(`sd_xiaoyuan/lost_found/detail/${id}`)
}

export function publishLostFound(data: any) {
    return request.post('sd_xiaoyuan/lost_found/publish', data)
}

export function editLostFound(data: any) {
    return request.post('sd_xiaoyuan/lost_found/edit', data)
}

export function resolveLostFound(data: any) {
    return request.post('sd_xiaoyuan/lost_found/resolve', data)
}

export function closeLostFound(data: any) {
    return request.post('sd_xiaoyuan/lost_found/close', data)
}

export function delLostFound(data: any) {
    return request.post('sd_xiaoyuan/lost_found/del', data)
}

export function contactLostFound(data: any) {
    return request.post('sd_xiaoyuan/lost_found/contact', data)
}

export function getMyPublishLostFound(params: any) {
    return request.get('sd_xiaoyuan/lost_found/my_publish', params)
}

// 系统消息接口
export function getMessageTypeList() {
    return request.get('sd_xiaoyuan/message/type_list')
}

export function getMessageList(params: any) {
    return request.get('sd_xiaoyuan/message/list', params)
}

export function getUnreadMessageCount(params?: any) {
    return request.get('sd_xiaoyuan/message/unread_count', params)
}

export function getUnreadMessageCountByType() {
    return request.get('sd_xiaoyuan/message/unread_count_by_type')
}

export function readMessage(data: any) {
    return request.post('sd_xiaoyuan/message/read', data)
}

export function readAllMessages(data?: any) {
    return request.post('sd_xiaoyuan/message/read_all', data)
}

export function delMessage(data: any) {
    return request.post('sd_xiaoyuan/message/del', data)
}

export function clearMessages(data?: any) {
    return request.post('sd_xiaoyuan/message/clear', data)
}

// 钱包接口
export function getWalletBalance() {
    return request.get('sd_xiaoyuan/wallet/balance')
}

export function getWalletLogList(params: any) {
    return request.get('sd_xiaoyuan/wallet/log_list', params)
}

// 信誉分接口
export function getCreditInfo() {
    return request.get('sd_xiaoyuan/credit/info')
}

export function getCreditLogList(params: any) {
    return request.get('sd_xiaoyuan/credit/log_list', params)
}

export function checkCreditCanOperate() {
    return request.get('sd_xiaoyuan/credit/check')
}

// 快递站点接口
export function getExpressStations(params?: any) {
    return request.get('sd_xiaoyuan/express/stations', params)
}

export function getPackagePrices(params?: any) {
    return request.get('sd_xiaoyuan/express/package_prices', params)
}

// 帮帮忙接口
export function createHelpOrder(data: any) {
    return request.post('sd_xiaoyuan/help/create', data)
}

// 代占座位接口
export function createSeatOrder(data: any) {
    return request.post('sd_xiaoyuan/seat/create', data)
}

export function getHelpList(params: any) {
    return request.get('sd_xiaoyuan/help/list', params)
}

export function getHelpDetail(id: number) {
    return request.get(`sd_xiaoyuan/help/detail/${id}`)
}

export function acceptHelp(data: any) {
    return request.post('sd_xiaoyuan/help/accept', data)
}

export function cancelHelp(data: any) {
    return request.post('sd_xiaoyuan/help/cancel', data)
}

export function completeHelp(data: any) {
    return request.post('sd_xiaoyuan/help/complete', data)
}

export function getMyPublishHelp(params: any) {
    return request.get('sd_xiaoyuan/help/my_publish', params)
}

export function getMyAcceptHelp(params: any) {
    return request.get('sd_xiaoyuan/help/my_accept', params)
}

// 积分商城接口
export function getPointsGoodsList(params: any) {
    return request.get('sd_xiaoyuan/points_mall/goods_list', params)
}

export function getPointsGoodsDetail(params: any) {
    return request.get('sd_xiaoyuan/points_mall/goods_detail', params)
}

export function exchangePointsGoods(data: any) {
    return request.post('sd_xiaoyuan/points_mall/exchange', data)
}

export function getMyPointsOrders(params: any) {
    return request.get('sd_xiaoyuan/points_mall/my_orders', params)
}

export function getPointsOrderDetail(params: any) {
    return request.get('sd_xiaoyuan/points_mall/order_detail', params)
}

export function getPointsLogistics(params: any) {
    return request.get('sd_xiaoyuan/points_mall/logistics', params)
}

export function getMyPoints() {
    return request.get('sd_xiaoyuan/member/points')
}

export function getPointsRecord(params: any) {
    return request.get('sd_xiaoyuan/member/points_record', params)
}

// 留言板接口
export function getGuestbookList(params: any) {
    return request.get('sd_xiaoyuan/guestbook/list', params)
}

export function getGuestbookDetail(id: number) {
    return request.get(`sd_xiaoyuan/guestbook/detail/${id}`)
}

export function publishGuestbook(data: any) {
    return request.post('sd_xiaoyuan/guestbook/publish', data)
}

export function deleteGuestbook(data: any) {
    return request.post('sd_xiaoyuan/guestbook/delete', data)
}

export function getMyGuestbook(params: any) {
    return request.get('sd_xiaoyuan/guestbook/my', params)
}

export function getGuestbookStats() {
    return request.get('sd_xiaoyuan/guestbook/stats')
}

// 文档上传接口
export function uploadDocument(filePath: string) {
    return request.upload('sd_xiaoyuan/upload/document', { filePath, name: 'file' })
}
