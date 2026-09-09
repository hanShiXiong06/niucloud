import request from '@/utils/request'

/**
 * 获取商品分类模板配置
 */
export function getGoodsCategoryConfig() {
    return request.get(`phone_shop/goods/category/config`)
}

/**
 * 获取商品分类树结构
 */
export function getGoodsCategoryTree() {
    return request.get(`phone_shop/goods/category/tree`)
}

/**
 * 获取商品分类列表
 */
export function getGoodsCategoryList(params: Record<string, any>) {
    return request.get(`phone_shop/goods/category/list`, params)
}

/**
 * 获取商品列表
 */
export function getGoodsPages(params: Record<string, any>) {
    return request.get(`phone_shop/goods/pages`, params)
}

/**
 * 获取商品列表筛选项
 */
export function getGoodsFilterOptions(params: Record<string, any> = {}) {
    return request.get(`phone_shop/goods/filter/options`, params)
}

/**
 * 获取当前会员的商品筛选订阅
 */
export function getGoodsSubscriptionList(params: Record<string, any> = {}) {
    return request.get(`phone_shop/goods/subscription`, params)
}

export function getGoodsSubscriptionCapability() {
    return request.get('phone_shop/goods/subscription/capability', {}, { showErrorMessage: false })
}

/**
 * 查询指定筛选条件是否已订阅
 */
export function getGoodsSubscriptionStatus(rule: Record<string, any>) {
    return request.post(`phone_shop/goods/subscription/status`, { rule })
}

/**
 * 订阅当前筛选条件
 */
export function addGoodsSubscription(data: Record<string, any>) {
    return request.post(`phone_shop/goods/subscription`, data, { showSuccessMessage: true })
}

/**
 * 取消商品筛选订阅
 */
export function cancelGoodsSubscription(data: Record<string, any>) {
    return request.put(`phone_shop/goods/subscription/cancel`, data, { showSuccessMessage: true })
}

/**
 * 仓库切换选项(本地仓/代理仓)
 */
export function getGoodsWarehouses() {
    return request.get(`phone_shop/goods/warehouses`)
}

/**
 * 获取商品详情
 */
export function getGoodsDetail(params: Record<string, any>) {
    return request.get(`phone_shop/goods/detail`, params)
}

/**
 * 获取商品规格
 */
export function getGoodsSku(sku_id: any) {
    return request.get(`phone_shop/goods/sku/${ sku_id }`)
}

/**
 * 收藏列表
 */
export function getCollectList(params: Record<string, any>) {
    return request.get(`phone_shop/goods/collect`, params)
}

/**
 * 取消收藏
 */
export function cancelCollect(params: Record<string, any>) {
    return request.put(`phone_shop/goods/collect`, params, { showSuccessMessage: true })
}

/**
 *  收藏
 */
export function collect(goods_id: any) {
    return request.post(`phone_shop/goods/collect/${ goods_id }`)
}


/**
 * 获取评价
 */
export function getEvaluateList(goods_id: any) {
    return request.get(`phone_shop/goods/evaluate/list`, { goods_id })
}

/**
 * 获取商品列表供组件调用
 */
export function getGoodsComponents(params: Record<string, any>) {
    return request.get(`phone_shop/goods/components`, params)
}

/**
 * 获取商品满减信息
 */
export function getManjian(params: Record<string, any>) {
    return request.get(`phone_shop/manjian/info`, params)
}

/**
 *  商品足迹添加
 */
export function browse(params: Record<string, any>) {
    return request.post(`phone_shop/goods/browse`, params, { showSuccessMessage: false })
}

/**
 *  商品足迹列表
 */
export function getBrowse(params: Record<string, any>) {
    return request.get(`phone_shop/goods/browse`, params)
}

/**
 * 商品足迹删除
 */
export function delBrowse(params: Record<string, any>) {
    return request.delete(`phone_shop/goods/browse`, params)
}

/**
 *  获取商品搜索配置
 */
export function getGoodsConfigSearch() {
    return request.get(`phone_shop/goods/config/search`)
}
