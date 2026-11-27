let lastToastKey = ''
let lastToastAt = 0

const textOf = (value: unknown): string => {
    if (typeof value === 'string') return value.trim()
    if (typeof value === 'number') return String(value)
    return ''
}

/** 从牛云请求封装、原生网络错误和业务异常中提取可给用户看的原因。 */
export const erpErrorMessage = (error: any, fallback = '数据加载失败，请稍后重试'): string => {
    const candidates = [
        error?.data?.msg,
        error?.response?.data?.msg,
        error?.response?.msg,
        error?.msg,
        error?.message,
        error?.errMsg,
    ]
    const raw = candidates.map(textOf).find(Boolean) || fallback
    const generic = /^(request:fail|network error|failed to fetch|error)$/i.test(raw)
    return generic ? fallback : raw.slice(0, 80)
}

/**
 * 统一移动 ERP 错误反馈。短时间内同一错误只提示一次，避免分页组件自动重试
 * 时连续弹 Toast；列表仍应在调用处 complete(false)，保留“点击重试”能力。
 */
export const showErpError = (error: any, fallback?: string): string => {
    const message = erpErrorMessage(error, fallback)
    const now = Date.now()
    if (message !== lastToastKey || now - lastToastAt > 1500) {
        lastToastKey = message
        lastToastAt = now
        uni.showToast({ title: message, icon: 'none', duration: 2600 })
    }
    return message
}

