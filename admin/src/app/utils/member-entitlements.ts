/** Read-only display extensions. These values never replace the account's membership level. */
export type MemberEntitlementSource = { key: string; title: string }
export type MemberEntitlementStatus = 'active' | 'expired' | 'not_opened' | 'review' | 'inactive' | 'not_joined' | 'unavailable'
export type MemberEntitlement = MemberEntitlementSource & {
    label: string; status: MemberEntitlementStatus; status_text: string; context: string;
    expires_at: number; reason: string
}

const sourceKeyPattern = /^[a-z][a-z0-9_]{0,63}$/i
const statusLabels: Record<MemberEntitlementStatus, string> = {
    active: '生效中', expired: '已到期', not_opened: '未开通', review: '待核对',
    inactive: '当前未生效', not_joined: '未关联', unavailable: '读取失败'
}
const text = (value: unknown, limit = 120) => typeof value === 'string' ? value.trim().slice(0, limit) : ''
const record = (value: unknown): Record<string, any> | null => value !== null && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, any> : null

export function normalizeMemberEntitlementSources(value: unknown): MemberEntitlementSource[] {
    if (!Array.isArray(value)) return []
    const seen = new Set<string>()
    return value.slice(0, 32).flatMap(item => {
        const row = record(item), key = text(row?.key, 64), title = text(row?.title, 80)
        if (!sourceKeyPattern.test(key) || !title || seen.has(key)) return []
        seen.add(key)
        return [{ key, title }]
    })
}

/** Missing or malformed extension data is not evidence that somebody has no paid rights. */
export function normalizeMemberEntitlements(value: unknown, sourceValue: unknown, selectedKey = ''): MemberEntitlement[] {
    const rows = Array.isArray(value) ? value.map(record).filter((row): row is Record<string, any> => row !== null) : []
    return normalizeMemberEntitlementSources(sourceValue).filter(source => !selectedKey || source.key === selectedKey).map(source => {
        const matches = rows.filter(row => row.key === source.key), row = matches.length === 1 ? matches[0] : null
        const state = typeof row?.status === 'string' && Object.prototype.hasOwnProperty.call(statusLabels, row.status) ? row.status as MemberEntitlementStatus : 'unavailable'
        const rawExpiry = row?.expires_at
        const expiry = typeof rawExpiry === 'number' ? rawExpiry : typeof rawExpiry === 'string' && /^\d+$/.test(rawExpiry) ? Number(rawExpiry) : NaN
        const invalid = !row || !Number.isSafeInteger(expiry) || expiry < 0 || expiry > 253402300799
        const status: MemberEntitlementStatus = invalid ? 'unavailable' : state
        return {
            ...source,
            label: status === 'unavailable' ? '权益信息暂不可用' : text(row?.label) || statusLabels[status],
            status,
            status_text: status === 'unavailable' ? statusLabels.unavailable : text(row?.status_text, 40) || statusLabels[status],
            context: text(row?.context, 200), expires_at: status === 'unavailable' ? 0 : expiry,
            reason: status === 'unavailable' ? '请刷新后重试，仍失败请联系管理员核对' : text(row?.reason, 300)
        }
    })
}

export function memberEntitlementTone(status: MemberEntitlementStatus): 'success' | 'warning' | 'danger' | 'info' {
    return status === 'active' ? 'success' : status === 'expired' || status === 'review' ? 'warning' : status === 'unavailable' ? 'danger' : 'info'
}

export function memberEntitlementExpiry(item: MemberEntitlement, format: (value: number) => string): string {
    if (item.status === 'unavailable' || item.status === 'not_joined' || item.status === 'not_opened') return ''
    if (item.expires_at > 0) return `${format(item.expires_at)} 到期`
    return item.status === 'active' ? '长期有效' : ''
}
