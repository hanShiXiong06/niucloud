type RequestLoadingOptions = {
    title?: string
    delay?: number
    mask?: boolean
}

let pendingCount = 0
let visible = false
let timer: ReturnType<typeof setTimeout> | null = null
let tokenSeed = 0
const activeTokens = new Set<number>()

/**
 * 全局网络请求反馈：支持并发计数和延迟展示，避免闪烁或被其它请求提前关闭。
 * 请求层默认调用；独立异步任务也可使用 withRequestLoading 包装。
 */
export function beginRequestLoading(options: RequestLoadingOptions = {}) {
    const token = ++tokenSeed
    activeTokens.add(token)
    pendingCount += 1
    if (pendingCount === 1) {
        if (timer) clearTimeout(timer)
        timer = setTimeout(() => {
            timer = null
            if (pendingCount <= 0 || visible) return
            visible = true
            uni.showLoading({ title: options.title || '处理中…', mask: options.mask !== false })
        }, Math.max(0, Number(options.delay ?? 220)))
    }
    return token
}

export function endRequestLoading(token: number) {
    if (!activeTokens.delete(token)) return
    pendingCount = Math.max(0, pendingCount - 1)
    if (pendingCount > 0) return
    if (timer) { clearTimeout(timer); timer = null }
    if (visible) { visible = false; uni.hideLoading() }
}

export async function withRequestLoading<T>(task: () => Promise<T>, options: RequestLoadingOptions = {}): Promise<T> {
    const token = beginRequestLoading(options)
    try { return await task() } finally { endRequestLoading(token) }
}

export function useRequestLoading() {
    return { beginRequestLoading, endRequestLoading, withRequestLoading }
}
