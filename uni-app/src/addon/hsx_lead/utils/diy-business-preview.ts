export const BUSINESS_PREVIEW_REQUEST = 'hsx_lead:diy-business-preview:request'
export const BUSINESS_PREVIEW_RESULT = 'hsx_lead:diy-business-preview:result'
export interface PreviewBusiness { key: string; name: string }
export interface BusinessPreview { state: 'ready' | 'denied' | 'error'; items: PreviewBusiness[]; message: string }

export function previewParentOrigin(referrer: string): string {
    try { const url = new URL(referrer); return ['http:', 'https:'].includes(url.protocol) ? url.origin : '' } catch { return '' }
}
export function readBusinessPreview(value: unknown): BusinessPreview | null {
    try {
        const data = typeof value === 'string' && value.length < 20000 ? JSON.parse(value) : value
        if (!data || typeof data !== 'object' || (data as any).type !== BUSINESS_PREVIEW_RESULT) return null
        const row = data as any
        if (!['ready', 'denied', 'error'].includes(row.state) || !Array.isArray(row.items) || row.items.length > 30 || typeof row.message !== 'string' || row.message.length > 300) return null
        const seen = new Set<string>(), items: PreviewBusiness[] = []
        for (const item of row.items) {
            if (!item || typeof item !== 'object' || Object.keys(item).some(key => !['key', 'name'].includes(key)) || typeof item.key !== 'string' || !/^[a-z][a-z0-9_]{0,15}$/.test(item.key) || seen.has(item.key) || typeof item.name !== 'string' || !item.name.trim() || item.name.length > 100 || /[<>\x00-\x1f]/.test(item.name)) return null
            seen.add(item.key); items.push({ key: item.key, name: item.name })
        }
        return { state: row.state, items: row.state === 'ready' ? items : [], message: row.message }
    } catch { return null }
}
