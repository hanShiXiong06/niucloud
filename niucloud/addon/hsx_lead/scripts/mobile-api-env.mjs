/** Build-time syntax checks only. No request, environment mutation or domain preset. */
export function validateMobileApiBase(platform, value) {
    const baseUrl = typeof value === 'string' ? value.trim() : ''
    if (platform !== 'mp-weixin') return { baseUrl, absolute: /^https?:\/\//i.test(baseUrl), local: false, warning: '' }
    const guidance = '小程序 API 地址必须是完整的 http(s) URL，不能使用 /api。请用 VITE_APP_BASE_URL 覆写后重新构建；H5 可以继续使用相对地址。'
    if (!/^https?:\/\//i.test(baseUrl) || /[\s\\<>"'`]/.test(baseUrl)) throw new Error(guidance)
    let url
    try { url = new URL(baseUrl) } catch { throw new Error(guidance) }
    if (!['http:', 'https:'].includes(url.protocol) || !url.hostname || url.username || url.password || url.search || url.hash) throw new Error('小程序 API 根地址不能包含账号密码、查询参数或锚点；请检查 VITE_APP_BASE_URL。')
    const local = ['localhost', '127.0.0.1', '[::1]'].includes(url.hostname.toLowerCase())
    const warning = local ? '当前 API 使用本机地址，仅适用于本机微信开发者工具；真机或上传版本须改为可访问的 HTTPS API，并配置微信 request 合法域名。' : url.protocol === 'http:' ? '当前 API 使用 HTTP；正式小程序通常需要 HTTPS 和微信 request 合法域名，请勿将本次调试包当作已满足发布条件。' : ''
    return { baseUrl: url.href, absolute: true, local, warning }
}
