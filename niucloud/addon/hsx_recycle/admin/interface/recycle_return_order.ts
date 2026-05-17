/**
 * 退回订单列表查询参数
 */
export interface IReturnOrderListParams {
  page?: number;
  limit?: number;
  order_no?: string;
  express_no?: string;
  status?: number | string;
  create_at?: string[];
}

/**
 * 退回订单详情
 */
export interface IReturnOrderDetail {
  id: number;
  site_id: number;
  order_id: number;
  order_no: string;
  status: number;
  express_company: string;
  express_no: string;
  return_address: string;
  comment: string;
  create_at: string;
  update_at: string;
  over_at: string;
  operator_uid: number;
  operator_name: string;
  member_id: number;
  member_name: string;
  member_mobile: string;
  returnDevices: IReturnOrderDevice[];
  order?: {
    id: number;
    order_no: string;
    status: number;
  };
}

/**
 * 退回订单设备
 */
export interface IReturnOrderDevice {
  id: number;
  return_order_id: number;
  device_id: number;
  create_at: string;
  device?: {
    id: number;
    imei: string;
    model: string;
    status: number;
    initial_price: number;
    final_price: number;
  };
}

/**
 * 退回订单创建参数
 */
export interface IReturnOrderCreateParams {
  order_id: number;
  device_ids: number | number[];
  express_company: string;
  express_no: string;
  return_address: string;
  comment?: string;
}

/**
 * 更新退回订单状态参数
 */
export interface IReturnOrderStatusUpdateParams {
  status: number;
  express_company?: string;
  express_no?: string;
  return_address?: string;
  comment?: string;
}

/**
 * 退回订单状态统计
 */
export interface IReturnOrderStatusCount {
  all: number;
  pending: number;
  returning: number;
  completed: number;
  canceled: number;
}

/**
 * 退回设备信息
 */
export interface IReturnDevice {
  id: number;
  return_order_id: number;
  device_id: number;
  status: number;
  status_name: string;
  create_at: string;
  update_at: string;
  device?: {
    id: number;
    site_id: number;
    order_id: number;
    imei: string;
    model: string;
    status: number;
    final_price: string;
    [key: string]: any;
  };
}

/**
 * 退回订单信息
 */
export interface IReturnOrder {
  id: number;
  site_id: number;
  order_id: number;
  order_no: string;
  status: number;
  status_name: string;
  express_company: string;
  express_no: string;
  return_address: string;
  comment: string;
  create_at: string;
  update_at: string;
  over_at: string | null;
  operator_uid: number;
  operator_name: string;
  member_id: number;
  member_name: string;
  member_mobile: string;
  returnDevices: IReturnDevice[];
  member?: {
    member_id: number;
    username: string;
    nickname: string;
    mobile: string;
    headimg: string;
    [key: string]: any;
  };
  order?: {
    [key: string]: any;
  };
}

/**
 * 状态统计信息
 */
export interface IStatusCount {
  status: number | string;
  name: string;
  count: number;
}
