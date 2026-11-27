import request from '@/utils/request'

/**
 * 获取商品列表
 * @param params
 * @returns
 */
export function getGoodsPageList(params: Record<string, any>) {
    return request.get(`mall/site/goods`, params)
}

/**
 * 获取商品详情
 * @param goods_id 商品goods_id
 * @returns
 */
export function getGoodsInfo(goods_id: number) {
    return request.get(`mall/site/goods/${goods_id}`);
}

/**
 * 添加实物商品
 * @param params
 * @returns
 */
export function addGoods(params: Record<string, any>) {
    return request.post('mall/site/goods', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑实物商品
 * @param params
 */
export function editGoods(params: Record<string, any>) {
    return request.put(`mall/site/goods/${params.goods_id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 获取商品添加/编辑初始化数据
 * @param params
 */
export function getGoodsInit(params: Record<string, any>) {
    return request.get(`mall/site/goods/init`, params);
}

/**
 * 添加虚拟商品
 * @param params
 * @returns
 */
export function addVirtualGoods(params: Record<string, any>) {
    return request.post('mall/site/goods/virtual', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑虚拟商品
 * @param params
 */
export function editVirtualGoods(params: Record<string, any>) {
    return request.put(`mall/site/goods/virtual/${params.goods_id}`, params, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}


/**
 * 获取虚拟商品添加/编辑初始化数据
 * @param params
 */
export function getVirtualGoodsInit(params: Record<string, any>) {
    return request.get(`mall/site/goods/virtual/init`, params);
}

/**
 * 获取商品类型
 * @returns
 */
export function getGoodsType() {
    return request.get(`mall/site/goods/type`);
}

/**
 * 获取商品状态
 * @returns
 */
export function getGoodsStatus() {
    return request.get(`mall/site/goods/status`);
}

/**
 * 获取商品标签列表
 * @param params
 * @returns
 */
export function getLabelList(params: Record<string, any>) {
    return request.get(`mall/site/goods/label/list`, params)
}

/**
 * 修改商品上下架状态
 * @param params
 */
export function editGoodsStatus(params: Record<string, any>) {
    return request.put(`mall/site/goods/status`, params, { showSuccessMessage: true })
}

/**
 * 删除商品
 * @param params
 * @returns
 */
export function deleteGoods(params: Record<string, any>) {
    return request.put(`mall/site/goods/delete`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/** 查询商品参与营销活动的数量
 * @param params
 * @returns
 */
export function getActiveGoodsCount(params: Record<string, any>) {
    return request.get(`mall/site/goods/active/count`, params)
}

/**
 * 获取商品SKU规格列表
 * @param params
 * @returns
 */
export function getGoodsSkuList(params: Record<string, any>) {
    return request.get(`mall/site/goods/sku`, params)
}

/**
 * 编辑商品SKU规格价格
 * @param params
 * @returns
 */
export function editGoodsListPrice(params: Record<string, any>) {
    return request.put(`mall/site/goods/sku/price`, params, { showSuccessMessage: true })
}

/**
 * 编辑商品SKU规格库存
 * @param params
 * @returns
 */
export function editGoodsListStock(params: Record<string, any>) {
    return request.put(`mall/site/goods/sku/stock`, params, { showSuccessMessage: true })
}