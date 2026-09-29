import { getAppPages, getSubPackagesPages } from './pages'

type NavigationMode = 'navigateTo' | 'redirectTo' | 'reLaunch' | 'switchTab'

interface NavigationOptions {
    url: string
    mode: string
    success?: (result: any) => void
    fail?: (error: any) => void
    complete?: (result: any) => void
}

const modes: NavigationMode[] = ['navigateTo', 'redirectTo', 'reLaunch', 'switchTab']
let latestNavigation = 0
let feedbackVisible = false

/** 诊断只包含静态页面路径，不包含查询参数、授权票据或外部地址。 */
const diagnosticPath = (url: unknown): string => {
    if (typeof url !== 'string') return '(无页面地址)'
    const path = '/' + url.split(/[?#]/)[0].replace(/^\//, '')
    // 未注册路径也可能含路径参数或票据，不能只凭字符规则判断其安全。
    if (![...getAppPages(), ...getSubPackagesPages()].includes(path)) return '(未注册页面地址，已隐藏)'
    return path
}

const pageStack = (): any[] => {
    try {
        return [...getCurrentPages()]
    } catch (_) { return [] }
}

// 不复制原始异常对象：其中可能含 URL 参数、登录票据或业务数据。
const failureReason = (error: any): [string, string] => {
    const message = String(error?.errMsg || error?.message || '').toLowerCase()
    if (message.includes('navigation_no_callback')) return ['NO_CALLBACK', '客户端长时间未返回跳转结果']
    if (message.includes('navigation_invalid_mode')) return ['INVALID_MODE', '页面使用了不支持的跳转方式']
    if (message.includes('navigation_invalid_url')) return ['INVALID_URL', '目标页面地址为空']
    if (/not.*tabbar|non.*tabbar/.test(message)) return ['NOT_TAB_PAGE', '目标不是底部导航页面，跳转方式不匹配']
    if (/tabbar/.test(message)) return ['TAB_PAGE_MODE', '底部导航页面需要使用对应的跳转方式']
    if (/not found|not exist|not registered|page does not/.test(message)) return ['PAGE_NOT_FOUND', '当前小程序版本未找到目标页面']
    if (/limit exceed|webview count|page stack/.test(message)) return ['PAGE_STACK_LIMIT', '已打开的页面过多，请返回后重试']
    if (/not support|unsupported|not a function/.test(message)) return ['API_UNAVAILABLE', '当前客户端不支持此跳转能力']
    if (/permission|denied|forbidden|unauthorized/.test(message)) return ['ACCESS_DENIED', '客户端拒绝了此次跳转']
    if (/timeout|time out/.test(message)) return ['TIMEOUT', '页面打开超时']
    if (/cancel|intercept/.test(message)) return ['INTERRUPTED', '此次跳转被取消或拦截']
    return ['UNKNOWN', '客户端未提供可识别的失败原因']
}

const runtimeDescription = (): string => {
    const lines: string[] = []
    try {
        const info: any = uni.getSystemInfoSync()
        lines.push(`客户端：${info.host?.appName || info.appName || '未提供'}`)
        lines.push(`运行环境：${info.environment || '未提供'}`)
        lines.push(`客户端版本：${info.version || info.host?.version || '未提供'}`)
        lines.push(`系统：${info.system || info.platform || '未提供'}`)
        lines.push(`基础库：${info.SDKVersion || '未提供'}`)
    } catch (_) { lines.push('客户端环境：未能读取') }
    try {
        const account = uni.getAccountInfoSync?.().miniProgram
        if (account) {
            lines.push(`小程序：${account.appId || '未提供'}`)
            lines.push(`版本：${account.envVersion || '未提供'} / ${account.version || '未提供'}`)
        }
    } catch (_) { /* 非小程序客户端可能不支持读取版本，不影响错误提示。 */ }
    return lines.join('\n')
}

const toast = (title: string) => {
    try { uni.showToast({ title, icon: 'none', duration: 3000 }) } catch (_) { /* 保留原始跳转结果。 */ }
}

const showFailure = (options: NavigationOptions, error: any, from: string) => {
    if (feedbackVisible) return
    const [code, reason] = failureReason(error)
    const errCode = /^-?\d{1,8}$/.test(String(error?.errCode)) ? String(error.errCode) : '未提供'
    const diagnostic = [
        '管理端页面跳转诊断 v1',
        `时间：${new Date().toISOString()}`,
        `方式：${modes.includes(options.mode as NavigationMode) ? options.mode : '不支持的方式'}`,
        `来源：${diagnosticPath(from)}`,
        `目标：${diagnosticPath(options.url)}`,
        `错误类型：${code} / ${reason}`,
        `客户端错误码：${errCode}`,
        runtimeDescription(),
        '已排除页面参数、Token、授权票据及业务内容；未自动上传。'
    ].join('\n')

    feedbackVisible = true
    const fallback = () => {
        feedbackVisible = false
        toast('页面暂时无法打开，请返回后重试')
    }
    try {
        uni.showModal({
            title: '页面暂时无法打开',
            content: `${reason}。可复制诊断信息发给管理员排查；不会执行订单或付款操作。`,
            confirmText: '复制诊断',
            cancelText: '关闭',
            success: (result) => {
                feedbackVisible = false
                if (!result.confirm) return
                try {
                    uni.setClipboardData({
                        data: diagnostic,
                        success: () => toast('诊断已复制，请发给管理员'),
                        fail: () => toast('复制失败，请稍后重试')
                    })
                } catch (_) { toast('当前客户端无法复制，请稍后重试') }
            },
            fail: fallback
        })
    } catch (_) { fallback() }
}

/** 通用导航反馈。调用方有 fail 时由其处理，保留登录流程等已有的回退逻辑。 */
export const navigateWithFeedback = (options: NavigationOptions): void => {
    const requestId = ++latestNavigation
    const initialPages = pageStack()
    const from = initialPages[initialPages.length - 1]?.route || ''
    let returned = false
    let feedbackShown = false
    let timer: ReturnType<typeof setTimeout> | undefined
    const finish = () => {
        returned = true
        if (timer !== undefined) clearTimeout(timer)
    }
    const report = (error: any) => {
        if (feedbackShown || requestId !== latestNavigation) return
        feedbackShown = true
        showFailure(options, error, from)
    }
    const failed = (error: any) => {
        finish()
        if (options.fail) options.fail(error)
        else report(error)
    }
    const rejected = (error: any) => {
        try { failed(error) } finally { options.complete?.(error) }
    }
    if (!modes.includes(options.mode as NavigationMode)) {
        rejected({ errMsg: 'navigation_invalid_mode' })
        return
    }
    if (typeof options.url !== 'string' || !options.url.trim()) {
        rejected({ errMsg: 'navigation_invalid_url' })
        return
    }
    // 超时只提示、不自动重新跳转、不伪造 fail，避免重复操作或破坏页面栈。
    // 登录等自带 fail 回退的调用方保留自己的超时策略。
    if (!options.fail) {
        timer = setTimeout(() => {
            const currentPages = pageStack()
            const unchanged = currentPages.length === initialPages.length && currentPages.every((page, index) => page === initialPages[index])
            if (!returned && unchanged) report({ errMsg: 'navigation_no_callback' })
        }, 12000)
    }
    try {
        const request: UniApp.NavigateToOptions = {
            url: options.url,
            success: (result) => {
                finish()
                options.success?.(result)
            },
            fail: failed,
            complete: (result) => {
                finish()
                options.complete?.(result)
            }
        }
        // uni 的四种方法带有不同的泛型重载，保持显式调用以便类型检查和跨端编译。
        switch (options.mode as NavigationMode) {
            case 'navigateTo': uni.navigateTo(request); break
            case 'redirectTo': uni.redirectTo(request); break
            case 'reLaunch': uni.reLaunch(request); break
            case 'switchTab': uni.switchTab(request); break
        }
    } catch (error) {
        // 调用方回调自身抛错时不误报为导航失败，也不重复执行其回调。
        if (returned) throw error
        rejected(error)
    }
}
