import type { DeviceEntryRow } from './types'

/** 设备录入串号：不做 IMEI 校验位验证，也不静默删除空格；沿用既有 15 位上限。 */
export function deviceImeiError(value: unknown): string {
    if (value == null || value === '') return '请填写 IMEI'
    if (!['string', 'number'].includes(typeof value) || (typeof value === 'number' && !Number.isSafeInteger(value)) || /[^A-Za-z0-9]/.test(String(value))) {
        return 'IMEI 只能包含英文字母和数字，不能有空格或特殊字符'
    }
    if (String(value).length < 6) return 'IMEI 至少填写 6 位'
    if (String(value).length > 15) return 'IMEI 不能超过 15 位'
    return ''
}

/** 只传实际要保存/签收的设备，新增的空白占位行不参与最终签收。 */
export function validateDeviceEntryImeis(rows: DeviceEntryRow[]): string {
    let firstError = ''
    rows.forEach((row, index) => {
        row.imei_touched = true
        const error = deviceImeiError(row.imei)
        if (error && !firstError) firstError = `第 ${index + 1} 台设备：${error}`
    })
    return firstError
}
