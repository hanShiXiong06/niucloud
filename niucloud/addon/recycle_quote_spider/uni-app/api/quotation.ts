import request from "@/utils/request";

/**
 * 获取报价爬虫插件的前台报价项列表
 */
export function getQuoteSpiderFeatured(params: any = {}) {
  return request.get("recycle_quote_spider/featured", params);
}

/**
 * 获取报价爬虫插件的前台分类树
 */
export function getQuoteSpiderCategoryTree(params: any = {}) {
  return request.get("recycle_quote_spider/category/tree", params);
}

/**
 * 获取报价爬虫插件的报价项详情
 */
export function getQuoteSpiderDetail(id: number | string) {
  return request.get(`recycle_quote_spider/item/${id}`);
}

/**
 * 获取某个型号(行)的历史价格序列
 */
export function getQuoteSpiderPriceHistory(id: number | string, days = 30) {
  return request.get(`recycle_quote_spider/row/${id}/price-history`, { days });
}

/**
 * 获取报价项列表（按分类，含子分类）
 */
export function getQuoteSpiderItems(params: any = {}) {
  return request.get("recycle_quote_spider/item", params);
}

/**
 * 生成报价单权限（会员权益 quote_report）
 */
export function getQuoteSpiderReportPermission() {
  return request.get("recycle_quote_spider/report/permission");
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

export interface QuoteSpiderItem {
  id: number;
  source_id: number;
  category_id: number;
  brand: string;
  tab: string;
  name: string;
  parent_name: string;
  category_path?: string;
  title: string;
  quote_type: string;
  is_image_quote: number;
  image: string;
  timage: string;
  bimage: string;
  icon: string;
  notice_text?: string;
  is_hot: number;
  model_count: number;
  last_sync_at_text: string;
  last_sync_at?: number | string;
  update_at?: number | string;
  update_at_text?: string;
  price_date?: string;
  rows?: QuoteSpiderRow[];
}

export interface QuoteSpiderCategory {
  id: number;
  source_id: number;
  parent_id: number;
  name: string;
  level: number;
  sort: number;
  is_show: number;
  children?: QuoteSpiderCategory[];
}

export interface QuoteSpiderRow {
  id: number;
  item_id: number;
  brand: string;
  tab: string;
  model_name: string;
  columns?: string[];
  source_prices?: Record<string, any>;
  manual_prices?: Record<string, any>;
  final_prices?: Record<string, any>;
  remark?: string;
  price_date?: string;
  create_at?: number | string;
  update_at?: number | string;
  update_at_text?: string;
}
