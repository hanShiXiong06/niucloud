import type { HsxHapticType, HsxModalOptions } from '../types'

export function useHaptics() {
    const trigger = (type: HsxHapticType = 'light'): Promise<boolean> => new Promise((resolve) => {
        if (type === 'none' || typeof uni === 'undefined' || typeof uni.vibrateShort !== 'function') {
            resolve(false)
            return
        }

        // 微信开发者工具会用“整个模拟器画面抖动”来模拟手机震动，容易被误认为
        // 页面重排或按钮动效故障。开发环境跳过模拟，真机仍保留触感反馈。
        try {
            const deviceInfo = typeof uni.getDeviceInfo === 'function' ? uni.getDeviceInfo() : undefined
            if (deviceInfo?.platform === 'devtools') {
                resolve(false)
                return
            }
        } catch (_) {
            // 个别旧端没有 getDeviceInfo，继续按原能力安全降级。
        }

        uni.vibrateShort({
            type: type as any,
            success: () => resolve(true),
            fail: () => resolve(false)
        } as any)
    })

    const long = (): Promise<boolean> => new Promise((resolve) => {
        if (typeof uni === 'undefined' || typeof uni.vibrateLong !== 'function') {
            resolve(false)
            return
        }
        try {
            const deviceInfo = typeof uni.getDeviceInfo === 'function' ? uni.getDeviceInfo() : undefined
            if (deviceInfo?.platform === 'devtools') {
                resolve(false)
                return
            }
        } catch (_) {}
        uni.vibrateLong({ success: () => resolve(true), fail: () => resolve(false) })
    })

    return {
        trigger,
        selection: () => trigger('light'),
        success: () => trigger('light'),
        warning: () => trigger('medium'),
        error: () => trigger('heavy'),
        long
    }
}

export function useToast() {
    const haptics = useHaptics()
    const show = (title: string, icon: UniApp.ShowToastOptions['icon'] = 'none', duration = 2000) =>
        uni.showToast({ title, icon, duration })

    return {
        show,
        success: (title: string, haptic = false) => { if (haptic) void haptics.success(); return show(title, 'success') },
        error: (title: string, haptic = false) => { if (haptic) void haptics.error(); return show(title, 'none') }
    }
}

export function useModal() {
    const haptics = useHaptics()
    const confirm = (input: string | HsxModalOptions, legacyTitle = '提示'): Promise<boolean> =>
        new Promise((resolve) => {
            const options: HsxModalOptions = typeof input === 'string' ? { content: input, title: legacyTitle } : input
            uni.showModal({
                title: options.title || '提示',
                content: options.content,
                confirmText: options.confirmText || '确定',
                cancelText: options.cancelText || '取消',
                showCancel: options.showCancel ?? true,
                editable: options.editable,
                placeholderText: options.placeholderText,
                confirmColor: options.confirmColor,
                cancelColor: options.cancelColor,
                success: (result) => {
                    if (result.confirm && options.haptic && options.haptic !== 'none') void haptics.trigger(options.haptic)
                    resolve(Boolean(result.confirm))
                },
                fail: () => resolve(false)
            } as UniApp.ShowModalOptions)
        })

    const alert = (content: string, title = '提示') => confirm({ content, title, showCancel: false, confirmText: '知道了' })
    const danger = (content: string, title = '风险确认') => confirm({ content, title, confirmText: '确认操作', confirmColor: '#dc2626', haptic: 'heavy' })

    return { confirm, alert, danger }
}

export function useActionSheet() {
    const haptics = useHaptics()
    const show = (itemList: string[], itemColor?: string): Promise<number | null> => new Promise((resolve) => {
        uni.showActionSheet({
            itemList,
            itemColor,
            success: (result) => { void haptics.selection(); resolve(result.tapIndex) },
            fail: () => resolve(null)
        })
    })
    return { show }
}

/** 与 Web 端保持同一层级语义；移动端重通知使用系统 Modal 承载。 */
export function useFeedback() {
    const toast = useToast()
    const modal = useModal()
    const light = (message: string, type: 'success' | 'error' | 'info' = 'info') => {
        if (type === 'success') return toast.success(message)
        if (type === 'error') return toast.error(message)
        return toast.show(message)
    }
    const notice = (input: string | { title?: string, message: string }) => {
        const options = typeof input === 'string' ? { message: input } : input
        return modal.alert(options.message, options.title || '提醒')
    }
    return {
        light,
        message: light,
        success: (message: string) => light(message, 'success'),
        error: (message: string) => light(message, 'error'),
        info: (message: string) => light(message, 'info'),
        notice,
        notification: notice,
        confirm: modal.confirm,
        alert: modal.alert,
        danger: modal.danger
    }
}
