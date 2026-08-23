import type { FormItemRule } from 'element-plus'
import type { AnyRecord } from '../types'

export function debounce<T extends (...args: any[]) => any>(fn: T, delay = 300) {
    let timer: ReturnType<typeof setTimeout> | undefined
    return function (this: ThisParameterType<T>, ...args: Parameters<T>) {
        if (timer) clearTimeout(timer)
        timer = setTimeout(() => fn.apply(this, args), delay)
    }
}

export function throttle<T extends (...args: any[]) => any>(fn: T, delay = 300) {
    let lastTime = 0
    return function (this: ThisParameterType<T>, ...args: Parameters<T>) {
        const now = Date.now()
        if (now - lastTime < delay) return
        lastTime = now
        return fn.apply(this, args)
    }
}

export function formatDate(value: string | number | Date | undefined, withTime = true): string {
    if (value === undefined || value === null || value === '') return '-'
    const date = value instanceof Date ? value : new Date(value)
    if (Number.isNaN(date.getTime())) return String(value)
    const pad = (part: number) => String(part).padStart(2, '0')
    const dateText = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
    return withTime ? `${dateText} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}` : dateText
}

export function formatMoney(value: string | number | undefined, digits = 2): string {
    const number = Number(value)
    if (!Number.isFinite(number)) return '0.00'
    return number.toLocaleString('zh-CN', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
    })
}

export function genRules(label: string, trigger: 'blur' | 'change' = 'blur'): FormItemRule[] {
    return [{ required: true, message: `请${trigger === 'change' ? '选择' : '输入'}${label}`, trigger }]
}

export function transformData(source: AnyRecord): AnyRecord {
    return Object.entries(source).reduce((result, [key, value]) => {
        if (Array.isArray(value) && key.endsWith('Range')) {
            const base = key.slice(0, -5)
            result[`${base}Start`] = value[0]
            result[`${base}End`] = value[1]
        } else if (value !== '' && value !== undefined && value !== null) {
            result[key] = value
        }
        return result
    }, {} as AnyRecord)
}
