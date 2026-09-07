import { collectSummaryValues } from './summaryUtil'
import type { DeviceEntryRow } from './types'

function readingPayload(device: DeviceEntryRow) {
    return {
        imei2: (device.imei2 || '').trim(),
        serial_number: (device.serial_number || '').trim(),
        capacity: device.capacity ?? '', color: device.color ?? '',
        system_version: device.system_version ?? '', warranty_info: device.warranty_info ?? '',
        battery_health: device.battery_health ?? '', battery_cycle: device.battery_cycle_count ?? '',
        device_readings: device.device_readings
    }
}

/**
 * 新增设备 / 签收提交用的设备载荷（摘要以 { field_key: value } 形式传，后端组装 info）。
 */
export function normalizeDevice(device: DeviceEntryRow) {
    return {
        ...readingPayload(device),
        imei: (device.imei || '').trim(),
        model: (device.model || '').trim(),
        initial_price: Number(device.initial_price || 0),
        category_id: device.category_id || 0,
        category_path: Array.isArray(device.category_path) ? device.category_path : [],
        check_template_id: Number(device.check_template_id || 0),
        check_images_buyer: String(device.check_images_buyer || ''),
        summary: collectSummaryValues(device.summary_fields || [], device.summary_values || {})
    }
}

/**
 * 编辑已保存设备用的载荷（updateOrderDevice 走 $device->save 整包，需要直接给出 info）。
 */
export function buildUpdatePayload(device: DeviceEntryRow) {
    const summaryValues = collectSummaryValues(device.summary_fields || [], device.summary_values || {})
    const categoryPath = Array.isArray(device.category_path) && device.category_path.length
        ? device.category_path
        : (device.category_id ? [device.category_id] : [])
    const info: Record<string, any> = { goods_category: categoryPath, ...summaryValues }
    if (Object.keys(summaryValues).length) info.sign_summary = summaryValues
    return {
        ...readingPayload(device),
        model: (device.model || '').trim(),
        imei: (device.imei || '').trim(),
        initial_price: Number(device.initial_price || 0),
        category_id: device.category_id || 0,
        check_template_id: Number(device.check_template_id || 0),
        check_images_buyer: String(device.check_images_buyer || ''),
        summary: summaryValues,
        info
    }
}
