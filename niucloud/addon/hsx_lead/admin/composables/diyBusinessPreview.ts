export const BUSINESS_PREVIEW_REQUEST = 'hsx_lead:diy-business-preview:request'
export const BUSINESS_PREVIEW_RESULT = 'hsx_lead:diy-business-preview:result'
export type PreviewBusiness = { key: string; name: string }
export type BusinessPreview = {
    type: typeof BUSINESS_PREVIEW_RESULT
    state: 'ready' | 'denied' | 'error'
    items: PreviewBusiness[]
    message: string
}

export function enabledPreviewBusinesses(config: Record<string, any>): PreviewBusiness[] {
    if (!Array.isArray(config?.businesses)) throw new Error('未返回业务定义')
    return config.businesses
        .filter((row: any) => row && [true, 1, '1'].includes(row.enabled))
        .map((row: any) => {
            if (
                typeof row.key !== 'string' ||
                !/^[a-z][a-z0-9_]{0,15}$/.test(row.key) ||
                typeof row.name !== 'string' ||
                !row.name.trim() ||
                row.name.length > 100 ||
                /[<>\x00-\x1f]/.test(row.name)
            )
                throw new Error('业务预览名称格式不完整')
            return { key: row.key, name: row.name.trim() }
        })
        .slice(0, 30)
}
export function previewOrigin(value: string, base?: string): string {
    try {
        const url = new URL(value, base)
        return ['http:', 'https:'].includes(url.protocol) ? url.origin : ''
    } catch {
        return ''
    }
}
export function isBusinessPreviewRequest(value: unknown): boolean {
    try {
        const data = typeof value === 'string' && value.length < 1000 ? JSON.parse(value) : value
        return !!data && typeof data === 'object' && (data as any).type === BUSINESS_PREVIEW_REQUEST
    } catch {
        return false
    }
}
export function businessPreview(
    state: BusinessPreview['state'],
    items: PreviewBusiness[] = [],
    message = ''
): BusinessPreview {
    return { type: BUSINESS_PREVIEW_RESULT, state, items: items.map(({ key, name }) => ({ key, name })), message }
}
