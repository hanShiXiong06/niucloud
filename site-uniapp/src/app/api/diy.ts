import request from '@/utils/request'

/**
 * 获取页面分享信息
 */
export function getShareInfo(params: Record<string, any>) {
    return request.get('diy/share', params)
}