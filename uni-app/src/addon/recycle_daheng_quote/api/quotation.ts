import request from "@/utils/request";

/**
 * 获取报价 2.0 报价单列表（移动端低代码入口）
 */
export function getQuotationV2Types(params: any = {}) {
  return request.get("recycle_daheng_quote/quotation_v2/types", params);
}

/**
 * 获取报价 2.0 明细（移动端）
 */
export function getQuotationV2PriceList(params: any) {
  return request.get("recycle_daheng_quote/quotation_v2/lists", params);
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
  adjustment_items?: QuotationAdjustmentItem[];
  adjustment_summary?: string;
  price_date: string;
  create_at: number | string;
  update_at: number | string;
}

export interface QuotationAdjustmentItem {
  id?: number;
  field_id?: number;
  field_name?: string;
  field_type?: string;
  field_sort?: number;
  content_text?: string;
  content_html?: string;
}

export interface PriceDetail {
  original: number;
  final: number;
  price?: number;
  adjust_type?: number;
  adjust_value?: number;
}

export interface QuotationV2Type {
  id: number;
  dataset_id: number;
  quotation_id: number;
  price_name: string;
  dataset_name: string;
  title: string;
  nav_image?: string;
  last_sync_at: number;
  last_sync_at_text: string;
  last_sync_status_name: string;
  price_count: number;
  model_count: number;
}
