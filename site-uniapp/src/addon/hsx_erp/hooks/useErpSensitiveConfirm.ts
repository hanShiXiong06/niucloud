export interface ErpSensitiveConfirmOptions {
    title: string
    content: string
    confirmText?: string
    cancelText?: string
}

/** ERP 敏感操作统一二次确认：取消或弹窗失败都视为不执行。 */
export function confirmErpSensitiveAction(options: ErpSensitiveConfirmOptions): Promise<boolean> {
    return new Promise(resolve => {
        uni.showModal({
            title: options.title,
            content: options.content,
            confirmText: options.confirmText || '确认执行',
            cancelText: options.cancelText || '返回检查',
            success: result => resolve(Boolean(result.confirm)),
            fail: () => resolve(false),
        })
    })
}
