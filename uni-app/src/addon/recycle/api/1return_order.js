import request from "@/utils/request";

/**
 * 获取退回订单列表
 * @param {Object} params - 查询参数
 * @param {Number} params.page - 页码
 * @param {Number} params.limit - 每页数量
 * @param {Number} params.status - 订单状态
 * @returns {Promise}
 */
export function getReturnOrderList(params = {}) {
  return request({
    url: "/api/recycle/recycle_return_order/lists",
    method: "get",
    data: params,
  });
}

/**
 * 获取退回订单详情
 * @param {Number} id - 订单ID
 * @returns {Promise}
 */
export function getReturnOrderDetail(id) {
  return request({
    url: "/api/recycle/recycle_return_order/detail",
    method: "get",
    data: { id },
  });
}

/**
 * 确认收到退回设备
 * @param {Number} id - 订单ID
 * @param {Object} data - 确认数据
 * @param {String} data.comment - 备注信息
 * @returns {Promise}
 */
export function confirmReceiveReturnOrder(id, data = {}) {
  return request({
    url: "/api/recycle/recycle_return_order/confirm",
    method: "post",
    data: {
      id,
      ...data,
    },
  });
}

/**
 * 获取退回订单状态统计
 * @returns {Promise}
 */
export function getReturnOrderStatusCount() {
  return request({
    url: "/api/recycle/recycle_return_order/status_count",
    method: "get",
  });
}

