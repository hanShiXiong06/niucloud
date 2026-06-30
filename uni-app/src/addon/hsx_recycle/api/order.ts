import request from "@/utils/request";

// 获取回收订单列表
export function getOrderList(data: any) {
  return request.get("recycle/recycle_order", data);
}

// 获取回收订单详情
export function getOrderDetail(id: number) {
  return request.get(`recycle/recycle_order/${id}`);
}

// 催办订单，推送到企业微信群
export function urgeOrder(id: number) {
  return request.post(`recycle/recycle_order/${id}/urge`);
}

// 添加回收订单
export function createOrder(data: any) {
  return request.post("recycle/recycle_order", data);
}

export function getOrderSubmitConfig() {
  return request.get("recycle/order_submit_config");
}

// 搜索设备型号字典叶子节点
export function searchDeviceModelDictOptions(params: { keyword: string; limit?: number }) {
  return request.get("recycle/device_model_dict/options", params);
}

export function getDeviceModelDictTree() {
  return request.get("recycle/device_model_dict/tree");
}

export function getDeviceModelDictChildren(params: { pid?: number; keyword?: string; limit?: number }) {
  return request.get("recycle/device_model_dict/children", params);
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
  check_result_seller?: string;
  check_images?: string;
  check_images_seller?: string;
  check_images_seller_thumb_small?: string[];
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

// 确认设备处理方式：出售或拒绝出售
export function deviceConfirmHandle(id: number, data: { is_sell: boolean; remark?: string }) {
  return request.put(`recycle/recycle_device/${id}/confirm`, data);
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

// 查询快递单号
// 注意：阿里快递查询接口的 mobile 需为收/寄件人手机号「后4位」（顺丰等必填），传完整手机号会查不到数据
export function getExpress(express_code: string = '', mobile: string = '') {
  const digits = String(mobile || '').replace(/\D/g, '')
  const mobile_tail = digits.length > 4 ? digits.slice(-4) : digits
  return request.get('recycle/device_query_api/express',  { express_code, mobile: mobile_tail })
}

// 检查用户是否关注公众号
export function checkWechatFollow() {
  return request.get('recycle/wechat_follow/check')
}
