import type { AnyRecord, HsxDiyPageData, MobileFormField, MobileSchemaValue, MobileOption } from '../types'

export function debounce<T extends (...args: any[]) => any>(fn: T, delay = 300) {
    let timer: ReturnType<typeof setTimeout> | undefined
    return (...args: Parameters<T>) => {
        if (timer) clearTimeout(timer)
        timer = setTimeout(() => fn(...args), delay)
    }
}

export function throttle<T extends (...args: any[]) => any>(fn: T, delay = 300) {
    let lastTime = 0
    return (...args: Parameters<T>) => {
        const now = Date.now()
        if (now - lastTime < delay) return
        lastTime = now
        return fn(...args)
    }
}

export function formatMoney(value: string | number | undefined, digits = 2): string {
    const number = Number(value)
    if (!Number.isFinite(number)) return '0.00'
    return number.toFixed(digits).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

export function formatDate(value: string | number | Date | undefined): string {
    if (value === undefined || value === null || value === '') return '-'
    const date = value instanceof Date ? value : new Date(value)
    if (Number.isNaN(date.getTime())) return String(value)
    const pad = (part: number) => String(part).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

export function cleanParams<T extends AnyRecord>(source: T): Partial<T> {
    return Object.entries(source).reduce((result, [key, value]) => {
        if (value !== '' && value !== null && value !== undefined) result[key as keyof T] = value
        return result
    }, {} as Partial<T>)
}

/** 判断一个筛选值是否真正生效。空字符串、空数组、false 和空对象不计入。 */
export function isActiveMobileFilterValue(value: any): boolean {
    if (value === undefined || value === null || value === false) return false
    if (typeof value === 'string') return value.trim() !== ''
    if (Array.isArray(value)) return value.some((item) => isActiveMobileFilterValue(item))
    if (typeof value === 'object') return Object.values(value).some((item) => isActiveMobileFilterValue(item))
    return true
}

/** 按字段统计已生效筛选数；一个日期区间或多选数组只算一个条件。 */
export function countActiveMobileFilters(source: AnyRecord = {}, ignoredKeys: string[] = []): number {
    const ignored = new Set(ignoredKeys)
    return Object.entries(source).reduce((count, [key, value]) => (
        ignored.has(key) || !isActiveMobileFilterValue(value) ? count : count + 1
    ), 0)
}

/** 清理提交给列表接口的空筛选，同时保留 0 等合法业务值。 */
export function compactMobileFilters<T extends AnyRecord>(source: T): Partial<T> {
    return Object.entries(source).reduce((result, [key, value]) => {
        if (isActiveMobileFilterValue(value)) result[key as keyof T] = deepClone(value)
        return result
    }, {} as Partial<T>)
}

export function deepClone<T>(value: T): T {
    if (value === undefined || value === null) return value
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

export function resolveMobileSchemaValue<T extends AnyRecord, V>(
    value: MobileSchemaValue<T, V> | undefined,
    model: T,
    fallback: V
): V {
    if (value === undefined) return fallback
    return typeof value === 'function' ? (value as (currentModel: T) => V)(model) : value
}

export function resolveMobileOptions<T extends AnyRecord>(field: MobileFormField<T>, model: T): MobileOption[] {
    return resolveMobileSchemaValue(field.options, model, [])
}

export function mobileOptionDependencies<T extends AnyRecord>(field: MobileFormField<T>, model: T): AnyRecord {
    return (field.optionsDependencies || []).reduce((result, path) => {
        result[path] = getPathValue(model, path)
        return result
    }, {} as AnyRecord)
}

export function mobileOptionDependencySignature<T extends AnyRecord>(field: MobileFormField<T>, model: T): string {
    try {
        return JSON.stringify(mobileOptionDependencies(field, model))
    } catch {
        return (field.optionsDependencies || []).map((path) => String(getPathValue(model, path))).join('|')
    }
}

/** 保留字段模型类型的 Schema 声明助手，Template 与 TSX 可共用。 */
export function defineMobileFormSchema<T extends AnyRecord = AnyRecord>(schema: MobileFormField<T>[]) {
    return schema
}

/** 将 NiuCloud DIY 页面数据收敛为稳定协议；组件本体继续由系统 renderer 负责。 */
export function normalizeHsxDiyPageData(source: Partial<HsxDiyPageData> | null | undefined): HsxDiyPageData {
    const global = source?.global && typeof source.global === 'object' ? deepClone(source.global) : {}
    const sourceValue = source?.value
    const value = Array.isArray(sourceValue)
        ? sourceValue.filter((item) => item && typeof item.componentName === 'string' && item.componentName.trim() !== '').map((item) => {
            const cloned = deepClone(item)
            return {
                ...cloned,
                componentIsShow: cloned.componentIsShow ?? true,
                margin: { top: 0, bottom: 0, ...deepClone(cloned.margin || {}) }
            }
        })
        : []
    return { global, value }
}
