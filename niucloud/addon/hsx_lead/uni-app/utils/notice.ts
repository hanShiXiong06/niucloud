// WeChat shows an icon-less toast in at most two lines (about 28 CJK characters)
// and silently cuts the rest. Server guidance such as "已收款，请勿重复付款" often
// ends in exactly the part that gets cut, so long text must not go through a toast.
export const COOP_TOAST_LIMIT = 28

type NoticeOptions = {
    /** Short toast to show when the full message is already rendered on the page. */
    inlineHint?: string
    /** Toast duration override for short messages (ms). */
    duration?: number
}

export function coopNoticeLength(text: string) {
    return [...text].length
}

/**
 * Show feedback without truncation:
 *  - short text: toast (longer display time for longer sentences);
 *  - long text already shown inline: a short toast pointing at it;
 *  - long text shown nowhere else: a modal with the full message.
 */
export function coopNotice(message: unknown, options: NoticeOptions = {}) {
    const text = String(message ?? '').trim()
    if (!text) return
    const length = coopNoticeLength(text)
    if (length <= COOP_TOAST_LIMIT) {
        uni.showToast({ title: text, icon: 'none', duration: options.duration ?? (length > 14 ? 2500 : 1500) })
        return
    }
    if (options.inlineHint) {
        uni.showToast({ title: options.inlineHint, icon: 'none', duration: 2000 })
        return
    }
    uni.showModal({ title: '提示', content: text, showCancel: false, confirmText: '知道了' })
}
