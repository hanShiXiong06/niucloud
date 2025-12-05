import request from '@/utils/request'

/**
 * 获取统计总数
 */
export function getShopCountList() {
    return request.get(`phone_shop/stat/total`)
}

/**
 * 获取今日统计总数
 */
export function getShopTodayCountList() {
    return request.get(`phone_shop/stat/today`)
}

/**
 * 获取昨日统计总数
 */
export function getShopYesterdayCountList() {
    return request.get(`phone_shop/stat/yesterday`)
}

/**
 * 获取统计图数据
 * @param timeRange 时间范围：today, yesterday, week, month
 */
export function getShopStat(timeRange: string = 'today') {
    return request.get(`phone_shop/stat`, {
        params: { time_range: timeRange }
    })
}

/**
 * 获取订单统计
 */
export function getShopOrderStat() {
    return request.get(`phone_shop/stat/order`)
}

/**
 * 获取商品统计
 */
export function getShopGoodsStat() {
    return request.get(`phone_shop/stat/goods`)
}

