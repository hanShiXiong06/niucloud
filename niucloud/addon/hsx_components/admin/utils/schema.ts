import type { AnyRecord, ProFormField, SchemaValue, SelectOption, VisibleRule } from '../types'
import { getPathValue } from './object'

export function evaluateSchemaRule<T extends AnyRecord>(
    rule: VisibleRule<T> | undefined,
    model: T,
    fallback = false
): boolean {
    if (rule === undefined) return fallback
    return typeof rule === 'function' ? Boolean(rule(model)) : Boolean(rule)
}

export function resolveSchemaValue<T extends AnyRecord, V>(
    value: SchemaValue<T, V> | undefined,
    model: T,
    fallback: V
): V {
    if (value === undefined) return fallback
    return typeof value === 'function'
        ? (value as (currentModel: T) => V)(model)
        : value
}

export function resolveSchemaOptions<T extends AnyRecord>(field: ProFormField<T>, model: T): SelectOption[] {
    return resolveSchemaValue(field.options, model, [])
}

export function optionDependencies<T extends AnyRecord>(field: ProFormField<T>, model: T): AnyRecord {
    return (field.optionsDependencies || []).reduce((result, path) => {
        result[path] = getPathValue(model, path)
        return result
    }, {} as AnyRecord)
}

export function optionDependencySignature<T extends AnyRecord>(field: ProFormField<T>, model: T): string {
    try {
        return JSON.stringify(optionDependencies(field, model))
    } catch {
        return (field.optionsDependencies || []).map((path) => String(getPathValue(model, path))).join('|')
    }
}
