export const TASK_PAGE = '/addon/hsx_marketing/pages/index'

const decode = (value: unknown): string => {
    if (typeof value !== 'string') return ''
    try { return decodeURIComponent(value).trim() } catch { return value.trim() }
}

/** 返回业务只允许本站页面，不接受外部网址或可执行协议。 */
export function parseTaskEntry(option: Record<string, unknown> = {}) {
    const id = Number(option.campaign_id || 0)
    const raw = typeof option.return_url === 'string' ? option.return_url.trim() : ''
    const returnUrl = raw.startsWith('/') ? raw : decode(raw)
    const [path] = returnUrl.split('?')
    const valid = returnUrl.length <= 1000 && !/[\s\\<>"#\u0000-\u001f]/.test(returnUrl)
        && /^\/(addon|app)\/[a-zA-Z0-9_/-]+$/.test(path) && !path.includes('//') && path !== TASK_PAGE
    return {
        campaignId: Number.isSafeInteger(id) && id > 0 ? id : 0,
        returnUrl: valid ? returnUrl : '',
        returnLabel: decode(option.return_label).slice(0, 12) || '返回继续办理'
    }
}

export function businessTarget(returnUrl: string) {
    const entry = parseTaskEntry({ return_url: returnUrl })
    if (!entry.returnUrl) return null
    const split = entry.returnUrl.indexOf('?')
    const url = split < 0 ? entry.returnUrl : entry.returnUrl.slice(0, split)
    const param: Record<string, string> = {}
    if (split >= 0) for (const pair of entry.returnUrl.slice(split + 1).split('&')) {
        const equal = pair.indexOf('=')
        const key = equal < 0 ? pair : pair.slice(0, equal)
        if (/^[a-zA-Z0-9_]+$/.test(key) && !['__proto__', 'constructor', 'prototype'].includes(key)) param[key] = equal < 0 ? '' : pair.slice(equal + 1)
    }
    return { url, param }
}
