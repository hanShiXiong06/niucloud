/** Plugin task routes use the native page stack; the framework owns the four tabs. */
export const cooperationHomeUrl = '/addon/hsx_lead/pages/index'
export const cooperationTaskUrl = '/addon/hsx_lead/pages/task/index'
export function cooperationTaskLink(task: string, params: Record<string, unknown> = {}) {
    const query = Object.entries({ task, ...params })
        .filter(([, value]) => value != null && value !== '')
        .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`)
        .join('&')
    return `${cooperationTaskUrl}?${query}`
}
const openingTasks = new Set<string>()
export function openCooperationTask(
    task: string,
    params: Record<string, unknown> = {},
    onResult?: (opened: boolean) => void
) {
    const url = cooperationTaskLink(task, params)
    if (openingTasks.has(url)) return false
    openingTasks.add(url)
    try {
        uni.navigateTo({
            url,
            success: () => onResult?.(true),
            complete: () => openingTasks.delete(url),
            fail: () => {
                onResult?.(false)
                uni.showToast({ title: '页面未打开，请重试', icon: 'none' })
            }
        })
        return true
    } catch (_) {
        openingTasks.delete(url)
        onResult?.(false)
        uni.showToast({ title: '页面未打开，请重试', icon: 'none' })
        return false
    }
}
export function backCooperationTask() {
    const pages = getCurrentPages()
    for (let index = pages.length - 2; index >= 0; index--) {
        const page = pages[index] as any
        const route = String(page?.route || page?.$page?.route || page?.$page?.fullPath || '')
            .replace(/^\/+/, '')
            .split(/[?#]/)[0]
        // Authentication can remain in the stack after login returns to a deep-linked task.
        // Never send a successfully onboarded merchant back into that login flow.
        if (!route || /(?:^|\/)app\/pages\/auth(?:\/|$)/.test(route)) continue
        uni.navigateBack({ delta: pages.length - 1 - index })
        return
    }
    uni.redirectTo({ url: cooperationHomeUrl })
}
export function openCooperationCatalog(
    options: Record<string, unknown>,
    onSelected: (items: Record<string, any>[]) => void
) {
    uni.navigateTo({
        url: '/addon/hsx_lead/pages/catalog/index',
        events: { selected: onSelected },
        success: (result) => result.eventChannel.emit('initialize', options)
    })
}
