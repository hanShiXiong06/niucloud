import request from '@/utils/request'


/***************************************************** 项目 ****************************************************/

/**
 * 获取项目列表(分页)
 * @param params
 * @returns
 */
export function getGoodsList(params: Record<string, any>) {
    return request.get(`home_service/goods`, { params })
}

/**
 * 获取项目选择分页列表
 * @param params
 * @returns
 */
export function getGoodsSelectPageList(params: Record<string, any>) {
    return request.get(`home_service/goods/select`, { params })
}


/**
 * 获取次卡选择分页列表
 * @param params
 * @returns
 */
export function getCardSelectPageList(params: Record<string, any>) {
    return request.get(`home_service/card/select`, { params })
}

/**
 * 获取项目列表（不分页）
 * @returns
 */
export function getGoodsListTo() {
    return request.get(`home_service/goods/list`)
}

/**
 * 添加项目
 * @param params
 * @returns
 */
export function addGoods(params: Record<string, any>) {
    return request.post('home_service/goods', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑项目
 * @param params
 * @returns
 */
export function editGoods(params: Record<string, any>) {
    return request.put(`home_service/goods/${ params.goods_id }`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 项目详情
 * @param params
 * @returns
 */
export function getGoodsDetail(params: Record<string, any>) {
    return request.get(`home_service/goods/init`, { params })
}

/**
 * 修改项目状态(上架,下架)
 * @param params
 * @returns
 */
export function editGoodsStatus(params: Record<string, any>) {
    return request.put(`home_service/goods/status`, params, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 修改项目排序
 * @param params
 * @returns
 */
export function editGoodsSort(params: Record<string, any>) {
    return request.put(`home_service/goods/sort/${ params.goods_id }`, params, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 删除项目
 * @param id
 * @returns
 */
export function deleteGoods(params: Record<string, any>) {
    return request.delete(`home_service/goods/delete`, { params, showErrorMessage: true, showSuccessMessage: true })
}
/**
 * 复制服务
 * @param params
 */
export function copyGoods(params: Record<string, any>) {
    return request.put(`home_service/goods/copy/${ params.goods_id }`, params, { showSuccessMessage: true })
}

/**
 * 获取服务SKU规格列表
 * @param params
 * @returns
 */
export function getGoodsSkuList(params: Record<string, any>) {
    return request.get(`home_service/goods/sku`, { params })
}

/**
 * 编辑服务SKU规格会员价格
 * @param params
 * @returns
 */
export function editGoodsListMemberPrice(params: Record<string, any>) {
    return request.put(`home_service/goods/member_price`, params, { showSuccessMessage: true })
}

/**
 * 获取分级分类列表
 * @returns
 */
export function getCategoryTree() {
    return request.get(`home_service/category/tree`)
}

/**
 * 获取服务SKU规格不分页列表
 * @param params
 * @returns
 */
export function getGoodsSkuNoPageList(params: Record<string, any>) {
    return request.get(`home_service/goods/selectgoodssku`, { params })
}

/**
 * 获取次卡不分页列表
 * @param params
 * @returns
 */
export function getCardSkuNoPageList(params: Record<string, any>) {
    return request.get(`home_service/card/selectcardsku`, { params })
}


/**
 * 获时效性
 * @param params
 * @returns
 */
export function getvalidType(params: Record<string, any>) {
    return request.get(`home_service/card/valid_type`, { params })
}
/**
 * 获取服务分类列表
 * @param params
 * @returns
 */
export function getCategoryList(params: Record<string, any>) {
    return request.get(`home_service/category`, { params })
}

/**
 * 获取服务类型
 * @returns
 */
export function getGoodsType() {
    return request.get(`home_service/goods/coupon/type`);
}


/**
 * 获取服务保障
 * @returns
 */
export function getGoodsGuarantee() {
    return request.get(`home_service/goods/guarantee`);
}
