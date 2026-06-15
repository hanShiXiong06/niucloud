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
    return request.put(`recycle/recycle_device/${deviceId}`, data)
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

/**
 * 批量查询设备下游同步健康度（仅"卡住"的设备才需显示「重新同步」）。
 * 返回 { [deviceId]: { stuck:boolean, has_asset:boolean, pending:number, failed:number, reason:string } }
 */
export function getDeviceSyncHealth(deviceIds: Array<number | string>) {
    return request.post('recycle/device_export/sync_health', { device_ids: deviceIds }, { showErrorMessage: false })
}

/**
 * 重新同步单台设备（兜底：事件失效时手动补齐下游步骤，如中台待拍照）。
 */
export function resyncRecycleDevice(deviceId: number | string) {
    return request.post(`recycle/device_export/${deviceId}/resync`, {})
}
