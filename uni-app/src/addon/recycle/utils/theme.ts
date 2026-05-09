/**
 * Recycle 插件主题配置
 * 统一管理颜色、渐变、CSS变量
 *
 * 色彩体系基于 Tailwind CSS 调色板，保持与 order 页面一致
 */

// ============ 品牌色 ============
export const BRAND = {
  primary: '#3b82f6',
  primaryDeep: '#4f46e5',
  primaryDark: '#111827',
  primaryLight: 'rgba(59, 130, 246, 0.1)',
  gradient: 'linear-gradient(100deg, #111827 0%, #4f46e5 58%, #3b82f6 100%)',
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
  textSecondary: '#6b7280',
  textMuted: '#94a3b8',
  textDisabled: '#cbd5e1',

  border: '#e5e7eb',
  borderLight: '#f7f7f8',
  bgPage: '#f3f4f6',
  bgCard: '#ffffff',
  bgMuted: '#f7f7f8',
  noticeBg: '#fff8ed',
  noticeText: '#f59e0b',

  // 价格
  price: '#2563eb',
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

export const RECYCLE_THEME_COLORS = {
  page_bg: COLORS.bgPage,
  card_bg: COLORS.bgCard,
  soft_bg: COLORS.bgMuted,
  line: COLORS.border,
  text_main: '#1f2937',
  text_sub: COLORS.textSecondary,
  brand: BRAND.primary,
  brand_deep: BRAND.primaryDeep,
  price: COLORS.price,
  notice_bg: COLORS.noticeBg,
  notice_text: COLORS.noticeText,
  toolbar_bg: COLORS.bgCard,
  button_bg: BRAND.primaryDark,
  button_text: '#ffffff',
  series_active_bg: BRAND.primaryDark,
  series_active_text: '#ffffff',
  series_inactive_bg: '#f8fafc',
  series_inactive_text: '#475569',
  model_head_bg: '#f8fafc',
  model_brand_bg: BRAND.primaryDark,
  model_brand_text: '#ffffff'
} as const

export type RecycleThemeColors = Partial<Record<keyof typeof RECYCLE_THEME_COLORS, string>>

const isHexColor = (value: unknown): value is string => {
  return typeof value === 'string' && /^#[0-9a-fA-F]{6}$/.test(value)
}

export const mergeRecycleThemeColors = (colors: RecycleThemeColors = {}) => {
  const merged: Record<string, string> = { ...RECYCLE_THEME_COLORS }
  Object.entries(colors).forEach(([key, value]) => {
    if (isHexColor(value)) {
      merged[key] = value
    }
  })
  return merged
}

export const buildRecycleThemeVars = (colors: RecycleThemeColors = {}) => {
  const merged = mergeRecycleThemeColors(colors)
  const map: Record<string, string> = {
    page_bg: '--recycle-bg-main',
    card_bg: '--recycle-bg-card',
    soft_bg: '--recycle-bg-soft',
    line: '--recycle-line',
    text_main: '--recycle-text-main',
    text_sub: '--recycle-text-sub',
    brand: '--recycle-brand',
    brand_deep: '--recycle-brand-deep',
    price: '--recycle-price',
    notice_bg: '--recycle-notice-bg',
    notice_text: '--recycle-notice-text',
    toolbar_bg: '--recycle-toolbar-bg',
    button_bg: '--recycle-button-bg',
    button_text: '--recycle-button-text',
    series_active_bg: '--recycle-series-active-bg',
    series_active_text: '--recycle-series-active-text',
    series_inactive_bg: '--recycle-series-inactive-bg',
    series_inactive_text: '--recycle-series-inactive-text',
    model_head_bg: '--recycle-model-head-bg',
    model_brand_bg: '--recycle-model-brand-bg',
    model_brand_text: '--recycle-model-brand-text'
  }
  return Object.entries(map).map(([key, cssVar]) => `${cssVar}:${merged[key]}`).join(';') + ';'
}
