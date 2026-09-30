/** Only open label files returned on the provider's documented domains, including HTTP short links. */
export function trustedWaybillLabels(value: unknown): string[] {
    const candidates = Array.isArray(value) ? value : String(value || '').split(',')
    const labels: string[] = []
    for (const candidate of candidates) {
        if (typeof candidate !== 'string' || candidate.trim().length > 2048) continue
        try {
            const url = new URL(candidate.trim())
            const host = url.hostname.toLowerCase()
            const trusted = host === 'kuaidi100.com' || host.endsWith('.kuaidi100.com') || host === 'ckd.im' || host.endsWith('.ckd.im')
            if (!trusted || !['http:', 'https:'].includes(url.protocol) || url.username || url.password) continue
            labels.push(url.href)
        } catch { /* A malformed link is not an actionable label. */ }
    }
    return [...new Set(labels)]
}

/** Distinguish an old mall API from a current API with no authorized extension. */
export function electronicSheetProviderState(data: Record<string, any>) {
    const registryAvailable = Array.isArray(data.providers) && data.providers.some((item: any) => item?.key === 'kdbird')
    const providers = registryAvailable
        ? data.providers.filter((item: any) => item && typeof item.key === 'string' && typeof item.label === 'string')
        : [{ key: 'kdbird', label: '快递鸟（原有方式）', external: false }]
    return { providers, registryAvailable, hasExternal: providers.some((item: any) => item.external === true) }
}

export const WAYBILL_CREATE_CONFIRM = '将使用本站物流账号为选中的商品申请运单，可能消耗接口额度并按开通套餐计费；云打印方案还会发送打印任务。请核对收发地址、商品和重量。取号或打印不会自动确认商城发货，实际交给快递员后仍需确认发货。'
export const WAYBILL_REPRINT_CONFIRM = '请先核实原面单是否已经出纸。原单补打可能再次出纸，但不会重新申请运单；电脑打印方案会重新获取面单文件。补打有时效与次数限制，以服务商返回结果为准。'
