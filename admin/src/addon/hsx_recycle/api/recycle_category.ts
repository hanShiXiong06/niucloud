import request from "@/utils/request";

// USER_CODE_BEGIN -- recycle_recycle_category
export function getCategoryList(params: Record<string, any>) {
  return request.get(`recycle/recycle_category`, { params });
}
/**
 * 获取二手机分类列表
 * @param params
 * @returns
 */
export function getCategoryTree(params: Record<string, any>) {
  return request.get(`recycle/recycle_category_tree`, { params });
}

/**
 * 获取二手机分类详情
 * @param category_id 二手机分类category_id
 * @returns
 */
export function getRecycleCategoryInfo(category_id: number) {
  return request.get(`recycle/recycle_category/${category_id}`);
}

/**
 * 添加二手机分类
 * @param params
 * @returns
 */
export function addRecycleCategory(params: Record<string, any>) {
  return request.post("recycle/recycle_category", params, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

/**
 * 编辑二手机分类
 * @param category_id
 * @param params
 * @returns
 */
export function editRecycleCategory(params: Record<string, any>) {
  return request.put(
    `recycle/recycle_category/${params.category_id}`,
    params,
    { showErrorMessage: true, showSuccessMessage: true }
  );
}
export function updateRecycleCategory(params: Record<string, any>) {
  return request.post(
    `recycle/recycle_category/category/update`,
    params,
    {
      showErrorMessage: true,
      showSuccessMessage: true,
    }
  );
}

/**
 * 删除二手机分类
 * @param category_id
 * @returns
 */
export function deleteRecycleCategory(category_id: number) {
  return request.delete(`recycle/recycle_category/${category_id}`, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

// Banner相关接口
export function getBannerList(params?: any) {
  return request.get("/recycle/recycle_banner", { params });
}

export function getBannerInfo(id: number) {
  return request.get(`/recycle/recycle_banner/${id}`);
}

export function addBanner(data: any) {
  return request.post("/recycle/recycle_banner", data);
}

export function editBanner(id: number, data: any) {
  return request.put(`/recycle/recycle_banner/${id}`, data);
}

export function deleteBanner(id: number) {
  return request.delete(`/recycle/recycle_banner/${id}`);
}

export function changeBannerSort(id: number, sort: number) {
  return request.put(
    `/recycle/recycle_banner/change_sort/${id}/${sort}`
  );
}

// 配置相关接口
export function getConfig() {
  return request.get("/recycle/recycle_category_config");
}

export function setConfig() {
  return request.put("/recycle/recycle_category_config");
}

// USER_CODE_END -- recycle_recycle_category
