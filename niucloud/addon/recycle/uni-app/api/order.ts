import request from "@/utils/request";

// 获取回收订单列表
export function getOrderList(data: any) {
  return request.get("recycle/recycle_order", data);
}

// 获取回收订单详情
export function getOrderDetail(id: number) {
  return request.get(`recycle/recycle_order/${id}`);
}

// 添加回收订单
export function createOrder(data: any) {
  return request.post("recycle/recycle_order", data);
}

// getOrderStatusCount 获取 订单菜单及统计
export function getOrderStatusCount() {
  return request.get("recycle/recycle_order/status_count");
}

// 获取数量 回收订单
export function getDeviceCount() {
  return request.get("recycle/device/count");
}


export interface RecycleOrderDevice {
  id: number;
  order_id: number;
  imei: string;
  model: string;
  status: number;
  check_status: number;
  check_result: string;
  initial_price: number;
  final_price: number;
  price_remark: string;
  create_at: number;
  update_at: number;
  check_at: number;
}

// 获取设备状态列表
export function getDeviceStatus() {
  return request.get("recycle/device_status/list");
}

// 更新订单状态
export function updateOrderStatus(data: {
  order_id: number | string;
  status: string;
  action:
    | "cancel"
    | "reject"
    | "confirm"
    | "complete"
    | "delete"
    | "update_delivery"
    | "update_express";
  delivery_type?: "mail" | "self";
  express_id?: string;
}) {
  return request.put(
    "recycle/recycle_order/update_status/" + data.order_id,
    data
  );
}
// 确认设备
export function deviceConfirm(id: number) {
  
  return request.put(`recycle/recycle_device/${id}/confirm_price`);
}
// 批量确认设备
export function deviceAllConfirm(deviceIds: number[]) {
  return request.put(`recycle/recycle_device/all_confirm`, { device_ids: deviceIds });
}

// 取消设备
export function deviceCancel(id: number) {
  return request.put(`recycle/recycle_order/device_cancel/${id}`);
}

// 退回单个设备
export function returnDevice(data: {
  device_id: number;
  reason: string;
  images?: string[];
}) {
  return request.put(
    `recycle/recycle_order/device_return/${data.device_id}`,
    data
  );
}

// 依赖于 tk_vip/real/getrealinfo 插件
export function getRealInfo() {
  return request.get(`tk_vip/real/getrealinfo`);
}

// ============ 安果快递相关接口 ============
// 获取可用预约时间
export function getPickupTimes() {
  return request.get('recycle/anguo_delivery/pickup_times');
}

// 创建快递订单
export function createAnguoDelivery(data: {
  order_id: number;
  sender_address: {
    name: string;
    mobile: string;
    province: string;
    city: string;
    district: string;
    address: string;
  };
  pickup_time: string;
  weight?: number;
}) {
  return request.post('recycle/anguo_delivery/create', data);
}

// 取消快递订单
export function cancelAnguoDelivery(order_id: number) {
  return request.post('recycle/anguo_delivery/cancel', { order_id });
}

// 同步快递状态
export function syncAnguoDeliveryStatus(order_id: number) {
  return request.post('recycle/anguo_delivery/sync_status', { order_id });
}


// 查询快递单号

// export function getDelivery(order_id: number) {
//   return request.post('recycle/anguo_delivery/sync_status', { order_id });
// }
export function getExpress(express_code: string = '', mobile: string = '') {
  return request.get('recycle/device_query_api/express',  { express_code, mobile })
}

// 获取收货渠道字典
export function getReceivingChannels() {
  return request.get('recycle/dict/29')
}

// 检查用户是否关注公众号
export function checkWechatFollow() {
  return request.get('recycle/wechat_follow/check')
}
