type RenewalData = Record<string, any>

/** Current sync diagnostics take priority over the connection's renewal capability. */
export function syncAttentionFor(connection: RenewalData = {}) {
    const status = String(connection.last_sync_status || connection.sync_progress?.status || '')
    if (connection.channel_enabled === false || ['pending', 'running'].includes(status)) return null
    const attention = connection.sync_attention
    if (attention && ['reauthorize', 'retry', 'contact_support'].includes(attention.action) && typeof attention.message === 'string' && attention.message.trim()) {
        return { code: String(attention.code || ''), message: attention.message.trim(), action: String(attention.action), action_label: String(attention.action_label || '') }
    }
    return connection.authorization_attention === true ? { code: 'auth_required', message: '账号授权已失效，请重新登录后继续同步；原库存记录保留。', action: 'reauthorize', action_label: '重新登录' } : null
}

/** Public capability metadata only; never inspect or render passwords or runtime tokens. */
export function authRenewalLabel(value: RenewalData = {}) {
    if (value.automatic === true && value.ready === true) return '自动续登已配置'
    if (value.status === 'manual_token') return '仅 Token，不能续登'
    if (value.status === 'interactive_required') return '到期需登录验证'
    if (value.status === 'credential_error') return '授权需重新配置'
    if (value.status === 'credentials_required' || value.automatic === true) return '账号密码待补齐'
    return '续登能力待确认'
}

/** Describe the selected authorization mode before it has been verified and activated. */
export function authRenewalPolicy(schema: RenewalData = {}, mode = '') {
    const selected = Array.isArray(schema.auth_modes) ? schema.auth_modes.find((item: RenewalData) => item.key === mode) : undefined
    const renewal = selected?.renewal || schema.renewal || {}
    if (renewal.automatic === true) return {
        title: '支持自动续登', type: 'info' as const,
        message: '验证并启用后，账号密码将加密保存；授权失效时自动重新登录，再继续同步。密码变更或登录受限时会明确提示，不会无限重试。'
    }
    if (mode === 'token' || renewal.mode === 'manual_token') return {
        title: '仅 Token 不能自动续登', type: 'warning' as const,
        message: '只保存 Token，无法在失效后获取新授权。需要持续自动同步时，请使用该 ERP 支持的账号密码登录方式。'
    }
    if (schema.interactive_auth?.type || renewal.mode === 'interactive_login') return {
        title: '会话到期后需重新验证', type: 'warning' as const,
        message: '本次验证码登录不等于后台自动续登；会话失效后需重新完成登录验证，旧完整快照会保留供核对。'
    }
    return { title: '自动续登能力待确认', type: 'warning' as const, message: renewal.description || '请先核对该 ERP 的授权方式，不能仅凭自动同步开关判断能够自动续登。' }
}
