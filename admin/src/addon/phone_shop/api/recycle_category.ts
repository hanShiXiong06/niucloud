import request from "@/utils/request";

// USER_CODE_BEGIN -- phone_shop_recycle_category
export function getCategoryList(params: Record<string, any>) {
  return request.get(`phone_shop/recycle_category`, { params });
}
/**
 * 获取二手机分类列表
 * @param params
 * @returns
 */
export function getCategoryTree(params: Record<string, any>) {
  return request.get(`phone_shop/recycle_category_tree`, { params });
}

/**
 * 获取二手机分类详情
 * @param category_id 二手机分类category_id
 * @returns
 */
export function getRecycleCategoryInfo(category_id: number) {
  return request.get(`phone_shop/recycle_category/${category_id}`);
}

/**
 * 添加二手机分类
 * @param params
 * @returns
 */
export function addRecycleCategory(params: Record<string, any>) {
  return request.post("phone_shop/recycle_category", params, {
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
    `phone_shop/recycle_category/${params.category_id}`,
    params,
    { showErrorMessage: true, showSuccessMessage: true }
  );
}

/**
 * 删除二手机分类
 * @param category_id
 * @returns
 */
export function deleteRecycleCategory(category_id: number) {
  return request.delete(`phone_shop/recycle_category/${category_id}`, {
    showErrorMessage: true,
    showSuccessMessage: true,
  });
}

// USER_CODE_END -- phone_shop_recycle_category
