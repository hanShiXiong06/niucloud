import request from '@/utils/request'

/**
 * 生成回收订单分享链接
 */
export function generateOrderShortLink(params: { order_id: number | string; order_no?: string }) {
    return request.post('recycle/sys/short_link/order', params)
}
