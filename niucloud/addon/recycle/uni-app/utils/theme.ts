/**
 * Recycle 插件主题配置
 * 统一管理颜色、渐变、CSS变量
 *
 * 色彩体系基于 Tailwind CSS 调色板，保持与 order 页面一致
 */

// ============ 品牌色 ============
export const BRAND = {
  primary: '#5F758A',
  primaryLight: 'rgba(95, 117, 138, 0.1)',
  gradient: 'linear-gradient(to right, #8C7575, #5F758A)',
} as const

// ============ 语义色 ============
export const COLORS = {
  // 功能色
  success: '#10b981',
  warning: '#f59e0b',
  danger: '#ef4444',
  info: '#3b82f6',

  // 中性色
  textPrimary: '#1e293b',
  textSecondary: '#64748b',
  textMuted: '#94a3b8',
  textDisabled: '#cbd5e1',

  border: '#e2e8f0',
  borderLight: '#f1f5f9',
  bgPage: '#f8fafc',
  bgCard: '#ffffff',
  bgMuted: '#f1f5f9',

  // 价格
  price: '#ff6b00',
} as const

// ============ 订单状态 ============
export const ORDER_STATUS = {
  1: { text: '待签收', color: '#f59e0b', bgColor: '#fef3c7', gradient: 'linear-gradient(135deg, #ffa726, #fb8c00)' },
  2: { text: '已签收', color: '#06b6d4', bgColor: '#cffafe', gradient: 'linear-gradient(135deg, #26c6da, #0097a7)' },
  3: { text: '质检中', color: '#3b82f6', bgColor: '#dbeafe', gradient: 'linear-gradient(135deg, #42a5f5, #1e88e5)' },
  4: { text: '已质检', color: '#6366f1', bgColor: '#e0e7ff', gradient: 'linear-gradient(135deg, #7c4dff, #651fff)' },
  5: { text: '待确认', color: '#8b5cf6', bgColor: '#ede9fe', gradient: 'linear-gradient(135deg, #b388ff, #7c4dff)' },
  6: { text: '待打款', color: '#ec4899', bgColor: '#fce7f3', gradient: 'linear-gradient(135deg, #f06292, #e91e63)' },
  7: { text: '已完成', color: '#10b981', bgColor: '#d1fae5', gradient: 'linear-gradient(135deg, #4caf50, #2e7d32)' },
  8: { text: '已关闭', color: '#6b7280', bgColor: '#f3f4f6', gradient: 'linear-gradient(135deg, #90a4ae, #607d8b)' },
  9: { text: '已取消', color: '#6b7280', bgColor: '#f3f4f6', gradient: 'linear-gradient(135deg, #90a4ae, #607d8b)' },
} as const

// ============ 设备状态 ============
export const DEVICE_STATUS = {
  1: { text: '待质检', color: '#f59e0b', bgColor: '#fef3c7' },
  2: { text: '质检中', color: '#3b82f6', bgColor: '#dbeafe' },
  3: { text: '已质检', color: '#6366f1', bgColor: '#e0e7ff' },
  4: { text: '待确认', color: '#8b5cf6', bgColor: '#ede9fe' },
  5: { text: '已回收', color: '#10b981', bgColor: '#d1fae5' },
  6: { text: '已退回', color: '#ef4444', bgColor: '#fee2e2' },
  7: { text: '已定价', color: '#06b6d4', bgColor: '#cffafe' },
} as const

// ============ 退货订单状态 ============
export const RETURN_ORDER_STATUS = {
  0: { text: '待处理', color: '#f59e0b', bgColor: '#fef3c7', gradient: 'linear-gradient(135deg, #ffa726, #fb8c00)' },
  1: { text: '退货中', color: '#3b82f6', bgColor: '#dbeafe', gradient: 'linear-gradient(135deg, #42a5f5, #1e88e5)' },
  2: { text: '已完成', color: '#10b981', bgColor: '#d1fae5', gradient: 'linear-gradient(135deg, #4caf50, #2e7d32)' },
  3: { text: '已取消', color: '#6b7280', bgColor: '#f3f4f6', gradient: 'linear-gradient(135deg, #90a4ae, #607d8b)' },
} as const

// ============ 配送方式 ============
export const DELIVERY_TYPE = {
  1: { text: '邮寄', color: '#3b82f6' },
  2: { text: '自送', color: '#10b981' },
} as const

// ============ 默认状态 ============
export const DEFAULT_STATUS = { text: '未知状态', color: '#6b7280', bgColor: '#f3f4f6', gradient: 'linear-gradient(135deg, #90a4ae, #607d8b)' } as const

// ============ 辅助函数 ============

/** 获取订单状态信息 */
export const getOrderStatusInfo = (status: number | string) => {
  return ORDER_STATUS[Number(status) as keyof typeof ORDER_STATUS] || DEFAULT_STATUS
}

/** 获取设备状态信息 */
export const getDeviceStatusInfo = (status: number | string) => {
  return DEVICE_STATUS[Number(status) as keyof typeof DEVICE_STATUS] || DEFAULT_STATUS
}

/** 获取退货订单状态信息 */
export const getReturnOrderStatusInfo = (status: number | string) => {
  return RETURN_ORDER_STATUS[Number(status) as keyof typeof RETURN_ORDER_STATUS] || DEFAULT_STATUS
}

/** 获取配送方式信息 */
export const getDeliveryTypeInfo = (type: number | string) => {
  return DELIVERY_TYPE[Number(type) as keyof typeof DELIVERY_TYPE] || { text: '未知', color: '#6b7280' }
}
