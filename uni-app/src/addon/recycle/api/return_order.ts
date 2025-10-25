import request from "@/utils/request";

/**
 * 获取退回订单列表
 * @param params 查询参数
 */
export function getReturnOrderList(params: any) {
  return request.get("recycle/recycle_return_order/lists", { params });
}

/**
 * 获取退回订单详情
 * @param id 订单ID
 */
export function getReturnOrderDetail(id: number) {
  return request.get(`recycle/recycle_return_order/${id}`);
}

/**
 * 确认收到退回设备
 * @param id 订单ID
 * @param data 确认数据
 */
export function confirmReceiveReturnOrder(id: number, data: any) {
  return request.put(
    `recycle/recycle_return_order/${id}/confirm`,
    data
  );
}

/**
 * 获取退回订单状态统计
 */
export function getReturnOrderStatusCount() {
  return request.get("recycle/recycle_return_order/status_count");
}

/**
 * 获取退回订单状态列表
 * 用于状态筛选
 */
export function getReturnOrderStatusList() {
  return [
    { text: "全部", value: -1 },
    { text: "待处理", value: 0 },
    { text: "退货中", value: 1 },
    { text: "已完成", value: 2 },
    { text: "已取消", value: 3 },
  ];
}


/**
 * 获取用户退货地址信息
 */
export function getRecycleUserAddressInfo() {
  return request.get('recycle/recycle_user_address');
}

/**
 * 新增用户退货地址
 * @param data 地址信息
 */
export function addRecycleUserAddress(data: {
  name: string;
  mobile: string;
  id_card: string;
  address: string;
  card_pic: string;
}) {
  return request.post('recycle/recycle_user_address', data);
}

/**
 * 编辑用户退货地址
 * @param id 地址ID
 * @param data 地址信息
 */
export function editRecycleUserAddress(id: number, data: {
  name: string;
  mobile: string;
  id_card: string;
  address: string;
  card_pic: string;
}) {
  return request.put(`recycle/recycle_user_address/${id}`, data);
}

/**
 * 删除用户退货地址
 * @param id 地址ID
 */
export function deleteRecycleUserAddress(id: number) {
  return request.delete(`recycle/recycle_user_address/${id}`);
} 