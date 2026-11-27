type Kind = 'navigate' | 'select' | 'confirm'
type Runtime = {
    vibrateShort?: (options: { type: 'light'; fail: () => void }) => unknown
    getStorageSync?: (key: string) => unknown
    setStorageSync?: (key: string, value: boolean) => void
}
const preferenceKey = 'hsx_lead_touch_feedback_v1'

// A single optional adapter: never use browser vibration or block a business action.
export function createInteractionFeedback(getRuntime: () => Runtime | undefined, now = () => Date.now()) {
    let preference: boolean | undefined
    let lastAt = -Infinity
    const runtime = () => {
        try {
            return getRuntime()
        } catch {
            return undefined
        }
    }
    const supported = () => typeof runtime()?.vibrateShort === 'function'
    const enabled = () => {
        if (preference !== undefined) return preference
        try {
            return runtime()?.getStorageSync?.(preferenceKey) !== false
        } catch {
            return true
        }
    }
    const setEnabled = (value: boolean) => {
        preference = value === true
        try {
            runtime()?.setStorageSync?.(preferenceKey, preference)
        } catch {
            /* Optional preference storage. */
        }
    }
    const feedback = (_kind: Kind) => {
        const api = runtime()
        if (!enabled() || typeof api?.vibrateShort !== 'function') return false
        const at = now()
        if (at - lastAt < 350) return false
        lastAt = at
        try {
            api.vibrateShort({ type: 'light', fail: () => {} })
            return true
        } catch {
            return false
        }
    }
    return { supported, enabled, setEnabled, feedback }
}

function nativeRuntime(): Runtime | undefined {
    let runtime: Runtime | undefined
    // #ifdef MP-WEIXIN
    runtime = typeof uni === 'undefined' ? undefined : uni
    // #endif
    return runtime
}
const touch = createInteractionFeedback(nativeRuntime)
export const interactionFeedback = touch.feedback
export const interactionFeedbackSupported = touch.supported
export const interactionFeedbackEnabled = touch.enabled
export const setInteractionFeedbackEnabled = touch.setEnabled
