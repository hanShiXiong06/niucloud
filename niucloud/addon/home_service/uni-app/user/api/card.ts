import request from '@/utils/request'

/***************************************************** 次卡管理 ****************************************************/

/**
 * 次卡列表(分页)
 * @param params
 * @returns
 */
export function getCardList(params: Record<string, any>) {
    return request.get(`home_service/card`, params)
}

/**
 * 我的次卡列表(分页)
 * @param params
 * @returns
 */
export function getMyCardList(params: Record<string, any>) {
    return request.get(`home_service/member/card`, params)
}


/**
 * 我的次卡状态
 * @param params
 * @returns
 */
export function getMyCardStatus(params: Record<string, any>) {
    return request.get(`home_service/member/card/status`, params)
}


/**
 * 次卡详情
 * @param params
 * @returns
 */
export function getCardDetail(params: Record<string, any>) {
    return request.get(`home_service/card/${params.card_id}`,params)
}

/**
 * 次卡订单创建
 */
export function orderCreate(params: Record<string, any>) {
    return request.post(`home_service/card_order/create`, params)
}

/**
 * 次卡使用列表
 */
export function getCardItem(params: Record<string, any>) {
    return request.get(`home_service/member/card/item`, params)
}




/**
 * 次卡使用记录
 */
export function getCardUseList(params: Record<string, any>) {
    return request.get(`home_service/member/card/records`, params )
}

/**
 * 次卡类型
 */
export function gettabStatus() {
    return request.get(`home_service/card/dict` )
}
