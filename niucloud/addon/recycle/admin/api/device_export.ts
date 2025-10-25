import request from '@/utils/request'

/**
 * 获取回收设备列表
 * @param params
 * @returns 
 */
export function getRecycleDeviceList(params: Record<string, any>) {
    return request.get('recycle/device_export/list', { params })
}

/**
 * 导出回收设备
 * @param params
 * @returns 
 */
export function exportRecycleDevice(params: Record<string, any>) {
    return request.post('recycle/device_export/export', params)
} 