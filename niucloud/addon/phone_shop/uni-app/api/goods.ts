import request from "@/utils/request";

/**
 * 获取商品分类模板配置
 */
export function getGoodsCategoryConfig() {
  return request.get(`phone_shop/goods/category/config`);
}

/**
 * 获取商品分类树结构
 */
export function getGoodsCategoryTree() {
  return request.get(`phone_shop/goods/category/tree`);
}

/**
 * 获取商品分类列表
 */
export function getGoodsCategoryList(params: Record<string, any>) {
  return request.get(`phone_shop/goods/category/list`, params);
}

/**
 * 获取商品列表
 */
export function getGoodsPages(params: Record<string, any>) {
  return request.get(`phone_shop/goods/pages`, params);
}

/**
 * 获取商品详情
 */
export function getGoodsDetail(params: Record<string, any>) {
  return request.get(`phone_shop/goods/detail`, params);
}

/**
 * 获取商品规格
 */
export function getGoodsSku(sku_id: any) {
  return request.get(`phone_shop/goods/sku/${sku_id}`);
}

/**
 *  收藏
 */
export function collect(goods_id: any) {
  return request.post(`phone_shop/goods/collect/${goods_id}`);
}

/**
 *  取消收藏
 */
export function cancelCollect(goods_id: any) {
  return request.delete(`phone_shop/goods/collect/${goods_id}`);
}

/**
 * 获取评价
 */
export function getEvaluateList(goods_id: any) {
  return request.get(`phone_shop/goods/evaluate/list`, { goods_id });
}

/**
 * 获取商品列表供组件调用
 */
export function getGoodsComponents(params: Record<string, any>) {
  return request.get(`phone_shop/goods/components`, params);
}

/**
 * 改变商品上下架状态
 */
export function oparationGoods(goods_id: any) {
  return request.get(`phone_shop/goods/operation/${goods_id}`);
}
/**
 * 获取brand列表
 * brand
 * */
export function getBrandList() {
  return request.get(`phone_shop/goods/brand/list`);
}

// 添加商品
export function addGoods(data: Record<string, any>) {
  return request.post(`phone_shop/goods/add`, data);
}
// getMemoryList

export function getMemoryList(params: Record<string, any>) {
  return request.get(`phone_shop/goods/memory/list`, params);
}

// 更新商品
export function updateGoods(goods_id: any, data: Record<string, any>) {
  return request.put(`phone_shop/goods/${goods_id}`, data);
}