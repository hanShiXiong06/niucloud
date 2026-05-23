import request from "@/utils/request";

// USER_CODE_BEGIN -- hsx_phone_query_category
/**
 * 获取分类列表
 * @param params
 * @returns
 */
export function getHsxPhoneQueryCategoryList(params: Record<string, any>) {
  return request.get(`hsx_phone_query/hsx_phone_query_category`, { params });
}

/**
 * 获取分类详情
 * @param site_id 分类site_id
 * @returns
 */
export function getHsxPhoneQueryCategoryInfo(id: number) {
  return request.get(`hsx_phone_query/hsx_phone_query_category/${id}`);
}

/**
 * 添加分类
 * @param params
 * @returns
 */
export function addHsxPhoneQueryCategory(params: Record<string, any>) {
  return request.post("hsx_phone_query/hsx_phone_query_category", params, {
    showErrorMessage: false,
    showSuccessMessage: false,
  });
}

/**
 * 编辑分类
 * @param site_id
 * @param params
 * @returns
 */
export function editHsxPhoneQueryCategory(params: Record<string, any>) {
  return request.put(
    `hsx_phone_query/hsx_phone_query_category/${params.id}`,
    params,
    { showErrorMessage: false, showSuccessMessage: false }
  );
}

/**
 * 删除分类
 * @param site_id
 * @returns
 */
export function deleteHsxPhoneQueryCategory(id: number) {
  return request.delete(`hsx_phone_query/hsx_phone_query_category/${id}`, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

/**
 * 修改排序
 * @param id
 * @param sort
 */
export function modifySort(id: number, sort: number) {
  return request.post(
    `hsx_phone_query/hsx_phone_query_category/modify_sort/${id}`,
    { sort },
    { showErrorMessage: false, showSuccessMessage: false }
  );
}

/**
 * 修改显示状态
 * @param id
 * @param is_show
 */
export function modifyShow(id: number, is_show: number) {
  return request.post(
    `hsx_phone_query/hsx_phone_query_category/modify_show/${id}`,
    { is_show },
    { showErrorMessage: false, showSuccessMessage: false }
  );
}

/**
 * 同步初始化查询项目
 * @param overwrite_sale_config 是否覆盖售价/成本/上下架/排序
 */
export function syncHsxPhoneQueryItems(overwrite_sale_config = 0) {
  return request.post(
    `hsx_phone_query/hsx_phone_query_category/sync_items`,
    { overwrite_sale_config },
    { showErrorMessage: true, showSuccessMessage: true }
  );
}

/**
 * 修复查询项目渠道基础信息
 */
export function repairHsxPhoneQueryItems() {
  return request.post(
    `hsx_phone_query/hsx_phone_query_category/repair_items`,
    {},
    { showErrorMessage: true, showSuccessMessage: true }
  );
}

// USER_CODE_END -- hsx_phone_query_category
