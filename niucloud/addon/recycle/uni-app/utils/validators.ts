/**
 * 表单验证工具
 * 统一校验规则，避免 payment/index 和 return_order/address 重复
 */

/** 校验姓名 */
export const validateName = (name: string): string | null => {
  if (!name || !name.trim()) return '请输入姓名'
  if (name.trim().length < 2) return '姓名至少2个字符'
  return null
}

/** 校验手机号 */
export const validateMobile = (mobile: string): string | null => {
  if (!mobile || !mobile.trim()) return '请输入手机号'
  if (!/^1[3-9]\d{9}$/.test(mobile.trim())) return '请输入正确的手机号'
  return null
}

/** 校验身份证号 */
export const validateIdCard = (idCard: string): string | null => {
  if (!idCard || !idCard.trim()) return '请输入身份证号'
  if (!/^\d{17}[\dXx]$/.test(idCard.trim())) return '请输入正确的身份证号'
  return null
}

/** 批量校验，返回第一个错误信息或 null */
export const validateAll = (rules: Array<() => string | null>): string | null => {
  for (const rule of rules) {
    const err = rule()
    if (err) return err
  }
  return null
}
