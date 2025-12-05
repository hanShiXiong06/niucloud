import request from "@/utils/request";
import { IApiResponse } from "@/utils/request/types";

// USER_CODE_BEGIN -- phone_shop_site
// 获取所有的站点列表
export function getSiteListAll() {
  return request.get(`site/site`);
}

/**
 * 获取站点(二手)管理列表
 * @param params
 * @returns
 */
export function getSiteList(params: Record<string, any>) {
  return request.get(`phone_shop/site`, { params });
}

/**
 * 获取站点(二手)管理详情
 * @param id 站点(二手)管理id
 * @returns
 */
export function getSiteInfo(id: number) {
  return request.get(`phone_shop/site/${id}`);
}

/**
 * 添加站点(二手)管理
 * @param params
 * @returns
 */
export function addSite(params: Record<string, any>) {
  return request.post("phone_shop/site", params, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

/**
 * 编辑站点(二手)管理
 * @param id
 * @param params
 * @returns
 */
export function editSite(params: Record<string, any>) {
  return request.put(`phone_shop/site/${params.id}`, params, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

/**
 * 删除站点(二手)管理
 * @param id
 * @returns
 */
export function deleteSite(id: number) {
  return request.delete(`phone_shop/site/${id}`, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

// USER_CODE_END -- phone_shop_site

// 回收商相关的api
export function addRecycler(params: Record<string, any>) {
  return request.post("phone_shop/recycler", params, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

// 查询回收商信息
export function getRecyclerInfo(id: number) {
  return request.get(`phone_shop/recycler`);
}

// 更新回收商信息
export function updateRecycler(params: Record<string, any>) {
  return request.put(`phone_shop/recycler/${params.id}`, params, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

// 删除回收商
export function deleteRecycler(id: number) {
  return request.delete(`phone_shop/recycler/${id}`, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

// getRole 获取是否具备 回收商的资格
export function getRole() {
  return request.get("phone_shop/recycler/role");
}

// 添加回收商价格配置
export function addRecyclerPriceConfig(params: Record<string, any>) {
  return request.post("phone_shop/recycler/price/config", params, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

// 查询回收商价格配置
export function getRecyclerPriceConfig(recycler_id: number) {
  return request.get(`phone_shop/recycler/price/config/${recycler_id}`);
}

// 更新回收商价格配置
export function updateRecyclerPriceConfig(params: Record<string, any>) {
  return request.put(`phone_shop/recycler/price/config/${params.id}`, params, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}
// 根据site_id 获取回收商价格配置
export function getRecyclerPriceConfigBySiteId(site_id: number) {
  return request.get(`phone_shop/recycler/price/config/site`);
}

// 删除回收商价格配置
export function deleteRecyclerPriceConfig(id: number) {
  return request.delete(`phone_shop/recycler/price/config/${id}`, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

/**
 * Route::group('site_agent', function () {
    Route::get('lists', 'addon\phone_shop\app\adminapi\controller\site\SiteAgent@lists');
    Route::get('available', 'addon\phone_shop\app\adminapi\controller\site\SiteAgent@getAvailableSites');
    Route::post('add', 'addon\phone_shop\app\adminapi\controller\site\SiteAgent@add');
    Route::put('modify_status', 'addon\phone_shop\app\adminapi\controller\site\SiteAgent@modifyStatus');
    Route::delete('del/:id', 'addon\phone_shop\app\adminapi\controller\site\SiteAgent@del');
});*/

export function getSiteAgent() {}

// 获取代理关系列表
export function getSiteAgentList(params?: {
  agent_site_id?: string | number;
  status?: 0 | 1;
  page?: number;
  limit?: number;
}) {
  return request.get<IApiResponse>("/phone_shop/site_agent/lists", {
    params,
    showErrorMessage: true,
  });
}

// 获取可代理的站点列表
export function getAvailableSites() {
  return request.get<IApiResponse>("/phone_shop/site_agent/available", {
    showErrorMessage: true,
  });
}

// 添加代理关系
export function addSiteAgent(data: { agent_site_id: number }) {
  return request.post<IApiResponse>("/phone_shop/site_agent/add", data, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

// 修改代理状态
export function modifySiteAgentStatus(data: { id: number; status: 0 | 1 }) {
  return request.put<IApiResponse>(
    "/phone_shop/site_agent/modify_status",
    data,
    {
      showErrorMessage: true,
      showSuccessMessage: true,
    }
  );
}

// 删除代理关系
export function deleteSiteAgent(id: number) {
  return request.delete<IApiResponse>(`/phone_shop/site_agent/del/${id}`, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

// API 返回数据类型定义
export interface ISiteAgent {
  id: number;
  site_id: number;
  agent_site_id: number;
  site_name?: string;
  status: 0 | 1;
  create_time: string;
  update_time: string;
}

export interface ISite {
  site_id: number;
  site_name: string;
  [key: string]: any;
}
