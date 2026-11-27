import type { LocationQuery, LocationQueryRaw } from 'vue-router'
export const leadPagePaths = {
    workbench: '/site/hsx_lead/workbench',
    leads: '/site/hsx_lead/cooperation/leads',
    partners: '/site/hsx_lead/cooperation/partners',
    entities: '/site/hsx_lead/cooperation/entities',
    reviews: '/site/hsx_lead/cooperation/reviews',
    orders: '/site/hsx_lead/cooperation/orders',
    settings: '/site/hsx_lead/cooperation/settings',
    merchants: '/site/hsx_lead/resource/merchants',
    inventory: '/site/hsx_lead/resource/inventory'
} as const
export function leadPageLocation(page: keyof typeof leadPagePaths, query: LocationQueryRaw = {}) {
    return { path: leadPagePaths[page], query }
}
/** Framework API rules and authorized menu routes are distinct; either may carry the page key. */
export function hasLeadPagePermission(user: { rules?: any[]; routers?: any[] }, key: string): boolean {
    const contains = (routes: any[]): boolean =>
        (routes || []).some((route) => route.name === key || contains(route.children || []))
    return (user.rules || []).includes(key) || contains(user.routers || [])
}
export function leadRouteFilters(query: LocationQuery, keys: string[]) {
    return Object.fromEntries(keys.map((key) => [key, typeof query[key] === 'string' ? query[key] : '']))
}
