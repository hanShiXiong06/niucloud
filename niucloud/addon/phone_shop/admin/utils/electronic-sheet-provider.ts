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

export function providerTaskKey(task: Record<string, any>, fallback = ''): string {
    return task.provider_key || task.provider_code || fallback
}
/** 只使用商家已选且已启用的默认通道，不擅自更改站点的服务商配置。 */
export function defaultDeliveryWay(provider: Record<string, any> | null, mode: string): string {
    return mode === 'add' && provider?.default_for_delivery === true ? 'plugin_waybill' : 'manual_write'
}
export function isSfWaybillTask(task: Record<string, any>): boolean {
    return providerTaskKey(task) === 'hsx_express_sf_direct'
}
export function providerTaskCanConfirm(task: Record<string, any>): boolean {
    if (isSfWaybillTask(task)) return task.environment === 'production' && task.can_confirm_delivery === true
    return task.can_confirm_delivery !== false
}
export function providerPdfPath(task: Record<string, any>): string {
    const id = Number(task.task_id || task.id)
    const value = typeof task.pdf_download_path === 'string' ? task.pdf_download_path : ''
    if (!Number.isSafeInteger(id) || id <= 0 || !new RegExp(`^[a-z][a-z0-9_]*\\/tasks\\/${id}\\/pdf$`).test(value)) return ''
    return value
}
export function canDownloadProviderPdf(task: Record<string, any>): boolean {
    return task.print_type === 'PDF' && task.can_download === true && task.label_state === 'ready' && !!providerPdfPath(task)
        && !['cancelled', 'cancelling', 'cancel_unknown'].includes(task.state)
}
export async function providerPdfBlob(response: any): Promise<Blob> {
    const blob = response?.data instanceof Blob ? response.data : response
    if (!(blob instanceof Blob)) throw new Error('接口未返回有效面单，请刷新原任务后重试')
    if (await blob.slice(0, 5).text() !== '%PDF-') {
        let message = '面单不可下载或已过期，请刷新原单并重新获取 PDF'
        try { const data = JSON.parse(await blob.slice(0, 8192).text()); if (typeof data.msg === 'string') message = data.msg } catch { /* 不下载错误页。 */ }
        throw new Error(message)
    }
    return blob
}
export const SF_WAYBILL_PDF_CONFIRM = '仅重新获取原顺丰运单的 PDF，不重新申请运单，不自动打印或确认商城发货。请先检查原面单是否已经使用，避免重复打印贴单。'
