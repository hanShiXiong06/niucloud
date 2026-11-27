import type { AnyRecord } from '../types'

export function deepClone<T>(value: T): T {
    if (value === undefined || value === null) return value
    if (typeof structuredClone === 'function') {
        try {
            return structuredClone(value)
        } catch (_) {
            // Vue Proxy、函数等不能 structuredClone 时使用递归兜底。
        }
    }
    if (value instanceof Date) return new Date(value.getTime()) as T
    if (Array.isArray(value)) return value.map((item) => deepClone(item)) as T
    if (typeof value === 'object') {
        return Object.entries(value as AnyRecord).reduce((result, [key, item]) => {
            result[key] = deepClone(item)
            return result
        }, {} as AnyRecord) as T
    }
    return value
}

export function isDeepEqual(left: any, right: any): boolean {
    if (Object.is(left, right)) return true
    if (left instanceof Date || right instanceof Date) {
        return left instanceof Date && right instanceof Date && left.getTime() === right.getTime()
    }
    if (Array.isArray(left) || Array.isArray(right)) {
        if (!Array.isArray(left) || !Array.isArray(right) || left.length !== right.length) return false
        return left.every((item, index) => isDeepEqual(item, right[index]))
    }
    if (!left || !right || typeof left !== 'object' || typeof right !== 'object') return false
    const leftKeys = Object.keys(left)
    const rightKeys = Object.keys(right)
    if (leftKeys.length !== rightKeys.length) return false
    return leftKeys.every((key) => Object.prototype.hasOwnProperty.call(right, key) && isDeepEqual(left[key], right[key]))
}

export function getPathValue(source: AnyRecord, path: string): any {
    return path.split('.').reduce((value, key) => value?.[key], source)
}

export function setPathValue(target: AnyRecord, path: string, value: any): void {
    const keys = path.split('.')
    const lastKey = keys.pop()
    if (!lastKey) return
    const parent = keys.reduce((current, key) => {
        if (!current[key] || typeof current[key] !== 'object') current[key] = {}
        return current[key]
    }, target)
    parent[lastKey] = value
}

export function clearObject<T extends AnyRecord>(target: T, keepKeys: string[] = []): T {
    const record = target as AnyRecord
    Object.keys(target).forEach((key) => {
        if (keepKeys.includes(key)) return
        const value = record[key]
        if (Array.isArray(value)) record[key] = []
        else if (typeof value === 'boolean') record[key] = false
        else if (typeof value === 'number') record[key] = undefined
        else record[key] = undefined
    })
    return target
}

export function removeEmptyValues<T extends AnyRecord>(source: T): Partial<T> {
    return Object.entries(source).reduce((result, [key, value]) => {
        const empty = value === '' || value === undefined || value === null
        if (!empty) result[key as keyof T] = value
        return result
    }, {} as Partial<T>)
}
