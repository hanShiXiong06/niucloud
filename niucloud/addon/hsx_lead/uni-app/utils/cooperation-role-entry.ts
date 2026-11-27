/** Presentation projection only; routes and business permissions never come from DIY JSON. */
type RoleAppearance = Record<string, any>
const frameKeys = [
    'componentStartBgColor',
    'componentEndBgColor',
    'componentGradientAngle',
    'componentBgUrl',
    'componentBgAlpha',
    'topRounded',
    'bottomRounded'
]

export function cooperationRoleAppearance(value: RoleAppearance = {}, role = 'stores'): RoleAppearance {
    const customer = role === 'customers'
    const result: RoleAppearance = { ...value }
    // The combined block already paints its outer frame. Per-card overrides
    // below may add a second, independent frame, but never clone the parent's.
    for (const key of frameKeys) delete result[key]
    Object.assign(result, {
        title: customer ? (value.customerTitle ?? '找客户') : (value.storeTitle ?? '找门店'),
        subtitle: customer
            ? (value.customerSubtitle ?? '查看交付给我的客户')
            : (value.storeSubtitle ?? '发布客户需求，找门店交付'),
        buttonText: customer ? (value.customerActionText ?? '查看客户') : (value.storeActionText ?? '去找门店'),
        image: customer ? (value.customerImage ?? '') : (value.storeImage ?? ''),
        buttonStyle: customer ? 'link' : 'solid'
    })
    const override = customer ? value.customersAppearance : value.storesAppearance
    if (override && typeof override === 'object' && !Array.isArray(override)) Object.assign(result, override)
    return result
}
