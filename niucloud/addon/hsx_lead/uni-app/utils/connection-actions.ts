export type ConnectionSyncAttention = {
    code: string
    message: string
    action: 'reauthorize' | 'retry' | 'contact_support'
    action_label: string
}

/** Present public server diagnostics, never infer an authorization failure from a network error. */
export function connectionSyncAttention(
    connection: Record<string, any> = {},
    syncStatus = String(connection.last_sync_status || '')
): ConnectionSyncAttention | null {
    if (connection.channel_enabled === false || ['pending', 'running'].includes(syncStatus)) return null
    const attention = connection.sync_attention
    if (
        attention &&
        ['reauthorize', 'retry', 'contact_support'].includes(attention.action) &&
        typeof attention.message === 'string' &&
        attention.message.trim()
    ) {
        return {
            code: String(attention.code || ''),
            message: attention.message.trim(),
            action: attention.action,
            action_label: String(attention.action_label || '')
        }
    }
    return connection.authorization_attention === true
        ? {
              code: 'auth_required',
              message: '账号授权已失效，请重新登录后继续同步；原库存记录保留。',
              action: 'reauthorize',
              action_label: '重新登录'
          }
        : null
}

/** One primary next action per stage. This module has no API or storage side effects. */
export function connectionActionState(input: {
    connected: boolean
    editing: boolean
    hasPreview: boolean
    setupStage: string
    channelClosed: boolean
    enabled: boolean
    syncStatus: string
    attention?: ConnectionSyncAttention | null
}) {
    if (input.hasPreview)
        return {
            step: 'preview',
            primary: 'activate',
            primaryLabel: '确认并启用同步',
            secondaryLabel: '返回修改',
            subtitle: '第 2 步 · 核对来源门店',
            showSecondary: true
        }
    if (input.editing || !input.connected)
        return {
            step: 'authorize',
            primary: 'verify',
            primaryLabel: '验证账号',
            secondaryLabel: '取消',
            subtitle: '第 1 步 · 填写账号',
            showSecondary: true
        }
    if (input.channelClosed)
        return {
            step: 'manage',
            primary: 'return',
            primaryLabel: '返回我的门店',
            secondaryLabel: '',
            subtitle: '渠道暂停 · 原资料保留',
            showSecondary: false
        }
    const attention = ['pending', 'running'].includes(input.syncStatus) ? null : input.attention
    if (attention?.action === 'reauthorize')
        return {
            step: 'manage',
            primary: 'reauthorize',
            primaryLabel: '重新登录',
            secondaryLabel: '返回',
            subtitle: '授权需要更新',
            showSecondary: true
        }
    if (attention?.action === 'contact_support')
        return {
            step: 'manage',
            primary: 'return',
            primaryLabel: '返回我的门店',
            secondaryLabel: '',
            subtitle: '同步需要平台协助',
            showSecondary: false
        }
    if (attention?.action === 'retry')
        return {
            step: 'manage',
            primary: 'sync',
            primaryLabel: '重新同步',
            secondaryLabel: '返回',
            subtitle: '本次同步未完成',
            showSecondary: true
        }
    if (input.setupStage)
        return {
            step: 'resume',
            primary: 'resume',
            primaryLabel: input.setupStage === 'enable_sync' ? '继续启用自动同步' : '继续首次同步',
            secondaryLabel: '稍后处理',
            subtitle: '授权已保存 · 同步设置尚未完成',
            showSecondary: true
        }
    if (input.enabled && input.syncStatus === 'completed')
        return {
            step: 'manage',
            primary: 'return',
            primaryLabel: '返回我的门店',
            secondaryLabel: '',
            subtitle: '库存同步完成',
            showSecondary: false
        }
    return {
        step: 'manage',
        primary: input.enabled ? 'sync' : 'enable',
        primaryLabel: input.enabled
            ? ['running', 'pending'].includes(input.syncStatus)
                ? '继续本次同步'
                : '同步库存'
            : '启用自动同步',
        secondaryLabel: '返回',
        subtitle: input.enabled ? '连接管理' : '自动同步已暂停',
        showSecondary: true
    }
}
