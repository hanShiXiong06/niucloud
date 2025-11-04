import request from "@/utils/request";

/**
 * 获取报价数据列表（移动端）
 */
export function getQuotationPriceList(params: any) {
  return request.get("recycle/quotation_price/lists", params);
}

/**
 * 获取报价类型列表
 */
export function getQuotationPriceTypes() {
  return request.get("recycle/quotation_price/types");
}

/**
 * 报价数据接口类型定义
 */
export interface QuotationPriceData {
  id: number;
  quotation_id: number;
  price_name: string;
  goods_id: number;
  goods_name: string;
  capacity: string;
  prices: Record<string, PriceDetail>;
  add_value_info: number;
  value_info?: string;
  price_date: string;
  create_at: number;
  update_at: number;
}

export interface PriceDetail {
  original: number;
  final: number;
}

export interface QuotationPriceType {
  quotation_id: number;
  price_name: string;
}

