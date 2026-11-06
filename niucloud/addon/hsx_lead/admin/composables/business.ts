import type { CoopRow } from './cooperation'

export const businessDimensionLabels: Record<string, string> = { model: '型号', brand: '品牌', operator: '运营商' }

/** Match the settlement contract: money is at most two decimals and previews use integer cents. */
export function businessAmountCents(value: unknown): number | null {
    if ((typeof value !== 'number' && typeof value !== 'string') || !/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/.test(String(value))) return null
    const [whole, fraction = ''] = String(value).split('.')
    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'))
}
export function cooperationFeePreview(amount: unknown, percent: unknown, minimum: unknown) {
    const gross = businessAmountCents(amount), basis = businessAmountCents(percent), floor = businessAmountCents(minimum)
    if (gross === null || gross < 1 || basis === null || basis >= 10000 || floor === null) return { valid: false, error: '请填写有效金额和平台费规则', amount: 0, fee: 0, net: 0 }
    const fee = Math.max(floor, Math.floor((gross * basis + 5000) / 10000))
    if (fee >= gross) return { valid: false, error: '平台维护费须低于本笔佣金，发布方才能获得收益', amount: gross / 100, fee: fee / 100, net: (gross - fee) / 100 }
    return { valid: true, error: '', amount: gross / 100, fee: fee / 100, net: (gross - fee) / 100 }
}
export function normalizeBusinesses(rows: unknown): CoopRow[] {
    return (Array.isArray(rows) ? rows : []).map(row => ({ ...row, enabled: row.enabled === true || row.enabled === 1 || row.enabled === '1', fee_override: row.fee_override === true || row.fee_override === 1 || row.fee_override === '1', fee_percent: Number(row.fee_percent ?? 0), fee_minimum: Number(row.fee_minimum ?? 0), selection_limit: Number(row.selection_limit ?? 0), commission_minimum: Number(row.commission_minimum ?? 0), fields: (row.fields || []).map((field: CoopRow) => ({ ...field, placeholder: field.placeholder || '', required: field.required === true || field.required === 1 || field.required === '1', options: field.options || [] })), options: row.options || [] }))
}
/** Read the same effective fee contract that the server freezes on each new lead. */
export function businessFeeRules(row: CoopRow, config: CoopRow): { percent: unknown; minimum: unknown } {
    return row.fee_override === true ? { percent: row.fee_percent, minimum: row.fee_minimum } : { percent: config.fee_percent, minimum: config.fee_minimum }
}
/** Never mutate a preset, another business, or enable a newly added business silently. */
export function addBusinessDraft(rows: CoopRow[], definition: CoopRow): string {
    const draft = normalizeBusinesses([JSON.parse(JSON.stringify(definition))])[0]
    draft.enabled = false
    if (rows.some(row => row.key === draft.key)) return '该业务已在列表中，请直接打开设置'
    if (rows.length >= 30) return '最多配置30项业务，请先暂停不再开展的业务'
    const error = businessValidation([draft])
    if (error) return error
    rows.push(draft)
    return ''
}
export function businessValidation(rows: CoopRow[]): string {
    if (!rows.length) return '未读取到多业务配置，请确认配套后端已更新后重新读取'
    const keys = new Set<string>()
    for (const row of rows) {
        const name = String(row.name || '').trim(), prefix = name || '未命名业务'
        if (!/^[a-z][a-z0-9_]{0,15}$/.test(row.key) || ['__proto__', 'prototype', 'constructor'].includes(row.key) || keys.has(row.key)) return '业务标识无效或重复，请重新读取配置'
        keys.add(row.key)
        if (!name || name.length > 100 || /[<>\x00-\x1F\x7F]/.test(name)) return `${prefix}：名称须为1—100字纯文本`
        if (!['model', 'brand', 'operator'].includes(row.dimension)) return `${prefix}：请选择需求维度`
        if (!['single', 'multiple'].includes(row.selection_mode)) return `${prefix}：请选择单选或多选`
        if (!Number.isInteger(Number(row.selection_limit)) || Number(row.selection_limit) < 0 || Number(row.selection_limit) > 5000) return `${prefix}：数量上限请填写0—5000的整数，0表示不限`
        const minimum = businessAmountCents(row.commission_minimum)
        if (minimum === null || minimum < 1) return `${prefix}：最低协作费用须大于0，最多两位小数`
        if (row.fee_override === true && !cooperationFeePreview(row.commission_minimum, row.fee_percent, row.fee_minimum).valid) return `${prefix}：独立平台费规则无效，最低金额试算后须留有发布方收益`
        const fields = new Set<string>()
        if (row.fields.length > 20) return `${prefix}：最多配置20个补充字段`
        for (const field of row.fields) {
            if (!/^[a-z][a-z0-9_]{0,15}$/.test(field.key) || ['__proto__', 'prototype', 'constructor'].includes(field.key) || fields.has(field.key)) return `${prefix}：字段标识须为1—16位小写字母、数字或下划线且不重复，以字母开头`
            fields.add(field.key)
            if (!String(field.label || '').trim() || String(field.label).length > 100 || /[<>\x00-\x1F\x7F]/.test(field.label)) return `${prefix}：请填写1—100字的字段名称，只支持纯文本`
            if (!['text', 'number', 'select', 'address'].includes(field.type)) return `${prefix}：字段仅支持文本、数字、下拉选择或地址`
            if (field.placeholder != null && (typeof field.placeholder !== 'string' || field.placeholder.length > 120 || /[<>\x00-\x1F\x7F]/.test(field.placeholder))) return `${prefix}：输入提示最多120字，只支持纯文本`
            if (field.type === 'select' && (!field.options?.length || field.options.length > 100 || field.options.some((option: CoopRow) => !String(option.value || '').trim() || !String(option.label || '').trim()) || new Set(field.options.map((option: CoopRow) => option.value)).size !== field.options.length)) return `${prefix} · ${field.label}：下拉选项须为1—100项，选项值不能为空或重复`
        }
        if (row.options.length > 5000) return `${prefix}：目录最多5000项`
        const optionIds = new Set<string>()
        for (const option of row.options) {
            if (!/^[A-Za-z0-9_-]{1,100}$/.test(option.id) || optionIds.has(option.id)) return `${prefix}：目录ID须唯一，只含字母、数字、下划线或横线`
            optionIds.add(option.id)
            if (!String(option.title || '').trim()) return `${prefix}：请填写每个目录选项的名称`
        }
    }
    return ''
}
export function businessPayload(rows: CoopRow[]): CoopRow[] {
    return rows.map(row => ({ ...row, commission_minimum: Number(row.commission_minimum).toFixed(2), fee_override: !!row.fee_override, ...(row.fee_override ? { fee_percent: Number(row.fee_percent).toFixed(2), fee_minimum: Number(row.fee_minimum).toFixed(2) } : {}), fields: row.fields.map((field: CoopRow) => ({ key: field.key, label: field.label, type: field.type, required: !!field.required, placeholder: String(field.placeholder || '').trim(), ...(field.type === 'select' ? { options: field.options } : {}) })) }))
}
export function demandNames(row: CoopRow): string[] {
    return (Array.isArray(row.demands) ? row.demands : Array.isArray(row.models) ? row.models : []).map((item: CoopRow) => String(item.title || item.name || '')).filter(Boolean)
}
export function businessFormEntries(row: CoopRow): { key: string; label: string; value: string }[] {
    const values = row.form_values && typeof row.form_values === 'object' && !Array.isArray(row.form_values) ? row.form_values : {}
    const fields = row.business_snapshot?.fields || []
    return Object.entries(values).map(([key, value]) => {
        const field = fields.find((item: CoopRow) => item.key === key)
        const option = field?.options?.find((item: CoopRow) => String(item.value) === String(value))
        return { key, label: field?.label || key, value: String(option?.label ?? value ?? '') }
    })
}
