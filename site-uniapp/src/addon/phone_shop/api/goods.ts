import request from '@/utils/request'

/**
 * 商品列表
 */
export function getGoodsList(params: Record<string, any> = {}) {
    return request.get('phone_shop/goods', params)
}

/**
 * 商品详情（主表，不含 SKU 价格）
 */
export function getGoodsInfo(id: number | string) {
    return request.get(`phone_shop/goods/${ id }`)
}

/**
 * 商品编辑初始化数据（含 goods_info.sku_list[0] 价格/库存/编码，编辑回填用）
 */
export function getGoodsInit(params: Record<string, any>) {
    return request.get('phone_shop/goods/init', params)
}

/**
 * 新增商品
 */
export function addGoods(params: Record<string, any>) {
    return request.post('phone_shop/goods', params, { showSuccessMessage: true })
}

/**
 * 编辑商品
 */
export function editGoods(id: number | string, params: Record<string, any>) {
    return request.put(`phone_shop/goods/${ id }`, params, { showSuccessMessage: true })
}

/**
 * 删除商品（逻辑删除）
 */
export function deleteGoods(goods_ids: string) {
    return request.put('phone_shop/goods/delete', { goods_ids, is_all: 0 }, { showSuccessMessage: true })
}

/**
 * 上下架（单个/批量，逗号分隔）
 * status: 1 上架 / 0 下架
 */
export function changeGoodsStatus(goods_ids: string, status: number | string) {
    return request.put('phone_shop/goods/single/status', { goods_ids, status }, { showSuccessMessage: true })
}

/**
 * 商品分类（扁平列表）
 */
export function getGoodsCategory(params: Record<string, any> = {}) {
    return request.get('phone_shop/goods/category', params)
}

/**
 * 商品分类（树结构，child_list 为子级）
 */
export function getCategoryTree() {
    return request.get('phone_shop/goods/category/tree')
}

/** 新增分类（pid=0 为一级） */
export function addCategory(params: Record<string, any>) {
    return request.post('phone_shop/goods/category', params, { showSuccessMessage: true })
}

/** 编辑分类 */
export function editCategory(id: number | string, params: Record<string, any>) {
    return request.put(`phone_shop/goods/category/${ id }`, params, { showSuccessMessage: true })
}

/** 删除分类（一级会连带删子级；有商品不可删） */
export function delCategory(id: number | string) {
    return request.delete(`phone_shop/goods/category/${ id }`, {}, { showSuccessMessage: true })
}

/**
 * 内存（规格分组，按分类）
 */
export function getSpecGroup(params: Record<string, any> = {}) {
    return request.get('phone_shop/goods/spec/group', params)
}

/**
 * 按分类取规格选项（内存随分类变） + 成色
 * 入参：category_id, category_path[]
 * 返回：{ spec_groups:[{label, items:[{item_value,...}]}], grades:[{grade_name}] }
 */
export function getSpecOptions(params: Record<string, any> = {}) {
    return request.get('phone_shop/goods/spec/options', params)
}

/**
 * 成色/等级
 */
export function getGradeList(params: Record<string, any> = {}) {
    return request.get('phone_shop/goods/grade', params)
}

/**
 * 配送方式
 */
export function getDeliveryList() {
    return request.get('phone_shop/delivery/deliveryList')
}
