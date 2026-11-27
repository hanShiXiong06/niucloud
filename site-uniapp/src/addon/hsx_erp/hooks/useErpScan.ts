export const scanErpCode = () => new Promise<string>((resolve, reject) => {
    uni.scanCode({
        scanType: ['barCode', 'qrCode'],
        success: (res: any) => {
            const code = normalizeErpScanText(res?.result || res?.path || '')
            if (code) resolve(code)
            else reject(new Error('未识别到有效内容'))
        },
        fail: reject,
    } as any)
})

export const normalizeErpScanText = (value: any) => {
    const text = String(value || '').trim()
    if (!text) return ''
    const imeiMatch = text.match(/\b\d{14,17}\b/)
    if (imeiMatch) return imeiMatch[0]
    const queryMatch = text.match(/[?&](?:imei|sn|asset_no|code)=([^&]+)/i)
    if (queryMatch) {
        try { return decodeURIComponent(queryMatch[1]).trim() } catch { return queryMatch[1].trim() }
    }
    return text
}
