/** Display text is site-owned. Provider keys stay unchanged in every request. */
export function erpProviderDisplayName(key: unknown, providers: unknown, fallbackName: unknown = ''): string {
    const item = Array.isArray(providers) ? providers.find(provider => provider && provider.key === key) : null
    for (const value of [item?.display_name, item?.name, fallbackName]) {
        if (typeof value === 'string' && value.trim()) return value.trim()
    }
    // A missing/closed definition must not turn the technical key into a label.
    return 'ERP平台'
}
