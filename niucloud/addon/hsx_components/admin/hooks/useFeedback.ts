import {
    ElMessage,
    ElMessageBox,
    ElNotification,
    type MessageOptions,
    type NotificationOptions
} from 'element-plus'

export type HsxFeedbackType = 'success' | 'warning' | 'info' | 'error'

export interface HsxConfirmOptions {
    title?: string
    message: string
    confirmText?: string
    cancelText?: string
    type?: HsxFeedbackType
    distinguishCancelAndClose?: boolean
}

export type HsxLightMessage = string | Partial<MessageOptions>
export type HsxHeavyNotice = string | Partial<NotificationOptions>

function messageOptions(input: HsxLightMessage, type: HsxFeedbackType): MessageOptions {
    const resolvedType = typeof input === 'string' ? type : input.type || type
    const defaults = { duration: resolvedType === 'success' ? 2400 : 5200, showClose: true, grouping: true }
    return typeof input === 'string'
        ? { ...defaults, message: input, type }
        : { ...defaults, ...input, type: resolvedType } as MessageOptions
}

function noticeOptions(input: HsxHeavyNotice, type: HsxFeedbackType): NotificationOptions {
    return (typeof input === 'string'
        ? { title: type === 'error' ? '操作失败' : type === 'warning' ? '请注意' : '操作提醒', message: input, type, duration: 5200, showClose: true }
        : { duration: 5200, showClose: true, ...input, type: input.type || type }) as unknown as NotificationOptions
}

/** 统一轻提示、重通知和需要用户决策的确认反馈。 */
export function useFeedback() {
    const light = (input: HsxLightMessage, type: HsxFeedbackType = 'info') => ElMessage(messageOptions(input, type))
    const notice = (input: HsxHeavyNotice, type: HsxFeedbackType = 'info') => ElNotification(noticeOptions(input, type))
    const confirm = async (input: string | HsxConfirmOptions): Promise<boolean> => {
        const config: HsxConfirmOptions = typeof input === 'string' ? { message: input } : input
        try {
            await ElMessageBox.confirm(config.message, config.title || '请确认', {
                confirmButtonText: config.confirmText || '确认',
                cancelButtonText: config.cancelText || '取消',
                type: config.type || 'warning',
                distinguishCancelAndClose: config.distinguishCancelAndClose ?? true,
                closeOnClickModal: false,
                draggable: true
            })
            return true
        } catch {
            return false
        }
    }
    const alert = async (message: string, title = '提示', type: HsxFeedbackType = 'info') => {
        try {
            await ElMessageBox.alert(message, title, { type, confirmButtonText: '知道了', closeOnClickModal: false, draggable: true })
        } catch (action) {
            // 关闭说明是正常阅读行为，不把取消传播成“保存失败”。
            if (action !== 'cancel' && action !== 'close') throw action
        }
    }

    return {
        light,
        message: light,
        success: (input: HsxLightMessage) => light(input, 'success'),
        warning: (input: HsxLightMessage) => light(input, 'warning'),
        error: (input: HsxLightMessage) => light(input, 'error'),
        info: (input: HsxLightMessage) => light(input, 'info'),
        notice,
        notification: notice,
        noticeSuccess: (input: HsxHeavyNotice) => notice(input, 'success'),
        noticeWarning: (input: HsxHeavyNotice) => notice(input, 'warning'),
        noticeError: (input: HsxHeavyNotice) => notice(input, 'error'),
        confirm,
        alert,
        closeMessages: () => ElMessage.closeAll(),
        closeNotices: () => ElNotification.closeAll()
    }
}
