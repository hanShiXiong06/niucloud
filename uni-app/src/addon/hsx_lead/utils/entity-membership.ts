/** Presentation of server-owned entity rights. No price, seat or level is granted here. */
export const entityPageUrl = '/addon/hsx_lead/pages/entity/index'
export const membershipPageUrl = '/addon/hsx_lead/pages/membership/index'
export function entityPageLink(code = '') { return entityPageUrl + (code ? `?mode=join&subject_code=${encodeURIComponent(code)}` : '') }
export function entityLevel(value: unknown) { return /^ML[1-9][0-9]{0,9}$/.test(String(value)) ? String(value) : '' }
export function membershipCycles(tier: Record<string, any> = {}) { return (Array.isArray(tier.cycles) ? tier.cycles : []).filter((item: any) => item && item.enabled === true && /^[A-Za-z][A-Za-z0-9_-]{0,23}$/.test(String(item.key)) && Number.isSafeInteger(item.months) && item.months >= 1 && item.months <= 120 && /^\d+\.\d{2}$/.test(String(item.price)) && Number(item.price) > 0) }
export function membershipCycleKey(value: unknown) { return /^[A-Za-z][A-Za-z0-9_-]{0,23}$/.test(String(value)) ? String(value) : '' }
export function membershipBadgeRank(value: Record<string, any> = {}) { const rank = Number(value.display_rank); return entityLevel(value.level_code || value.code) && Number.isSafeInteger(rank) && rank > 0 ? rank : 0 }
export function membershipBenefits(value: Record<string, any> = {}) { const icons = new Set(['account', 'home', 'order', 'grid', 'star', 'calendar', 'checkmark-circle']); return (Array.isArray(value.benefits) ? value.benefits : []).filter((item: any) => item && typeof item.key === 'string' && typeof item.title === 'string').map((item: any) => ({ key: item.key, title: String(item.title).slice(0, 80), desc: String(item.desc || '').slice(0, 200), enabled: item.enabled === true, icon: icons.has(item.icon) ? item.icon : 'checkmark-circle' })) }
export function membershipLevelColor(value: Record<string, any> = {}) { const color = String(value.level_color || ''); return /^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i.test(color) ? color : 'var(--primary-color,#ff4444)' }
export function experienceText(value: unknown) { const text = String(value ?? ''); return /^(?:0|[1-9][0-9]{0,13})(?:\.[0-9]{1,2})?$/.test(text) ? text.replace(/\.00$/, '').replace(/(\.[0-9])0$/, '$1') : '—' }
/** Missing fields retain the existing qualification rule; only an explicit server switch waives it. */
export function experienceGateEnabled(value: Record<string, any> = {}) { return value.experience_gate_enabled !== false }
export function entityAccounts(value: Record<string, any>) { return value?.unlimited === true || value?.unlimited === 1 ? '不限人数' : `${Number.isSafeInteger(Number(value?.account_limit)) && Number(value.account_limit) > 0 ? Number(value.account_limit) : '—'} 人` }
export function entityExpiry(value: unknown) { const at = Number(value); if (!Number.isFinite(at) || at <= 0) return '尚未开通'; const date = new Date(at * 1000); return Number.isNaN(date.getTime()) ? '待核对' : `${date.getFullYear()}/${date.getMonth() + 1}/${date.getDate()}` }
export function membershipState(value: Record<string, any>) { return value.active === true ? 'active' : entityLevel(value.level_code) ? 'expired' : 'locked' }
export function memberStatus(value: unknown) { return ({ pending: '待负责人确认', active: '已加入', rejected: '申请未通过', removed: '已退出', suspended: '账号已暂停' } as Record<string, string>)[String(value)] || '状态待核对' }
/** Validate returned monetary parts for display, never calculate or grant rights. */
export function membershipCosts(value: Record<string, any> = {}) {
    const amount = (key: string) => { const text = String(value[key] ?? ''); if (!/^\d+\.\d{2}$/.test(text)) return null; const cents = Number(text.replace('.', '')); return Number.isSafeInteger(cents) && cents >= 0 ? cents : null }
    const keys = ['deposit_required','deposit_balance','deposit_amount','service_full_amount','service_credit_amount','service_amount','experience_unit_price','experience_quantity','experience_amount','payable_amount']
    const entries = Object.fromEntries(keys.map(key => [key, amount(key)]))
    if (keys.some(key => entries[key] === null) || typeof value.buy_experience !== 'boolean' || typeof value.experience_purchase_enabled !== 'boolean') return null
    if (!experienceGateEnabled(value) && (value.buy_experience || value.experience_purchase_enabled || entries.experience_unit_price! !== 0 || entries.experience_quantity! !== 0 || entries.experience_amount! !== 0)) return null
    if (entries.service_full_amount! - entries.service_credit_amount! !== entries.service_amount! || entries.deposit_amount! + entries.service_amount! + entries.experience_amount! !== entries.payable_amount! || entries.payable_amount! <= 0) return null
    if (!value.buy_experience && (entries.experience_quantity! !== 0 || entries.experience_amount! !== 0)) return null
    if (value.buy_experience && (!value.experience_purchase_enabled || entries.experience_unit_price! <= 0 || (entries.experience_quantity! === 0 ? entries.experience_amount! !== 0 : entries.experience_amount! <= 0))) return null
    return { deposit:value.deposit_amount, service:value.service_amount, experience:value.experience_amount, total:value.payable_amount, quantity:value.experience_quantity, bought:value.buy_experience }
}
export function quoteMatches(quote: Record<string, any>, merchant: number, level: string, cycle: string, buyExperience = false, now = Date.now()) {
    if (!quote || quote.purpose !== 'membership' || Number(quote.merchant_id) !== merchant || quote.level_code !== level || quote.cycle !== cycle) return false
    if (quote.can_purchase !== true) return false
    if (!membershipCosts(quote) || quote.buy_experience !== buyExperience) return false
    return !!quote.quote_token && Number(quote.expires_at) * 1000 > now
}
