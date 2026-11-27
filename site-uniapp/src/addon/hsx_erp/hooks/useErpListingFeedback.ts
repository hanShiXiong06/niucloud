export type ErpListingFeedback = {
    level: 'success' | 'warning' | 'info'
    message: string
    detail?: string
}

/** 移动端统一解释保存、任务流转及商城发布结果。 */
export function erpListingFeedback(publish: any, savedMessage = '商品资料已保存'): ErpListingFeedback {
    if (!publish || publish.triggered === false) {
        const reason = String(publish?.reason || '')
        if (reason === 'manual_confirmation') return { level: 'success', message: savedMessage, detail: '当前配置为人工确认上架' }
        if (reason === 'channel_not_direct') return { level: 'success', message: savedMessage, detail: '请按渠道配置继续交接或发布' }
        if (reason === 'channel_disabled') return { level: 'success', message: savedMessage, detail: '商城渠道未启用，本次仅保存 ERP 资料' }
        if (reason === 'marketplace_unavailable') return { level: 'success', message: savedMessage, detail: '当前仓库不允许上商城，本次仅保存 ERP 资料' }
        return { level: 'success', message: savedMessage }
    }
    if (publish.failed) {
        return {
            level: 'warning',
            message: 'ERP 资料已保存，但商城发布失败',
            detail: String(publish.message || '请在库存中心查看失败原因并重新发布'),
        }
    }
    const result = publish.result || {}
    if (result.status === 'pending') return { level: 'success', message: result.message || '已交接商城运营处理' }
    return { level: 'success', message: result.message || '商品资料已保存并发布商城' }
}

/** 移动端统一展示“主数据已保存、渠道处理结果”，调用页不再重复拼弹窗。 */
export async function presentErpListingFeedback(publish: any, savedMessage = '商品资料已保存'): Promise<ErpListingFeedback> {
    const feedback = erpListingFeedback(publish, savedMessage)
    if (feedback.level === 'warning') {
        await new Promise<void>((resolve) => {
            uni.showModal({
                title: feedback.message,
                content: feedback.detail || '请在库存中心查看失败原因并重新发布',
                showCancel: false,
                confirmText: '我知道了',
                complete: () => resolve(),
            })
        })
        return feedback
    }
    uni.showToast({
        title: feedback.detail ? `${feedback.message}，${feedback.detail}` : feedback.message,
        icon: feedback.detail ? 'none' : 'success',
        duration: feedback.detail ? 2800 : 1800,
    })
    return feedback
}
