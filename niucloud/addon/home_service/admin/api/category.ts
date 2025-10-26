import request from '@/utils/request'

/*****************************************************  服务分类 ****************************************************/

/**
 * 获取 服务分类列表
 * @param params
 * @returns
 */
export function getCategoryList(params: Record<string, any>) {
    return request.get(`home_service/category`, { params  })
}

/**
 * 获取分级分类列表
 * @param params
 * @returns
 */
export function getCategory(params: Record<string, any>) {
    return request.get(`home_service/category/list`, { params })
}

/**
 * 获取分级分类列表
 * @returns
 */
export function getCategoryTree() {
    return request.get(`home_service/category/tree`)
}

/**
 * 获取服务分类详情
 * @param category_id  服务分类category_id
 * @returns
 */
export function getCategoryInfo(category_id: number) {
    return request.get(`home_service/category/${ category_id }`);
}

/**
 * 添加 服务分类
 * @param params
 * @returns
 */
export function addCategory(params: Record<string, any>) {
    return request.post('home_service/category', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑 服务分类
 * @param params
 * @returns
 */
export function editCategory(params: Record<string, any>) {
    return request.put(`home_service/category/${ params.category_id }`, params, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 删除 服务分类
 * @param category_id
 * @returns
 */
export function deleteCategory(category_id: number) {
    return request.delete(`home_service/category/${ category_id }`, { showErrorMessage: true, showSuccessMessage: true })
}



/**
 * 删除 服务分类
 * @param category_id
 * @returns
 */
export function editSortCategory(params: Record<string, any>) {
    return request.put(`home_service/category/sort`,params, { showErrorMessage: true, showSuccessMessage: true })
}

