/**
 * 剪贴板工具
 * 统一封装复制功能，避免各处重复
 */

/** 复制文本到剪贴板 */
export const copyText = (text: string, tip = '复制成功') => {
  if (!text) return
  uni.setClipboardData({
    data: text,
    success: () => {
      uni.showToast({ title: tip, icon: 'success' })
    }
  })
}

/** 复制订单号 */
export const copyOrderNo = (orderNo: string) => copyText(orderNo, '订单号已复制')

/** 复制快递单号 */
export const copyExpressNo = (expressNo: string) => copyText(expressNo, '快递单号已复制')

/** 复制 IMEI */
export const copyIMEI = (imei: string) => copyText(imei, 'IMEI已复制')
