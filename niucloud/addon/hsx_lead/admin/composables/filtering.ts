/** Small, UI-independent helpers for applied list filters. Calendar dates use the operator's local day. */
export type FilterOption = { value: string | number; label: string }
export type FilterDefinition = { key: string; label: string; options: FilterOption[]; loading?: boolean }
export type TimeField = { value: string; label: string }
export function calendarDate(value: number | Date): string {
    const date = value instanceof Date ? value : new Date(value * 1000)
    if (!Number.isFinite(date.getTime())) return ''
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}
export function calendarBoundary(value: string, end = false): number {
    if (!value) return 0
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)
    if (!match) return NaN
    const year = Number(match[1]),
        month = Number(match[2]),
        day = Number(match[3])
    const date = new Date(year, month - 1, day)
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return NaN
    if (end) date.setHours(23, 59, 59, 0)
    return Math.floor(date.getTime() / 1000)
}
export function calendarPreset(days: number, yesterday = false, now = new Date()): [string, string] {
    const end = new Date(now.getFullYear(), now.getMonth(), now.getDate())
    if (yesterday) end.setDate(end.getDate() - 1)
    const start = new Date(end)
    start.setDate(start.getDate() - Math.max(0, days - 1))
    return [calendarDate(start), calendarDate(end)]
}
export function filterIsActive(value: unknown): boolean {
    return value !== '' && value !== null && value !== undefined
}
export function hasListFilters(query: Record<string, any>, keys: string[] = []): boolean {
    return (
        !!String(query.keyword || '').trim() ||
        keys.some((key) => filterIsActive(query[key])) ||
        Number(query.start_time) > 0 ||
        Number(query.end_time) > 0
    )
}
