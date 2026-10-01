/** 只接受页面路径或网页地址，不把通知链接作为代码执行。参数保持编码交给框架导航。 */
export function noticeTarget(value: string, returnUrl = ''): { url: string; param?: Record<string, string> } | null {
    const link = String(value || '').trim()
    if (!link || link.length > 1000 || /[\s\\<>"\u0000-\u001f]/.test(link)) return null
    if (/^https?:\/\/[^/@?#]+(?:[/?#]|$)/i.test(link)) return { url: link }
    const split = link.indexOf('?')
    const url = split < 0 ? link : link.slice(0, split)
    if (!/^\/(addon|app)\/[a-zA-Z0-9_/-]+$/.test(url) || url.includes('//')) return null
    const param: Record<string, string> = {}
    if (split >= 0) {
        for (const part of link.slice(split + 1).split('&')) {
            const equal = part.indexOf('=')
            const key = equal < 0 ? part : part.slice(0, equal)
            if (!/^[a-zA-Z0-9_]+$/.test(key) || ['__proto__', 'constructor', 'prototype'].includes(key)) continue
            param[key] = equal < 0 ? '' : part.slice(equal + 1)
        }
    }
    if (returnUrl && !param.return_url) {
        param.return_url = encodeURIComponent(returnUrl)
        param.return_label = encodeURIComponent('返回下单')
    }
    return { url, param }
}
