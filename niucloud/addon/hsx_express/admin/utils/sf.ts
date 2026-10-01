export type SfScene = 'waybill' | 'pickup'
export const sfDefaults = () => ({ enabled: 0, environment: 'sandbox', client_code: '', check_word: '', monthly_card: '', product_code: '2', pay_method: 1, template_code: '', use_ack: 0, callback_base_url: '' })

export function sfForEditing(data: Record<string, any>) {
    const result: Record<string, any> = sfDefaults()
    for (const key of Object.keys(result)) if (data[key] !== undefined) result[key] = data[key]
    result.check_word = ''
    result.enabled = Number(result.enabled) === 1 ? 1 : 0
    result.use_ack = Number(result.use_ack) === 1 ? 1 : 0
    result.pay_method = Number(result.pay_method)
    result.product_code = String(result.product_code)
    return result
}

export function sfIdentityChanged(form: Record<string, any>, saved: Record<string, any>) {
    return form.environment !== saved.environment || String(form.client_code).trim() !== String(saved.client_code || '').trim()
}

export function sfSavePayload(form: Record<string, any>, clearSecret: boolean) {
    const payload: Record<string, any> = {}
    for (const key of Object.keys(sfDefaults())) payload[key] = form[key]
    // 输入新密钥代表替换；只有未输入新值时才执行明确清空。
    if (clearSecret && !String(form.check_word || '').trim()) payload.clear_secrets = ['check_word']
    return payload
}

export function isSfTask(task: Record<string, any>) {
    return task.provider_key === 'hsx_express_sf_direct' || task.provider_code === 'hsx_express_sf_direct'
}

export function isSandboxTask(task: Record<string, any>) {
    return task.environment === 'sandbox' || task.is_sandbox === true || Number(task.is_sandbox) === 1
}

export function canDownloadSfPdf(task: Record<string, any>) {
    return isSfTask(task) && task.print_type === 'PDF' && task.can_download === true && task.label_state === 'ready' && !['cancelled', 'cancelling', 'cancel_unknown'].includes(task.state)
}

export async function validatedPdf(response: any): Promise<Blob> {
    const blob = response?.data instanceof Blob ? response.data : response
    if (!(blob instanceof Blob)) throw new Error('面单接口未返回有效 PDF，请刷新原任务后重试')
    if (await blob.slice(0, 5).text() !== '%PDF-') {
        let message = '未取得 PDF，可能已过期或没有下载权限，请刷新原单结果'
        try { const data = JSON.parse(await blob.slice(0, 8192).text()); if (typeof data.msg === 'string') message = data.msg } catch { /* 不把 HTML 错误页下载为面单。 */ }
        throw new Error(message)
    }
    return blob
}
