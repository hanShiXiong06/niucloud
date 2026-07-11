import { onActivated, onBeforeUnmount, onDeactivated, onMounted } from 'vue'

/**
 * ERP 页面数据刷新：
 * - 首次进入时加载；
 * - KeepAlive 页面从详情/编辑页返回时重新加载；
 * - 合并同一时刻的重复刷新，避免并发接口覆盖新数据。
 */
export function useErpPageRefresh(loader: () => void | Promise<void>) {
    let active = true
    let running = false
    let pending = false
    let lastRefreshAt = 0

    const refresh = async (force = false) => {
        if (!force && Date.now() - lastRefreshAt < 400) return
        if (running) {
            pending = true
            return
        }
        running = true
        try {
            await loader()
        } finally {
            lastRefreshAt = Date.now()
            running = false
            if (pending) {
                pending = false
                await refresh(true)
            }
        }
    }

    onMounted(() => {
        active = true
        window.addEventListener('focus', onWindowFocus)
        document.addEventListener('visibilitychange', onVisibilityChange)
        void refresh(true)
    })

    onActivated(() => {
        active = true
        void refresh()
    })

    onDeactivated(() => { active = false })
    onBeforeUnmount(() => {
        window.removeEventListener('focus', onWindowFocus)
        document.removeEventListener('visibilitychange', onVisibilityChange)
    })

    function onWindowFocus() { if (active) void refresh() }
    function onVisibilityChange() { if (active && document.visibilityState === 'visible') void refresh() }

    return { refresh }
}
