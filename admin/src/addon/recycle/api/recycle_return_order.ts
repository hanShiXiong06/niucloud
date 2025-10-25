import request from "@/utils/request";
import {
  IReturnOrderDetail,
  IReturnOrderListParams,
  IReturnOrderCreateParams,
  IReturnOrderStatusUpdateParams,
} from "../interface/recycle_return_order";

/**
 * 获取退回订单列表
 * @param params 查询参数
 */
export function getReturnOrderList(params: any) {
  return request.get("/recycle/recycle_return_order/lists", {
    params,
  });
}

/**
 * 获取退回订单详情
 * @param id 订单ID
 */
export function getReturnOrderDetail(id: number) {
  return request.get(`/recycle/recycle_return_order/detail/${id}`);
}

/**
 * 创建退回订单
 * @param data 订单数据
 */
export function createReturnOrder(data: any) {
  return request.post("/recycle/recycle_return_order", data);
}

/**
 * 批量创建退回订单
 * @param data 批量订单数据
 */
export function batchCreateReturnOrder(data: any) {
  return request.post("/recycle/recycle_return_order/batch", data);
}

/**
 * 确认退回订单
 * @param id 订单ID
 */
export function confirmReturnOrder(id: number, data: any) {
  return request.put(
    `/recycle/recycle_return_order/${id}/confirm`,
    data
  );
}

/**
 * 取消退回订单
 * @param id 订单ID
 * @param comment 取消原因
 */
export function cancelReturnOrder(id: number, comment: string = "") {
  return request.put(`/recycle/recycle_return_order/${id}/cancel`, {
    comment,
  });
}

/**
 * 更新退回订单状态
 * @param id 订单ID
 * @param data 状态数据
 */
export function updateReturnOrderStatus(id: number, data: any) {
  return request.put(
    `/recycle/recycle_return_order/${id}/status`,
    data
  );
}

/**
 * 删除退回订单
 * @param id 订单ID
 */
export function deleteReturnOrder(id: number) {
  return request.delete(`/recycle/recycle_return_order/${id}`);
}

/**
 * 获取退回订单状态列表
 */
export function getReturnOrderStatusList() {
  return request.get("/recycle/recycle_return_order/status_list");
}

/**
 * 获取退回订单状态统计
 */
export function getReturnOrderStatusCount() {
  return request.get("/recycle/recycle_return_order/status");
}

/**
 * 获取退回订单关联的设备列表
 * @param orderId 订单ID
 */
export function getReturnOrderDevices(orderId: number) {
  return request.get(
    `/recycle/recycle_return_order/${orderId}/devices`
  );
}
/**
 *getDeviceInfo
 *  */
export function getDeviceInfo(id: number) {
  return request.get(
    `/recycle/recycle_return_order/${id}/device_info`
  );
}
