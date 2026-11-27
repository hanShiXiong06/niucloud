import request from '@/utils/request'

/**
 * 获取商品分类树结构
 * @returns
 */
export function getCategoryTree() {
    return request.get(`basic/site/goods/tree`)
}

/**
 * 获取商品品牌列表
 * @param params
 * @returns
 */
export function getBrandList(params: Record<string, any>) {
    return request.get(`basic/site/goods/brand/list`, params)
}

/**
 * 获取商品服务列表
 * @param params
 * @returns
 */
export function getServeList(params: Record<string, any>) {
    return request.get(`basic/site/goods/service/list`, params)
}


/**
 * 切换营业状态
 */
export function updateBusinessStatus(params: Record<string, any>) {
    return request.put(`shop/site/shop/business_status`, params, { showSuccessMessage: true })
}