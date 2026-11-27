export const CLIPBOARD_PASSIVE_TIP_LIMIT = 3
export const CLIPBOARD_PASSIVE_TIP_STORAGE_KEY = 'hsx_clipboard_upload.passive_tip_count.v1'

let memoryTipCount = 0

const normalizeCount = (value) => {
    const count = Number.parseInt(String(value ?? ''), 10)
    return Number.isFinite(count) ? Math.min(CLIPBOARD_PASSIVE_TIP_LIMIT, Math.max(0, count)) : 0
}

const getStorage = () => {
    try {
        return typeof window !== 'undefined' ? window.localStorage : null
    } catch (error) {
        return null
    }
}

export const readClipboardPassiveTipCount = (storage = getStorage()) => {
    try {
        if (storage) memoryTipCount = Math.max(memoryTipCount, normalizeCount(storage.getItem(CLIPBOARD_PASSIVE_TIP_STORAGE_KEY)))
    } catch (error) {
        // Privacy mode can disable localStorage. The in-memory count still
        // prevents repeated prompts for the lifetime of the current page.
    }
    return memoryTipCount
}

export const hasReachedClipboardPassiveTipLimit = (storage = getStorage()) => {
    return readClipboardPassiveTipCount(storage) >= CLIPBOARD_PASSIVE_TIP_LIMIT
}

export const consumeClipboardPassiveTip = (storage = getStorage()) => {
    const currentCount = readClipboardPassiveTipCount(storage)
    if (currentCount >= CLIPBOARD_PASSIVE_TIP_LIMIT) {
        return { allowed: false, count: currentCount }
    }

    const nextCount = currentCount + 1
    memoryTipCount = nextCount
    try {
        storage?.setItem(CLIPBOARD_PASSIVE_TIP_STORAGE_KEY, String(nextCount))
    } catch (error) {
        // Keep the in-memory fallback when persistent storage is unavailable.
    }
    return { allowed: true, count: nextCount }
}
