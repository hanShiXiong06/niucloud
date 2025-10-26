import request from '@/utils/request'

/***************************************************** 项目管理 ****************************************************/
/**
 * 项目分类
 * @returns
 */
export function getCategory() {
    return request.get('home_service/category')
}

/**
 * 项目列表(分页)
 * @param params
 * @returns
 */
export function getGoodsList(params: Record<string, any>) {
    return request.get(`home_service/goods`, params)
}

/**
 * 项目详情
 * @param params
 * @returns
 */
export function getGoodsDetail(params: Record<string, any>) {
    return request.get(`home_service/goods/detail`, params)
}

/**
 * 获取项目列表供组件调用
 */
export function getGoodsComponents(params: Record<string, any>) {
    return request.get(`home_service/goods/components`, params)
}


/**
 * 获取次卡列表供组件调用
 */
export function getCardComponents(params: Record<string, any>) {
    return request.get(`home_service/card/components`, params)
}

/**
 * 预约设置
 * @returns
 */
export function getReserveConfig() {
    return request.get('home_service/order/config')
}

/**
 * 获取附近师傅
 */
export function getNearbyTechs(data: Record<string, any>) {
    return request.get(`home_service/technician/nearbyTechs`, data)
}

/**
 * 获取商品分类列表
 */
export function getGoodsCategoryList(params: Record<string, any>) {
    return request.get(`home_service/goods/category/list`, params)
}

/**
 * 获取师傅次卡
 */
export function getCardList(data: Record<string, any>) {
    return request.get(`home_service/card`, data)
}

/**
 * 获取师傅评价
 */
export function getEvaluateList(data: Record<string, any>) {
    return request.get(`home_service/order/goodsevaluate`, data)
}


/**
 * 添加足迹
 */
export function addbrowseGoods(data: Record<string, any>) {
    return request.post(`home_service/goods/browse`, data)
}




