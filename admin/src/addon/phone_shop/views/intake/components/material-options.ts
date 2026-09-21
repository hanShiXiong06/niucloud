export type ParameterRow = {
    attr_id: number; attr_value_id: string | number; attr_value_name: string; type: string; sort: number
    attr_child_value_id: string | string[]; attr_child_value_name: string | string[]
}
export type MaterialForm = {
    memory_group: string; device_color: string; condition_grade: string
    battery_health: number | undefined; warranty_date: string; attr_ids: number[]; attr_format: ParameterRow[]
}
export const emptyMaterialForm = (): MaterialForm => ({ memory_group: '', device_color: '', condition_grade: '', battery_health: undefined, warranty_date: '', attr_ids: [], attr_format: [] })
export const selectionFields = [{ key: 'memory_group', label: '容量 / 规格' }, { key: 'device_color', label: '颜色' }, { key: 'condition_grade', label: '成色' }] as const
export const asArray = (value: any): any[] => {
    if (Array.isArray(value)) return value
    if (typeof value !== 'string' || !value.trim()) return []
    try { const result = JSON.parse(value); return Array.isArray(result) ? result : [] } catch { return [] }
}
const text = (value: any) => value == null ? '' : String(value).trim()
const unique = (values: string[]) => [...new Set(values.filter(Boolean))]
export const standardField = (name: string): 'memory_group' | 'device_color' | '' => /^(设备)?(颜色|配色|色彩)$/.test(name.trim()) ? 'device_color' : /^(内存|容量|存储容量|内存规格)$/.test(name.trim()) ? 'memory_group' : ''
export const parameterKey = (row: any) => `${Number(row.attr_id)}:${String(row.attr_value_id)}`
export const batteryValue = (value: any): number | undefined => {
    if (value == null || text(value) === '') return undefined
    const number = Number(value)
    return Number.isInteger(number) && number >= 0 && number <= 100 ? number : undefined
}

export function materialOptions(catalog: any, attrIds: number[]) {
    const options: Record<string, string[]> = { memory_group: [], device_color: [], condition_grade: [] }
    for (const group of asArray(catalog?.spec_groups)) {
        const label = text(group.label)
        const key = /颜色|配色|色彩/.test(label) ? 'device_color' : /内存|容量|存储|规格/.test(label) ? 'memory_group' : ''
        if (key) options[key].push(...asArray(group.items).map(item => text(item.item_value)))
    }
    options.condition_grade = asArray(catalog?.grades).filter(grade => Number(grade.status ?? 1) === 1).map(grade => text(grade.grade_name))
    for (const template of asArray(catalog?.templates).filter(row => attrIds.includes(Number(row.attr_id)))) {
        for (const field of asArray(template.fields)) {
            const name = text(field.attr_value_name)
            const key = standardField(name)
            if (key && ['radio', 'checkbox'].includes(field.type)) options[key].push(...asArray(field.child).map(item => text(item.name)))
        }
    }
    for (const key of Object.keys(options)) options[key] = unique(options[key])
    return options
}

export function parameterFields(catalog: any, attrIds: number[]) {
    return asArray(catalog?.templates).filter(item => attrIds.includes(Number(item.attr_id)))
        .flatMap(template => asArray(template.fields).map(field => ({ ...field, attr_id: Number(template.attr_id), template_name: template.attr_name })))
        .sort((a, b) => Number(b.sort || 0) - Number(a.sort || 0))
}

/** 切换模板只更换模板所属字段；保留原自定义参数及仍在所选模板中的历史字段。 */
export function mergeParameters(fields: any[], attrIds: number[], previous: any[]): ParameterRow[] {
    const old = new Map(previous.map(row => [parameterKey(row), row]))
    const keys = new Set(fields.map(parameterKey))
    const rows = fields.map(field => old.get(parameterKey(field)) || {
        attr_id: Number(field.attr_id), attr_value_id: field.attr_value_id, attr_value_name: field.attr_value_name,
        type: field.type, sort: Number(field.sort || 0),
        attr_child_value_id: field.type === 'checkbox' ? [] : '', attr_child_value_name: field.type === 'checkbox' ? [] : ''
    })
    return [...rows, ...previous.filter(row => !keys.has(parameterKey(row)) && (Number(row.attr_id) <= 0 || attrIds.includes(Number(row.attr_id))))]
}

export function setParameterValue(row: ParameterRow, field: any, value: string | string[]) {
    const children = new Map(asArray(field.child).map(child => [String(child.id), String(child.name)]))
    row.attr_child_value_id = value
    row.attr_child_value_name = Array.isArray(value) ? value.map(id => children.get(String(id)) || '') : children.get(String(value)) || ''
}

export function parameterDisplay(value: any): string {
    return Array.isArray(value) ? value.map(text).filter(Boolean).join('、') : text(value)
}

export function unmatchedParameterOptions(field: any, row?: ParameterRow) {
    if (field.type === 'text') return []
    const ids = field.type === 'checkbox' ? asArray(row?.attr_child_value_id).map(String) : [String(row?.attr_child_value_id ?? '')]
    const names = field.type === 'checkbox' ? asArray(row?.attr_child_value_name) : [row?.attr_child_value_name]
    return ids.flatMap((id, index) => id && !asArray(field.child).some(child => String(child.id) === id) ? [{ id, name: String(names[index] || '未对应') }] : [])
}

export function materialReview(catalog: any, form: MaterialForm) {
    const options = materialOptions(catalog, form.attr_ids)
    const unmatched = selectionFields.filter(field => form[field.key] && !options[field.key].includes(form[field.key])).map(field => field.label)
    const missing = [...selectionFields.filter(field => !form[field.key].trim()).map(field => field.label),
        ...(form.battery_health == null ? ['电池健康度'] : []), ...(!form.warranty_date ? ['保修到期日'] : [])]
    const invalidParameters = parameterFields(catalog, form.attr_ids).some(field => unmatchedParameterOptions(field, form.attr_format.find(row => parameterKey(row) === parameterKey(field))).length)
    return { invalidParameters, notes: [missing.length ? `${missing.join('、')}仍未记录，将保持未知。` : '',
        unmatched.length ? `${unmatched.join('、')}尚未对应商城选项，将保留原值。` : ''].filter(Boolean).join(' ') }
}
