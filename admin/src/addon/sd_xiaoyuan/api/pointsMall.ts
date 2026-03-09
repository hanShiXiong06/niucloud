import request from '@/utils/request'

// 积分商品管理
export function getPointsGoodsList(params: any) {
    return request.get('sd_xiaoyuan/points_mall/goods_list', params)
}

export function addPointsGoods(data: any) {
    return request.post('sd_xiaoyuan/points_mall/add_goods', data)
}

export function editPointsGoods(data: any) {
    return request.post('sd_xiaoyuan/points_mall/edit_goods', data)
}

export function delPointsGoods(id: number) {
    return request.post('sd_xiaoyuan/points_mall/del_goods', { id })
}

// 积分订单管理
export function getPointsOrderList(params: any) {
    return request.get('sd_xiaoyuan/points_mall/order_list', params)
}

export function setPointsOrderStatus(id: number, status: number) {
    return request.post('sd_xiaoyuan/points_mall/set_order_status', { id, status })
}

// 发货
export function shipPointsOrder(id: number, express_company: string, express_no: string) {
    return request.post('sd_xiaoyuan/points_mall/ship_order', { id, express_company, express_no })
}

// 物流查询
export function getPointsLogistics(id: number) {
    return request.get(`sd_xiaoyuan/points_mall/logistics?id=${id}`)
}
