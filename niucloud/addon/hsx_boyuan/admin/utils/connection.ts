import type { ConnectionState, ConnectionProfile, Environment } from '../api/connection'

export const routes = {
    home: 'hsx_boyuan_workbench_page', catalog: 'hsx_boyuan_catalog_page', connection: 'hsx_boyuan_connection_page', opening: 'hsx_boyuan_opening_page',
    purchase: 'hsx_erp_purchase', sale: 'hsx_erp_sale', records: 'hsx_boyuan_wholesale_page'
}
export const envName = (s: string) => s === 'production' ? '正式环境' : s === 'test' ? '测试环境（UAT）' : '尚未接入开放平台'
export const normalizeUrl = (s: string) => s.trim().replace(/\/+$/, '').replace(/\/gateway\.do$/, '')
export const complete = (p?: ConnectionProfile) => !!(p?.protocol === 'openapi_v153' && p.connection_key && p.endpoint && p.account_id && p.app_id && p.app_secret_configured)
export function decodeState(res: any): ConnectionState {
    const s=res?.data
    if (!s || !['mock','test','production'].includes(s.mode) || !Number.isInteger(s.revision) ||
        !['test','production'].every(k => typeof s.profiles?.[k]?.suggested_url === 'string') ||
        typeof s.can_edit !== 'boolean' || typeof s.can_probe !== 'boolean') throw new Error('未能读取完整连接状态，请重新载入。')
    return s
}
export function connectionStep(s: ConnectionState) {
    const p=s.mode === 'mock' ? undefined : s.profiles[s.mode]
    if (p && p.protocol !== 'openapi_v153') return 'legacy'
    return !complete(p) ? 'setup' : p!.probe_at > 0 ? 'verified' : 'verify'
}
export function validateConnection(d: { endpoint: string; account_id: string; app_id: string; app_secret: string }, mode: Environment, needsSecret: boolean) {
    const errors: Record<string,string> = {}
    const host = mode === 'test' ? 'open-api-uat.139erp.com' : 'open-api.139erp.com'
    try {
        const u=new URL(normalizeUrl(d.endpoint))
        if (u.protocol !== 'https:' || u.host !== host || u.username || u.password || u.search || u.hash || !/^\/(?:[\w-]+\/)*[\w-]*$/.test(u.pathname)) throw Error()
    } catch { errors.endpoint='请填写本环境的博远 HTTPS 地址，可点「使用默认地址」。' }
    if (!d.account_id.trim() || /\s/.test(d.account_id.trim()) || d.account_id.trim().length > 128) errors.account_id='请填写博远提供的公司代码，不含空格。'
    if (!/^\d{1,32}$/.test(d.app_id.trim())) errors.app_id='请填写博远提供的数字 AppID（最多 32 位）。'
    if ((needsSecret || d.app_secret) && !/^[a-zA-Z0-9]{32}$/.test(d.app_secret)) errors.app_secret='请填写本账套配套的 32 位 AppSecret。'
    return errors
}
export function reason(e: unknown, fallback: string) {
    const item=e as any
    return (typeof e === 'string' ? e : item?.response?.data?.msg || item?.msg || item?.message || fallback).replace(/^\[[A-Z_]+\]\s*/, '')
}
