import request from '@/utils/request'

/**
 * 获取商品分类树结构
 * @returns
 */
export function getMallCategoryTree() {
    return request.get(`basic/admin/goods/tree`)
}
