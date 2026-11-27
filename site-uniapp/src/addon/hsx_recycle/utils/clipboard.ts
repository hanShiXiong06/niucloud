/**
 * 回收插件剪贴板工具
 */
export const copyText = (value: string | number | undefined | null, tip = '复制成功') => {
    const text = String(value || '').trim()
    if (!text) {
        uni.showToast({ title: '暂无可复制内容', icon: 'none' })
        return false
    }

    uni.setClipboardData({
        data: text,
        success: () => {
            uni.showToast({ title: tip, icon: 'success' })
        },
        fail: () => {
            uni.showToast({ title: '复制失败，请重试', icon: 'none' })
        }
    })
    return true
}

export const copyOrderNo = (value: string | number | undefined | null) => copyText(value, '订单号已复制')

export const copyExpressNo = (value: string | number | undefined | null) => copyText(value, '快递单号已复制')

export const copyIMEI = (value: string | number | undefined | null) => copyText(value, 'IMEI已复制')

export const copyIMEIs = (values: Array<string | number | undefined | null>, tip = '已复制IMEI') => {
    const text = values.map(item => String(item || '').trim()).filter(Boolean).join('\n')
    return copyText(text, tip)
}
