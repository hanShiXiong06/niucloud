/**
 * 剪贴板工具
 * 统一封装复制功能，避免各处重复
 */

/** 复制文本到剪贴板 */
export const copyText = (text: string, tip = '复制成功') => {
  const content = String(text || '').trim()
  if (!content) {
    uni.showToast({ title: '暂无可复制内容', icon: 'none' })
    return false
  }
  uni.setClipboardData({
    data: content,
    success: () => {
      uni.showToast({ title: tip, icon: 'success' })
    },
    fail: () => {
      uni.showToast({ title: '复制失败，请重试', icon: 'none' })
    }
  })
  return true
}

/** 复制订单号 */
export const copyOrderNo = (orderNo: string) => copyText(orderNo, '订单号已复制')

/** 复制快递单号 */
export const copyExpressNo = (expressNo: string) => copyText(expressNo, '快递单号已复制')

/** 复制 IMEI */
export const copyIMEI = (imei: string) => copyText(imei, 'IMEI已复制')

/** 批量复制 IMEI */
export const copyIMEIs = (imeis: Array<string | number | undefined | null>, tip = '已复制IMEI') => {
  const text = imeis
    .map(item => String(item || '').trim())
    .filter(Boolean)
    .join('\n')
  return copyText(text, tip)
}
