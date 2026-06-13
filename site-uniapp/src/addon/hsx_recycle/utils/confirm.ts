/**
 * 危险/不可逆操作的统一二次确认——对齐 admin confirmDanger（useSubmit.ts）。
 *
 * 移动端用 uni.showModal 实现。注意 showModal 的 content 仅支持纯文本，
 * 因此 admin 的 HTML 高亮在此降级为「把金额/客户名直接拼进文案」。
 *
 * @example
 * if (!(await confirmDanger(`确认打款 ¥${amount} 给「${name}」？打款后不可撤销。`,
 *   { title: '确认打款', confirmText: '确认打款' }))) return
 * await submit.run(() => api.payment(payload), { success: '打款成功' })
 */

export interface ConfirmDangerOptions {
  /** 弹窗标题，默认「操作确认」。 */
  title?: string
  /** 确认按钮文案，默认「确定」。 */
  confirmText?: string
  /** 取消按钮文案，默认「取消」。 */
  cancelText?: string
  /** 确认按钮颜色，默认主色 var token 对应的实色 #4f46e5。 */
  confirmColor?: string
}

/** 弹出二次确认，返回用户是否点击了确认。永不抛错。 */
export function confirmDanger(message: string, options: ConfirmDangerOptions = {}): Promise<boolean> {
  return new Promise((resolve) => {
    uni.showModal({
      title: options.title ?? '操作确认',
      content: message,
      confirmText: options.confirmText ?? '确定',
      cancelText: options.cancelText ?? '取消',
      confirmColor: options.confirmColor ?? '#4f46e5',
      success: (res) => resolve(!!res.confirm),
      fail: () => resolve(false)
    })
  })
}
