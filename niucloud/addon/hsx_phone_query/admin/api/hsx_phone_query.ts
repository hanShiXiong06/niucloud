import request from "@/utils/request";

/**
 * 获取查询记录列表
 */
export function getHsxPhoneQueryList(params: {
  page: number;
  limit: number;
  keyword?: string;
  start_time?: string;
  end_time?: string;
  service_code?: string;
  provider_key?: string;
  channel_key?: string;
  status?: string | number;
}) {
  return request.get("hsx_phone_query/hsx_phone_query_list", { params });
}

/**
 * 获取查询运营看板
 */
export function getHsxPhoneQueryDashboard(params: {
  start_time?: string;
  end_time?: string;
}) {
  return request.get("hsx_phone_query/hsx_phone_query_dashboard", { params });
}

/**
 * 获取查询记录详情
 */
export function getHsxPhoneQueryInfo(id: number) {
  return request.get(`hsx_phone_query/hsx_phone_query_list/${id}`);
}

// 定义接口返回数据类型
export interface QueryInfo {
  id: number;
  sn: string;
  type_id: number;
  type_name: string;
  pid: number;
  info: {
    机型?: string;
    容量?: string;
    颜色?: string;
    [key: string]: any;
  };
  create_time: string;
  is_look: number;
  member_id: number;
}

export interface QueryListResponse {
  code: number;
  msg: string;
  data: {
    count: number;
    list: QueryInfo[];
  };
}

export interface QueryDetailResponse {
  code: number;
  msg: string;
  data: QueryInfo;
}
