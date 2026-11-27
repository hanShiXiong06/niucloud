/** 将后端空格/竖线拼接的规格统一为移动端易扫读的分隔格式。 */
export function erpSpecLine(value: any, fallback = '无规格'): string {
    const parts = String(value || '')
        .trim()
        .split(/\s*[·|｜]\s*|\s+/)
        .map(part => part.trim())
        .filter(Boolean)
    return Array.from(new Set(parts)).join(' · ') || fallback
}

export function erpDeviceIdentityLine(row: any, fallback = '未填写设备信息'): string {
    const spec = erpSpecLine(row?.spec, '')
    const identity = row?.imei ? `IMEI ${String(row.imei).trim()}` : (row?.sn ? `SN ${String(row.sn).trim()}` : '')
    return [identity, spec].filter(Boolean).join(' · ') || fallback
}
