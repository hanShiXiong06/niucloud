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

/**
 * 更新设备信息
 * @param deviceId 设备ID
 * @param data 更新数据
 * @returns
 */
export function updateDevice(deviceId: number, data: any) {
    return request.put(`recycle/recycle_device/${deviceId}`, { data })
}

/**
 * 将选中的设备批量同步到 ERP。
 */
export function syncRecycleDevicesToErp(deviceIds: Array<number | string>, targets: string[] = ['self_erp']) {
    return request.post('recycle/device_export/sync_erp', {
        device_ids: deviceIds,
        targets
    })
}
