import request from '@/utils/request'

/**
 * 生成单个小程序 Short Link
 */
export function generateShortLink(params: Record<string, any>) {
    return request.post('phone_shop/sys/short_link/generate', params)
}

/**
 * 批量生成商品 Short Link
 */
export function batchGenerateShortLink(params: Record<string, any>) {
    return request.post('phone_shop/sys/short_link/batch_generate', params)
}
