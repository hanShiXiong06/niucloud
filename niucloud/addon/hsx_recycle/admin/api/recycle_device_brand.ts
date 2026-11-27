import request from '@/utils/request'

// USER_CODE_BEGIN -- recycle_device_brand
/**
 * 获取回收设备品牌列表
 * @param params
 * @returns
 */
export function getRecycleDeviceBrandList(params: Record<string, any>) {
    return request.get(`recycle/recycle_device_brand`, {params})
}

/**
 * 获取回收设备品牌详情
 * @param id 回收设备品牌id
 * @returns
 */
export function getRecycleDeviceBrandInfo(id: number) {
    return request.get(`recycle/recycle_device_brand/${id}`);
}

/**
 * 添加回收设备品牌
 * @param params
 * @returns
 */
export function addRecycleDeviceBrand(params: Record<string, any>) {
    return request.post('recycle/recycle_device_brand', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑回收设备品牌
 * @param id
 * @param params
 * @returns
 */
export function editRecycleDeviceBrand(params: Record<string, any>) {
    return request.put(`recycle/recycle_device_brand/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除回收设备品牌
 * @param id
 * @returns
 */
export function deleteRecycleDeviceBrand(id: number) {
    return request.delete(`recycle/recycle_device_brand/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}



// USER_CODE_END -- recycle_device_brand
