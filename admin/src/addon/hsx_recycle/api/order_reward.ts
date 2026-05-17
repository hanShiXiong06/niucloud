import request from '@/utils/request'

/**
 * 获取订单完成奖励配置
 */
export function getOrderRewardConfig() {
    return request.get('recycle/order_reward/getconfig')
}

/**
 * 设置订单完成奖励配置
 */
export function setOrderRewardConfig(params: Record<string, any>) {
    return request.post('recycle/order_reward/setconfig', params)
}
