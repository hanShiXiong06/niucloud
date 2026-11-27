/** Presentation only. A badge never determines qualification, ranking or fees. */
export type MembershipBadgeLevel = number
export type MembershipBadgeState = 'active' | 'expired' | 'locked'
export type MembershipBadgeSize = 'small' | 'default' | 'large'
export type MembershipBadgeVariant = 'emblem' | 'inline'

/** Deliberately does not infer V status from former S/A/B, reliability or paid tiers. */
export function membershipBadgeLevel(value: unknown): MembershipBadgeLevel {
    if (typeof value === 'number' && Number.isSafeInteger(value) && value >= 0 && value <= 99999) return value
    return 0
}

export function membershipBadgeAppearance(
    level: unknown,
    state: unknown = 'locked',
    size: unknown = 'default',
    variant: unknown = 'emblem',
    animated: unknown = false,
    label: unknown = ''
) {
    const rank = membershipBadgeLevel(level)
    const status: MembershipBadgeState = state === 'active' || state === 'expired' ? state : 'locked'
    const scale: MembershipBadgeSize = size === 'small' || size === 'large' ? size : 'default'
    const presentation: MembershipBadgeVariant = variant === 'inline' ? variant : 'emblem'
    const name = rank ? String(label || `等级${rank}`).slice(0, 40) : '普通会员'
    const stateLabel = !rank || status === 'active' ? '' : status === 'expired' ? '已到期' : '未开通'
    return {
        level: rank,
        skin: Math.min(rank, 4),
        state: status,
        size: scale,
        variant: presentation,
        code: rank ? `V${rank}` : '普',
        name,
        stateLabel,
        ariaLabel: stateLabel ? `${name}，${stateLabel}` : name,
        glowing: rank > 0 && status === 'active',
        animated: rank >= 3 && status === 'active' && animated === true
    }
}
