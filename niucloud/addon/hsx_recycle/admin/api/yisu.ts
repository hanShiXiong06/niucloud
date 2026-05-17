import request from '@/utils/request'

/**
 * 获取易速产品列表
 */
export function getYisuProductList() {
    return request.get('recycle/yisu_product/lists')
}

/**
 * 批量更新易速产品
 */
export function batchUpdateYisuProduct(params: any) {
    return request.post('recycle/yisu_product/batch_update', params)
}

/**
 * 修改易速产品状态
 */
export function modifyYisuProductStatus(params: any) {
    return request.post('recycle/yisu_product/modify_status', params)
}

/**
 * 获取启用的易速产品
 */
export function getEnabledYisuProducts() {
    return request.get('recycle/yisu_product/enabled')
}
