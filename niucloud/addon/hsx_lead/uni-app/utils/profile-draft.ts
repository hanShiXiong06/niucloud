/** Compact, actor-scoped editing hints. Never persist credentials or private form answers. */
export const PROFILE_DRAFT_FIELDS = ['name', 'contact_name', 'mobile', 'province_id', 'city_id', 'district_id', 'address', 'provider', 'storefront_image', 'inviter_member_no'] as const
export const PROFILE_DRAFT_TTL = 24 * 60 * 60 * 1000
export const PROFILE_DRAFT_DELAY = 400
type Row = Record<string, any>
const record = (value: unknown): value is Row => !!value && typeof value === 'object' && !Array.isArray(value)
const safeKey = (value: unknown) => typeof value === 'string' && /^[a-z][a-z0-9_]{0,59}$/.test(value)
const limits: Record<string, number> = { name: 120, contact_name: 60, mobile: 20, address: 500, provider: 60, storefront_image: 2048, inviter_member_no: 255 }

export function profileDraftKey(scope: string): string {
    const parts = String(scope).split(':')
    if (parts.length !== 3 || !parts.every((part, index) => /^(0|[1-9]\d*)$/.test(part) && Number.isSafeInteger(Number(part)) && (index === 2 || Number(part) > 0))) return ''
    return 'hsx_lead_profile_draft:v2:' + scope
}

function formFields(raw: unknown): Row {
    const output: Row = {}
    if (!record(raw)) return output
    for (const key of PROFILE_DRAFT_FIELDS) {
        const value = raw[key]
        if (key.endsWith('_id')) {
            if (Number.isSafeInteger(Number(value)) && Number(value) >= 0) output[key] = Number(value)
        } else if (typeof value === 'string' && value.length <= limits[key] && !value.startsWith('data:')) output[key] = value
    }
    return output
}

function businessKeys(value: unknown): string[] {
    return Array.isArray(value) ? [...new Set(value.filter(safeKey))].slice(0, 40) : []
}

function choiceIds(raw: unknown, legacy = false): Record<string, string[]> {
    const output: Record<string, string[]> = {}
    if (!record(raw)) return output
    for (const key of Object.keys(raw).filter(safeKey).slice(0, 40)) {
        if (!Array.isArray(raw[key]) || raw[key].length > 5000) continue
        const ids: string[] = [], seen = new Set<string>()
        for (const option of raw[key]) {
            const value = legacy ? record(option) ? option.id : undefined : option
            const id = typeof value === 'string' ? value.trim() : typeof value === 'number' && Number.isSafeInteger(value) ? String(value) : ''
            if (!id || id.length > 255 || seen.has(id)) continue
            seen.add(id); ids.push(id)
        }
        output[key] = ids
    }
    return output
}

export function createProfileDraft(scope: string, form: Row, selected: string[], choices: Row, now = Date.now()): Row | null {
    if (!profileDraftKey(scope)) return null
    return { version: 2, scope, saved_at: now, expires_at: now + PROFILE_DRAFT_TTL, form: formFields(form), selectedBusinesses: businessKeys(selected), capabilityIds: choiceIds(choices, true) }
}

export function readProfileDraft(raw: unknown, scope: string, now = Date.now()): Row | null {
    if (!profileDraftKey(scope) || !record(raw) || raw.version !== 2 || raw.scope !== scope || !Number.isFinite(raw.saved_at) || !Number.isFinite(raw.expires_at) || raw.saved_at > now + 60000 || raw.expires_at <= now || raw.expires_at - raw.saved_at !== PROFILE_DRAFT_TTL) return null
    return { form: formFields(raw.form), selectedBusinesses: businessKeys(raw.selectedBusinesses), capabilityIds: choiceIds(raw.capabilityIds) }
}

/** One read of the current actor's old draft; do not restore private or other-shop data. */
export function readLegacyProfileDraft(raw: unknown, scope: string): Row | null {
    if (!profileDraftKey(scope) || !record(raw) || !record(raw.form)) return null
    const merchant = Number(scope.split(':')[2]), oldMerchant = Number(raw.form.id || 0)
    if (oldMerchant !== merchant) return null
    return { form: formFields(raw.form), selectedBusinesses: businessKeys(raw.selectedBusinesses), capabilityIds: choiceIds(raw.capabilityChoices, true) }
}

/** Names are read from current server data or fetched by the picker; cache stores IDs only. */
export function restoreProfileChoices(ids: unknown, current: Row[]): Row[] {
    if (!Array.isArray(ids)) return []
    const canonical = new Map(current.map(item => [String(item.id), item]))
    return ids.map(id => canonical.get(String(id)) || { id: String(id) })
}
