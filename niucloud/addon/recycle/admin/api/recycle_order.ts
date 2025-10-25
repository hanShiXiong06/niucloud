import request from "@/utils/request";

// 获取回收订单列表
export function getRecycleOrderList(params: any) {
  return request.get("/recycle/recycle_order/lists", { params });
}

// 更新回收订单状态
export function updateRecycleOrder(id: number, data: any) {
  return request.put(`/recycle/recycle_order/${id}`, data);
}

// 删除回收订单
export function deleteRecycleOrder(id: number) {
  return request.delete(`/recycle/recycle_order/${id}`);
}

/**
 * 更新设备状态
 * @param id 设备ID
 * @param data 更新数据
 */
export function updateDeviceStatus(id: string, data: any) {
  return request.put(`/recycle/recycle_order/device/${id}`, data);
}

// 添加回收设备
export function addRecycleDevice(data: any) {
  return request.post("/recycle/recycle_order/device", data);
}

/**
 * 删除回收设备
 * @param id 设备ID
 * @returns
 */
export function deleteRecycleDevice(id: string | number) {
  return request.delete(`/recycle/recycle_device/${id}`);
}

/**
 * 获取订单状态列表
 */
export function getStatus() {
  return request.get("/recycle/recycle_order/status");
}

// 获取订单关联的设备列表
export function getOrderDevices(orderId: number) {
  return request.get(`/recycle/recycle_order/${orderId}/devices`);
}

// 添加设备
export function addOrderDevice(orderId: number, data: any) {
  return request.post(`/recycle/recycle_order/${orderId}/devices`, {
    data,
  });
}

// 更新设备
export function updateOrderDevice(deviceId: number, data: any) {
  return request.put(`/recycle/recycle_device/${deviceId}`, {
    data,
  });
}

// 删除设备
export function deleteOrderDevice(deviceId: number) {
  return request.delete(`/recycle/recycle_device/${deviceId}`);
}

// getDevice
// 获取单个设备信息
export function getDevice(deviceId: number) {
  
  return request.get(`/recycle/recycle_device/${deviceId}`);
}

// updateDevice
// 更新单个设备信息
export function updateDevice(deviceId: number, data: any) {
  return request.put(`/recycle/recycle_device/${deviceId}`, {
    data,
  });
}

// confirmPrice
// 定价
export function confirmPrice(orderId: number, data: any) {
  return request.put(
    `/recycle/recycle_device/${orderId}/confirm_price`,data);
}
// 获取商户的收款信息
export function getMerchantPayInfo(memberId: number) {
  return request.get(
    `/recycle/recycle_order/merchant_pay_info/${memberId}`
  );
}

// 确认打款
export function paymentConfirm(orderId: number, data: any) {
  return request.put(
    `/recycle/recycle_order/${orderId}/payment_confirm`,
    data
  );
}

// 批量更新设备状态
export function batchUpdateDeviceStatus(data: any) {
  return request.post(
    "/recycle/recycle_device/batch_update_status",
    data
  );
}

// 批量确认设备（回收）
export function batchRecycleDevices(data: any) {
  return request.post("/recycle/recycle_device/batch_recycle", data);
}

// 批量拒绝设备（退回）
export function batchReturnDevices(data: any) {
  return request.post("/recycle/recycle_device/batch_return", data);
}

// 获取回收订单详情
export function getRecycleOrderInfo(id: number) {
  return request.get(`/recycle/recycle_order/${id}`);
}

// getRecycleOrderStatusList 是 getStatus 的别名
export function getRecycleOrderStatusList() {
  return getStatus();
}

/**
 * getUserByMobile
 * 通过手机号 查找用户的 id
 */
export function getUserByMobile(keyword: string): Promise<any> {
  return request.get("/member/member", { params: { keyword } });
}

/**
 * createRecycleOrder
 * 创建回收订单
 */
export function createRecycleOrder(data: any) {
  return request.post("/recycle/recycle_order/create", data);
}

// 获取IMEI信息
export function getImeiInfo(imei: string) {
  return request.get(`/recycle/recycle_device/imei_info/${imei}`);
}

// 推送订单确认通知
export function pushOrderNotify(orderId: number) {
  return request.post(`/recycle/recycle_order/${orderId}/push_notify`);
}
