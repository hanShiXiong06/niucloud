import { nextTick } from 'vue'
import {
    confirmErpSensitiveAction,
    type ErpSensitiveConfirmOptions,
} from '@/addon/hsx_erp/hooks/useErpSensitiveConfirm'

export type ErpPopupConfirmOptions = ErpSensitiveConfirmOptions & {
    closePopup: () => void
    reopenPopup?: () => void
    transitionMs?: number
}

/**
 * Bottom Sheet 内的敏感操作统一确认流程。
 *
 * 先由调用方冻结提交快照，再关闭业务弹层并等待遮罩退场，最后展示系统确认框；
 * 取消时恢复业务弹层，避免 showModal 被 popup 遮挡或点击穿透改变勾选结果。
 */
export async function confirmErpPopupAction(options: ErpPopupConfirmOptions): Promise<boolean> {
    options.closePopup()
    await nextTick()
    await waitForPopupTransition(options.transitionMs)

    const confirmed = await confirmErpSensitiveAction(options)
    if (!confirmed && options.reopenPopup) {
        options.reopenPopup()
        await nextTick()
    }
    return confirmed
}

export function cloneErpSubmitSnapshot<T>(value: T): T {
    if (value === null || value === undefined) return value
    return JSON.parse(JSON.stringify(value)) as T
}

function waitForPopupTransition(ms = 320) {
    return new Promise(resolve => setTimeout(resolve, Math.max(0, ms)))
}
