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

// 获取订单状态列表
export function getOrderStatus() {
  return request.get("recycle/recycle_order/status_count");
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

