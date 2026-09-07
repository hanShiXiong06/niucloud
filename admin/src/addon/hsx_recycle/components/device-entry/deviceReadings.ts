import type { CheckSummaryField, DeviceEntryRow } from './types'

export interface DeviceReadings extends Record<string, unknown> {
    version: 1
    local?: { raw: Record<string, any>; normalized: Record<string, any>; received_at?: string; saved_at?: number }
    model_match?: { strategy: string; category_id: number; model: string; candidates: string[] }
    external_queries?: Array<Record<string, any>>
}

export interface LocalMappedDevice {
    model: string
    imei: string
    imei2: string
    serial_number: string
    capacity: string
    color: string
    color_index?: number
    system_version: string
    warranty_info: string
    battery_health: string
    battery_health_source: string
    battery_cycle_count: string | number
    product_type: string
    model_number: string
    model_candidates: string[]
    raw: Record<string, any>
}

const text = (value: unknown): string => value === null || value === undefined ? '' : String(value).trim()
export const hasReadingValue = (value: unknown): boolean => Array.isArray(value)
    ? value.length > 0 : value !== undefined && value !== null && value !== ''

function numeric(value: unknown): number | undefined {
    const input = text(value).replace(/%$/, '').trim()
    if (!/^\d+(\.\d+)?$/.test(input)) return undefined
    const result = Number(input)
    return Number.isFinite(result) ? result : undefined
}

/** 业务健康度与瞬时电量、计算容量比严格分开，原值始终保留在 raw。 */
export function mapLocalDevice(d: any): LocalMappedDevice {
    const identity = d?.identity || {}
    const hardware = d?.hardware || {}
    const display = d?.display || {}
    const system = d?.system || {}
    const battery = d?.battery || {}
    const candidates: Array<[unknown, string]> = [
        [battery.health_percent, 'battery.health_percent'],
        [d?.battery_health, 'battery_health'],
        [battery.calculated_health_percent, 'battery.calculated_health_percent']
    ]
    let health = ''
    let healthSource = ''
    for (const [value, source] of candidates) {
        const number = numeric(value)
        if (number === undefined || (source !== 'battery.calculated_health_percent' && number > 100)) continue
        health = String(Math.min(100, Math.round(number)))
        healthSource = source
        break
    }
    const cycle = numeric(battery.cycle_count ?? d?.battery_cycle_count ?? d?.battery_cycle)
    const model = text(display.device_name || d?.display_name || d?.model_name || d?.model || display.model_hint)
    // 结构化设备的名称可由用户任意修改；只学习硬件标识，避免把“张三的手机”绑定成公共型号。
    const modelCandidates = d?.schema_version ? [hardware.product_type, display.model_hint, hardware.model_number_full, hardware.model_number]
        : [model, model ? `苹果 ${model}` : '', d?.product_type, d?.model_number]
    const rawColor = text(display.color || d?.color)
    const colorCode = numeric(display.color_index ?? hardware.color_code ?? d?.color_index ?? d?.color_code)
    return {
        model, imei: text(identity.imei || d?.imei), imei2: text(identity.imei2 || d?.imei2),
        serial_number: text(identity.serial_number || d?.serial_number || d?.sn),
        capacity: text(display.capacity || d?.storage || d?.total_storage || d?.capacity),
        color: /^\d+$/.test(rawColor) ? '' : rawColor,
        color_index: colorCode !== undefined && Number.isInteger(colorCode) ? colorCode : undefined,
        system_version: text(system.version || d?.ios_version || d?.os_version || d?.system_version || d?.android_version),
        warranty_info: text(d?.warranty_info), battery_health: health, battery_health_source: healthSource,
        battery_cycle_count: cycle !== undefined && Number.isInteger(cycle) ? cycle : '',
        product_type: text(hardware.product_type || d?.product_type), model_number: text(hardware.model_number || d?.model_number),
        model_candidates: Array.from(new Set(modelCandidates.map(text).filter(Boolean))), raw: d
    }
}

export function localReadingArchive(mapped: LocalMappedDevice): DeviceReadings {
    const { raw, ...normalized } = mapped
    return { version: 1, local: { raw: JSON.parse(JSON.stringify(raw)), normalized, received_at: new Date().toISOString() } }
}

const normalizeOptionText = (value: unknown): string => text(value).toLowerCase().replace(/\s+/g, '').replace(/gb$/, 'g').replace(/tb$/, 't').replace(/%$/, '')
function resolveField(field: CheckSummaryField, raw: any): any {
    if (!hasReadingValue(raw)) return undefined
    if (field.options?.length) {
        // 读取的是物理值，不把颜色代码、容量数字当成数据库 option ID，更不按数组下标猜颜色。
        const matches = field.options.filter(option => [option.label, option.name].some(label => normalizeOptionText(label) === normalizeOptionText(raw)))
        if (matches.length !== 1) return undefined
        return field.component === 'checkbox' || field.selection_mode === 'multiple' ? [matches[0].value] : matches[0].value
    }
    return field.component === 'number' ? numeric(raw) : raw
}

export function prefillDeviceSummary(row: DeviceEntryRow, mapped: Partial<LocalMappedDevice>): void {
    const values = { ...(row.summary_values || {}) }
    const prefilled = new Set(row.local_prefilled_keys || [])
    const defaults = new Set(row.summary_default_keys || [])
    const mappings = [
        { keys: ['capacity'], names: ['存储容量', '内存', '容量'], value: mapped.capacity },
        { keys: ['color'], names: ['机身颜色', '颜色'], value: mapped.color },
        { keys: ['system_version'], names: ['系统版本'], value: mapped.system_version },
        { keys: ['warranty_info'], names: ['保修'], value: mapped.warranty_info },
        { keys: ['battery'], names: ['电池健康度', '电池健康'], value: mapped.battery_health },
        { keys: ['battery_num', 'battery_cycle', 'cycle_count'], names: ['循环次数', '电池循环'], value: mapped.battery_cycle_count }
    ]
    for (const field of row.summary_fields || []) {
        if (hasReadingValue(values[field.field_key]) && !defaults.has(field.field_key) && !prefilled.has(field.field_key)) continue
        const mapping = mappings.find(item => item.keys.includes(field.field_key) || item.names.some(name => field.field_name.includes(name)))
        if (!mapping) continue
        const value = resolveField(field, mapping.value)
        if (value === undefined || value === '') {
            if (defaults.has(field.field_key) || prefilled.has(field.field_key)) {
                values[field.field_key] = ''
                defaults.delete(field.field_key)
                prefilled.delete(field.field_key)
            }
            continue
        }
        values[field.field_key] = value
        defaults.delete(field.field_key)
        prefilled.add(field.field_key)
    }
    row.summary_values = values
    row.summary_default_keys = [...defaults]
    row.local_prefilled_keys = [...prefilled]
}

export function recordModelMatch(row: DeviceEntryRow, strategy: string): void {
    if (!row.device_readings?.local) return
    row.device_readings.model_match = {
        strategy, category_id: Number(row.category_id || 0), model: row.model,
        candidates: [...(row.local_model_aliases || [])]
    }
}

export function parseCoverageStatus(coverage: any): string {
    if (!coverage || typeof coverage !== 'object') return ''
    const status = text(coverage.status)
    const date = text(coverage.date)
    if (status === 'Out Of Warranty') return '过保'
    if (status === 'Not Activated') return '未激活'
    if (status === 'In Warranty' || status === 'Active') return date ? `保 ${date}` : '在保'
    // 缺日期不代表未激活；空响应更不能生成确定的业务结论。
    return date || status
}
