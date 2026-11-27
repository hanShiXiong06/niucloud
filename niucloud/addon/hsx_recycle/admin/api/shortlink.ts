import request from '@/utils/request'

/**
 * 生成单个小程序 Short Link
 */
export function generateShortLink(params: Record<string, any>) {
    return request.post('recycle/sys/short_link/generate', params)
}

/**
 * 生成回收订单分享链接
 */
export function generateOrderShortLink(params: { order_id: number | string; order_no?: string }) {
    return request.post('recycle/sys/short_link/order', params)
}
