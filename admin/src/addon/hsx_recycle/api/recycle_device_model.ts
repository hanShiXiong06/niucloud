import request from '@/utils/request'

// USER_CODE_BEGIN -- recycle_device_model
/**
 * 获取回收设备型号列表
 * @param params
 * @returns
 */
export function getRecycleDeviceModelList(params: Record<string, any>) {
    return request.get(`recycle/recycle_device_model`, {params})
}

/**
 * 获取回收设备型号详情
 * @param id 回收设备型号id
 * @returns
 */
export function getRecycleDeviceModelInfo(id: number) {
    return request.get(`recycle/recycle_device_model/${id}`);
}

/**
 * 添加回收设备型号
 * @param params
 * @returns
 */
export function addRecycleDeviceModel(params: Record<string, any>) {
    return request.post('recycle/recycle_device_model', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑回收设备型号
 * @param id
 * @param params
 * @returns
 */
export function editRecycleDeviceModel(params: Record<string, any>) {
    return request.put(`recycle/recycle_device_model/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除回收设备型号
 * @param id
 * @returns
 */
export function deleteRecycleDeviceModel(id: number) {
    return request.delete(`recycle/recycle_device_model/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}



// USER_CODE_END -- recycle_device_model
