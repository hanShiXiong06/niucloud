export interface DeviceBridgeDownloads {
    windows_url: string
    windows_version: string
    macos_arm64_url: string
    macos_arm64_version: string
    tutorial_url: string
}

export type BridgeComputer = 'windows' | 'macos_arm64' | 'other'

export const BRIDGE_BASE_URL = 'http://127.0.0.1:17890'

export const emptyBridgeDownloads = (): DeviceBridgeDownloads => ({
    windows_url: '', windows_version: '', macos_arm64_url: '', macos_arm64_version: '', tutorial_url: ''
})

export function safeBridgeUrl(value: unknown): string {
    if (typeof value !== 'string') return ''
    const url = value.trim()
    if (!url || url.length > 2048 || /[\s\u0000-\u001f\u007f\\]/.test(url)) return ''
    if (url.startsWith('/')) return url.startsWith('//') ? '' : url
    if (!/^https?:\/\//i.test(url)) return ''
    try {
        const parsed = new URL(url)
        return ['http:', 'https:'].includes(parsed.protocol) && !parsed.username && !parsed.password ? url : ''
    } catch {
        return ''
    }
}

export function validBridgeVersion(value: unknown): boolean {
    return typeof value === 'string' && value.length <= 40 && /^\d{1,6}(?:\.\d{1,6}){1,3}$/.test(value)
}

export function normalizeBridgeDownloads(data: Partial<DeviceBridgeDownloads> = {}): DeviceBridgeDownloads {
    const result = emptyBridgeDownloads()
    for (const key of Object.keys(result) as Array<keyof DeviceBridgeDownloads>) {
        const raw = data[key]
        const value = typeof raw === 'string' ? raw.trim() : ''
        result[key] = key.endsWith('_version') ? (validBridgeVersion(value) ? value : '') : safeBridgeUrl(value)
    }
    return result
}

export function bridgeUpdateAvailable(current: string, published: string): boolean {
    if (!validBridgeVersion(current) || !validBridgeVersion(published)) return false
    const installed = current.split('.').map(Number)
    const latest = published.split('.').map(Number)
    for (let i = 0; i < Math.max(installed.length, latest.length); i++) {
        if ((latest[i] || 0) !== (installed[i] || 0)) return (latest[i] || 0) > (installed[i] || 0)
    }
    return false
}

export function detectBridgeComputer(userAgent: string): BridgeComputer {
    if (/Android|iPhone|iPad|Mobile/i.test(userAgent)) return 'other'
    if (/Windows/i.test(userAgent)) return 'windows'
    if (/Macintosh|Mac OS X/i.test(userAgent)) return 'macos_arm64'
    return 'other'
}

export function bridgeConnectionError(error: any): string {
    if (Number(error?.response?.status) === 403) return '当前后台域名未获授权，请联系管理员更新设备桥的站点白名单。'
    if (error?.code === 'ECONNABORTED' || /timeout/i.test(error?.message || '')) return '设备桥检测超时，请确认本机服务已启动后重新检测。'
    return '未连接到设备桥。请先安装或启动服务；浏览器提示访问本地网络时请选择允许。'
}
