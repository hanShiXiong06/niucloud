/** One primary next action per stage. This module has no API or storage side effects. */
export function connectionActionState(input: {
    connected: boolean; editing: boolean; hasPreview: boolean; setupStage: string;
    channelClosed: boolean; enabled: boolean; syncStatus: string
}) {
    if (input.hasPreview) return { step: 'preview', primary: 'activate', primaryLabel: '确认并启用同步', secondaryLabel: '返回修改', subtitle: '第 2 步 · 核对来源门店', showSecondary: true }
    if (input.editing || !input.connected) return { step: 'authorize', primary: 'verify', primaryLabel: '验证账号', secondaryLabel: '取消', subtitle: '第 1 步 · 填写账号', showSecondary: true }
    if (input.setupStage) return { step: 'resume', primary: 'resume', primaryLabel: input.setupStage === 'enable_sync' ? '继续启用自动同步' : '继续首次同步', secondaryLabel: '稍后处理', subtitle: '授权已保存 · 同步设置尚未完成', showSecondary: true }
    if (input.channelClosed || input.enabled && input.syncStatus === 'completed') return { step: 'manage', primary: 'return', primaryLabel: '返回我的门店', secondaryLabel: '', subtitle: input.channelClosed ? '渠道暂停 · 原资料保留' : '库存同步完成', showSecondary: false }
    return { step: 'manage', primary: input.enabled ? 'sync' : 'enable', primaryLabel: input.enabled ? ['running', 'pending'].includes(input.syncStatus) ? '继续本次同步' : '同步库存' : '启用自动同步', secondaryLabel: '返回', subtitle: input.enabled ? '连接管理' : '自动同步已暂停', showSecondary: true }
}
