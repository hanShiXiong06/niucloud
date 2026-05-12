import request from "@/utils/request";

// ============ 统一快递服务接口 ============

/**
 * 获取快递报价
 * POST /api/recycle/express/quote
 */
export function getExpressQuote(data: {
  sender_name: string;
  sender_mobile: string;
  sender_province: string;
  sender_city: string;
  sender_district?: string;
  sender_address: string;
  weight?: number;
  package_count?: number;
}) {
  return request.post("recycle/express/quote", data);
}

/**
 * 查询快递物流轨迹
 * GET /api/recycle/express/track/:order_id
 */
export function getExpressTrack(orderId: number) {
  return request.get(`recycle/express/track/${orderId}`);
}

/**
 * 获取可用的快递服务商列表
 * GET /api/recycle/express/providers
 */
export function getExpressProviders() {
  return request.get("recycle/express/providers");
}

/**
 * 检查平台快递是否启用
 * GET /api/recycle/express/check
 */
export function checkExpressEnabled() {
  return request.get("recycle/express/check");
}

/**
 * 取消快递单
 * POST /api/recycle/express/cancel
 */
export function cancelExpress(orderId: number) {
  return request.post("recycle/express/cancel", { order_id: orderId });
}

// ============ 快递配置类型 ============

/** 快递报价项 */
export interface ExpressQuoteItem {
  product_code: string;
  product_name: string;
  price: number;
  estimated_time: string;
  provider: string;
  logo?: string;
  remark?: string;
}

/** 快递报价结果 */
export interface ExpressQuoteResult {
  provider: string;
  provider_name: string;
  list: ExpressQuoteItem[];
}

/** 快递服务商信息 */
export interface ExpressProvider {
  provider: string;
  provider_name: string;
  display_name?: string;
  product_code?: string;
  product_name?: string;
  front_name?: string;
  is_default: number;
  support_quote: boolean;
  support_cancel: boolean;
  support_track: boolean;
}

/** 平台快递启用状态 */
export interface ExpressCheckResult {
  enabled: boolean;
  provider: string;
  provider_name?: string;
  display_name?: string;
  product_code?: string;
  product_name?: string;
  front_name?: string;
  has_shop_address: boolean;
  prompt?: string;
  memo?: string;
}

/** 快递配置（下单时传入） */
export interface ExpressConfig {
  sender_name: string;
  sender_mobile: string;
  sender_province: string;
  sender_city: string;
  sender_district: string;
  sender_address: string;
  product_code?: string;
  weight?: number;
  package_count?: number;
  pickup_time?: string;
  estimated_cost?: number;
}
