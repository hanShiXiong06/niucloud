import type { CheckSummaryField } from './types'
import { resolveCheckFieldValue } from '@/addon/hsx_recycle/utils/checkValue'

/** 选项值 → 展示文案（选项类映射 label，开关映射 是/否，多选用顿号连接） */
export function summaryOptionLabel(field: CheckSummaryField, val: any): string {
    return resolveCheckFieldValue(field, val, { emptyText: '' })
}

/** 折叠态规格标签：[{ key, name, value }] */
export function buildSummaryChips(
    fields: CheckSummaryField[] = [],
    values: Record<string, any> = {}
): Array<{ key: string; name: string; value: string }> {
    const chips: Array<{ key: string; name: string; value: string }> = []
    fields.forEach((field) => {
        const v = values[field.field_key]
        const empty = Array.isArray(v) ? v.length === 0 : (v === undefined || v === null || v === '')
        if (empty) return
        chips.push({
            key: field.field_key,
            name: field.field_name,
            value: resolveCheckFieldValue(field, v, { emptyText: '', includeUnit: true })
        })
    })
    return chips
}

/** 校验必填摘要字段（必填与否跟随模板字段自身配置）。返回空串表示通过 */
export function validateSummaryRequired(
    fields: CheckSummaryField[] = [],
    values: Record<string, any> = {}
): string {
    for (const field of fields) {
        if (Number(field.is_required || 0) !== 1) continue
        const v = values[field.field_key]
        const empty = Array.isArray(v) ? v.length === 0 : (v === undefined || v === null || v === '')
        if (empty) return `请填写质检摘要项「${field.field_name}」`
    }
    return ''
}

/** 收集有效录入值 { field_key: value }（剔除空值） */
export function collectSummaryValues(
    fields: CheckSummaryField[] = [],
    values: Record<string, any> = {}
): Record<string, any> {
    const result: Record<string, any> = {}
    fields.forEach((field) => {
        const v = values[field.field_key]
        if (Array.isArray(v)) {
            if (v.length) result[field.field_key] = v
        } else if (v !== undefined && v !== null && v !== '') {
            result[field.field_key] = v
        }
    })
    return result
}
