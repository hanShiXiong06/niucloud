/**
 * 质检字段值展示解析器。
 *
 * 模板选项历史上同时存在 id / value / option_value 等存储口径，
 * 所有展示场景统一通过这里把原始值还原成人可读的 label。
 */

export interface CheckValueOptionLike {
    id?: string | number
    option_id?: string | number
    value?: any
    option_value?: any
    label?: string
    name?: string
    option_label?: string
    text?: string
}

export interface CheckValueFieldLike {
    component?: string
    unit?: string
    options?: CheckValueOptionLike[]
}

export interface ResolveCheckValueOptions {
    emptyText?: string
    includeUnit?: boolean
    separator?: string
}

export const isCheckValueEmpty = (value: any): boolean => {
    if (value === undefined || value === null || value === '') return true
    return Array.isArray(value) && value.length === 0
}

const stringifyValue = (value: any): string => {
    if (value === undefined || value === null) return ''
    if (typeof value === 'object') {
        try {
            return JSON.stringify(value)
        } catch {
            return String(value)
        }
    }
    return String(value)
}

const optionLabel = (option: CheckValueOptionLike): string => {
    return String(option.label || option.name || option.option_label || option.text || option.value || option.option_value || option.id || '')
}

const optionKeys = (option: CheckValueOptionLike): string[] => {
    return [option.value, option.option_value, option.id, option.option_id]
        .filter(value => value !== undefined && value !== null && value !== '')
        .map(value => String(value))
}

const buildOptionLabelMap = (options: CheckValueOptionLike[] = []): Map<string, string> => {
    const map = new Map<string, string>()
    options.forEach((option) => {
        const label = optionLabel(option)
        if (!label) return
        optionKeys(option).forEach(key => map.set(key, label))
        // 历史数据偶尔直接保存 label，仍保持可读展示。
        map.set(label, label)
    })
    return map
}

const parseStoredValues = (rawValue: any, splitText: boolean): any[] => {
    if (Array.isArray(rawValue)) return rawValue
    if (rawValue && typeof rawValue === 'object') return [rawValue]
    if (typeof rawValue !== 'string') return [rawValue]

    const text = rawValue.trim()
    if (!text) return []
    if ((text.startsWith('[') && text.endsWith(']')) || (text.startsWith('{') && text.endsWith('}'))) {
        try {
            const parsed = JSON.parse(text)
            return Array.isArray(parsed) ? parsed : [parsed]
        } catch {
            // 非法 JSON 按普通文本继续处理。
        }
    }
    return splitText && /[,，]/u.test(text)
        ? text.split(/[,，]/u).map(item => item.trim()).filter(Boolean)
        : [text]
}

const objectStoredValue = (value: Record<string, any>): { key: string; label: string } => {
    const label = String(value.label || value.name || value.option_label || value.text || '')
    const raw = value.value ?? value.option_value ?? value.id ?? value.option_id ?? ''
    return { key: String(raw), label }
}

export const resolveCheckValueLabels = (
    options: CheckValueOptionLike[] = [],
    rawValue: any
): string[] => {
    if (isCheckValueEmpty(rawValue)) return []
    const labelMap = buildOptionLabelMap(options)
    const values = parseStoredValues(rawValue, options.length > 0)

    return values.map((value) => {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            const stored = objectStoredValue(value)
            return stored.label || labelMap.get(stored.key) || stored.key || stringifyValue(value)
        }
        const key = stringifyValue(value).trim()
        return labelMap.get(key) || key
    }).filter(Boolean)
}

const resolveSwitchText = (value: any): string => {
    const normalized = String(value).trim().toLowerCase()
    const enabled = value === true || value === 1 || ['1', 'true', 'yes', 'on', '开启', '是', '有锁'].includes(normalized)
    const disabled = value === false || value === 0 || ['0', 'false', 'no', 'off', '关闭', '否', '无锁'].includes(normalized)
    if (enabled) return '是'
    if (disabled) return '否'
    return stringifyValue(value)
}

export const resolveCheckFieldValue = (
    field: CheckValueFieldLike,
    rawValue: any,
    options: ResolveCheckValueOptions = {}
): string => {
    const emptyText = options.emptyText ?? '-'
    if (isCheckValueEmpty(rawValue)) return emptyText

    let text = ''
    if (field.component === 'switch' && !(field.options || []).length) {
        text = resolveSwitchText(rawValue)
    } else if (typeof rawValue === 'boolean' && !(field.options || []).length) {
        text = rawValue ? '是' : '否'
    } else {
        text = resolveCheckValueLabels(field.options || [], rawValue).join(options.separator || '、')
    }

    if (!text) return emptyText
    return options.includeUnit && field.unit ? `${text}${field.unit}` : text
}
