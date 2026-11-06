/** A relative home-area preset must never rewrite an explicitly registered foreign area. */
export function serviceScopeFor(areas: Record<string, any>[], home: Record<string, any>): string {
    if (!areas?.length) return 'default'
    if (areas.length !== 1) return 'custom'
    const area = areas[0]
    if (Number(area.province_id) !== Number(home.province_id)) return 'custom'
    if (!Number(area.city_id) && !Number(area.district_id)) return 'province'
    if (Number(area.city_id) !== Number(home.city_id)) return 'custom'
    if (!Number(area.district_id)) return 'city'
    return Number(area.district_id) === Number(home.district_id) ? 'district' : 'custom'
}
