/** Business configuration and persisted lead snapshots are server-owned. */
export interface BusinessOption {
    id: string
    title: string
    path?: string[]
    [key: string]: any
}
export interface BusinessField {
    key: string
    label: string
    type: 'text' | 'number' | 'select' | 'address'
    required?: boolean
    placeholder?: string
    options?: Array<{ value: string; label: string }>
}
export interface CooperationBusiness {
    key: string
    name: string
    enabled: boolean
    dimension: 'model' | 'brand' | 'operator'
    selection_mode: 'multiple' | 'single'
    selection_limit: number
    commission_minimum: string
    fee_override?: boolean
    fee_percent?: string
    fee_minimum?: string
    fields: BusinessField[]
    options: BusinessOption[]
}
export function enabledBusinesses(config: Record<string, any>): CooperationBusiness[] {
    return Array.isArray(config?.businesses)
        ? config.businesses.filter((item: any) => item && item.enabled && typeof item.key === 'string')
        : []
}
export function businessDimensionLabel(business?: CooperationBusiness) {
    return business?.dimension === 'brand' ? '品牌' : business?.dimension === 'operator' ? '运营商' : '型号'
}
export function businessSelectionLimit(business?: CooperationBusiness) {
    if (business?.selection_mode === 'single') return 1
    const value = Number(business?.selection_limit)
    return Number.isFinite(value) ? Math.min(5000, Math.max(0, Math.floor(value))) : 0
}
export function businessFormError(
    business: CooperationBusiness | undefined,
    items: BusinessOption[],
    values: Record<string, any>
) {
    if (!business) return '当前业务尚未开放，请选择已启用的业务'
    const label = businessDimensionLabel(business),
        limit = businessSelectionLimit(business)
    if (!items.length) return `请至少选择一个意向${label}`
    if (limit && items.length > limit) return `意向${label}最多选择 ${limit} 项`
    for (const field of business.fields || []) {
        const value = values[field.key]
        if (field.required && (value == null || String(value).trim() === '')) return `请填写${field.label}`
        if (value == null || String(value).trim() === '') continue
        if (!['string', 'number'].includes(typeof value) || (typeof value === 'number' && !Number.isInteger(value)))
            return `${field.label}格式不正确`
        if (
            field.type === 'address' &&
            (Array.from(String(value).trim()).length > 300 || /[<>\x00-\x1F\x7F]/.test(String(value)))
        )
            return `${field.label}请填写300字以内的小区、街道或地标`
        if (field.type === 'number' && !Number.isFinite(Number(value))) return `${field.label}请填写有效数字`
        if (field.type === 'select' && !(field.options || []).some((option) => String(option.value) === String(value)))
            return `请选择有效的${field.label}`
    }
    return ''
}
/** An address is plain text, not a geocoded location. Only copy what the server has disclosed. */
export function businessFieldValue(field: BusinessField, values: Record<string, any> = {}): string {
    const value = values?.[field.key]
    if (value == null || !['string', 'number'].includes(typeof value)) return ''
    if (field.type === 'select')
        return field.options?.find((option) => String(option.value) === String(value))?.label || String(value)
    return String(value).trim()
}
export function businessAddressText(region: unknown, address: unknown): string {
    if (typeof address !== 'string' || !address.trim()) return ''
    const place = address.trim(),
        area = typeof region === 'string' ? region.trim() : ''
    return area && !place.startsWith(area) ? `${area} ${place}` : place
}
export function businessFeeRules(business: CooperationBusiness | undefined, config: Record<string, any>) {
    const source = business?.fee_override === true ? business : config
    return { percent: source?.fee_percent, minimum: source?.fee_minimum }
}
export function leadFeeSnapshot(lead: Record<string, any>) {
    const minimum = Number(lead.commission_minimum),
        percent = Number(lead.fee_percent),
        feeMinimum = Number(lead.fee_minimum)
    const known =
        lead.fee_confirmed === true &&
        lead.commission_minimum != null &&
        lead.fee_percent != null &&
        lead.fee_minimum != null &&
        [minimum, percent, feeMinimum].every(Number.isFinite) &&
        minimum >= 0 &&
        percent >= 0 &&
        percent <= 100 &&
        feeMinimum >= 0
    return { known, minimum, percent, feeMinimum }
}
