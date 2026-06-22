import request from '@/utils/request'

// 收银台商品列表(富检索 + 会员价 + 质检详情)
export function cashierGoods(params: Record<string, any>) {
    return request.get('phone_shop/cashier/goods', { params })
}

// 收银台分类树(三级,空分类不显示)
export function cashierCategoryTree() {
    return request.get('phone_shop/cashier/category_tree')
}

// 联动筛选项(可选内存 / 成色,随分类及彼此选择联动)
export function cashierFilters(params: Record<string, any>) {
    return request.get('phone_shop/cashier/filters', { params })
}

// 代下单收银台:结算下单(一次多台,归并成一笔订单)
export function cashierCheckout(data: Record<string, any>) {
    return request.post('phone_shop/cashier/checkout', data, { showSuccessMessage: true })
}
