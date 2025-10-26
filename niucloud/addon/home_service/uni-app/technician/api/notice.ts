import request from '@/utils/request'

/***************************************************** 师傅订单管理 ****************************************************/
/**
 * 消息列表
 * @param params
 * @returns
 */
export function getNoticeList(params: Record<string, any>) {
    return request.get(`home_service/technician/notice/list`, params)
}

/**
 * 消息状态
 * @param params
 * @returns
 */
export function getNoticeStatus(params: Record<string, any>) {
    return request.get(`home_service/technician/notice/source`, params)
}

