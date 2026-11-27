export interface DevicePrintTarget { deviceId: string; siteId: number }

/** 仅解析回收插件的设备直达链接；不根据二维码切换站点或打开任意外部地址。 */
export function parseDevicePrintTarget(value: unknown): DevicePrintTarget | null {
    let params: Record<string, any>
    if (typeof value === 'string') {
        if (!/\/addon\/hsx_recycle\/pages\/check\/scan\?/.test(value)) return null
        params = Object.create(null)
        try {
            for (const pair of value.split('?')[1].split('#')[0].split('&')) {
                const [key, val = ''] = pair.split('=')
                params[decodeURIComponent(key)] = decodeURIComponent(val)
            }
        } catch (_) { return null }
    } else if (value && typeof value === 'object' && !Array.isArray(value)) {
        params = value as Record<string, any>
    } else return null
    const deviceId = String(params.device_id || '')
    const siteText = String(params.site_id || '')
    const isPositiveId = (text: string) => /^[1-9]/.test(text) && !/[^0-9]/.test(text) && Number.isSafeInteger(Number(text))
    if (!isPositiveId(deviceId)) return null
    if (siteText && !isPositiveId(siteText)) return null
    return { deviceId, siteId: Number(siteText || 0) }
}
