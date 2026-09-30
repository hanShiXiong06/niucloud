import request from '@/utils/request'

/**
 * 获取评论设置
 */
export function getEvaluateConfig() {
    return request.get(`phone_shop/goods/evaluate/config`)
}