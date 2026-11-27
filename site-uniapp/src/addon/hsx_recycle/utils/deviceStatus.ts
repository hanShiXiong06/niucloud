/**
 * 设备状态 → 文案 / 下一步动作 / 配色 的统一映射。
 * 对齐 admin/src/addon/hsx_recycle/hooks/useRecycleOrderUi.ts。
 *
 * 把状态机翻译成「现在该做什么」，供状态徽章（DeviceStatusBadge）、
 * 操作收敛、引导提示等复用，保证 PC 与移动端语义一致。
 *
 * 配色统一走 premium-tokens.scss 的 --hsx-* 令牌（柔和药丸：浅底 + 同色字）。
 */

export type DeviceStatusTone = 'info' | 'warning' | 'primary' | 'success' | 'danger' | 'purple'

/** 设备状态码 → 色调（对齐 admin DEVICE_STATUS_TYPE_MAP，并补全 7/8/9）。 */
const DEVICE_STATUS_TONE_MAP: Record<number, DeviceStatusTone> = {
  1: 'info',     // 待质检
  2: 'warning',  // 质检中
  3: 'primary',  // 已质检
  4: 'success',  // 待确认
  5: 'success',  // 已回收
  6: 'danger',   // 已退回
  7: 'primary',  // 已定价
  8: 'primary',  // 已定价（重新定价）
  9: 'purple'    // 已转代卖
}

/** 设备状态文案兜底（后端通常返回 status_name，此处用于缺失时兜底）。 */
const DEVICE_STATUS_TEXT_MAP: Record<number, string> = {
  1: '待质检',
  2: '质检中',
  3: '已质检',
  4: '待确认',
  5: '已回收',
  6: '已退回',
  7: '已定价',
  8: '已定价',
  9: '已转代卖'
}

/** 设备状态 → 下一步动作提示。给店员明确指引。 */
const DEVICE_STATUS_NEXT_MAP: Record<number, string> = {
  1: '下一步：开始质检',
  2: '下一步：完成质检',
  3: '下一步：回收定价',
  4: '下一步：确认回收（或重新定价 / 拒绝）',
  5: '已回收，等待打款与入库',
  6: '已退回，流程结束',
  7: '下一步：确认回收',
  8: '下一步：确认回收',
  9: '已转代卖，按代卖流程跟进'
}

/** 色调 → CSS 令牌（柔和药丸：浅底 + 同色字）。 */
const TONE_TOKEN_MAP: Record<DeviceStatusTone, { color: string; bg: string }> = {
  info:    { color: 'var(--hsx-info)',    bg: 'var(--hsx-info-bg)' },
  warning: { color: 'var(--hsx-warning)', bg: 'var(--hsx-warning-bg)' },
  primary: { color: 'var(--hsx-primary)', bg: 'var(--hsx-primary-50)' },
  success: { color: 'var(--hsx-success)', bg: 'var(--hsx-success-bg)' },
  danger:  { color: 'var(--hsx-danger)',  bg: 'var(--hsx-danger-bg)' },
  purple:  { color: 'var(--hsx-purple)',  bg: 'var(--hsx-purple-bg)' }
}

const toStatus = (status: number | string | null | undefined): number => Number(status || 0)

/** 设备状态色调。 */
export const getDeviceStatusTone = (status: number | string): DeviceStatusTone =>
  DEVICE_STATUS_TONE_MAP[toStatus(status)] || 'info'

/** 设备状态文案兜底。优先用后端 status_name，缺失时回退到本表。 */
export const getDeviceStatusText = (status: number | string, statusName?: string): string =>
  statusName || DEVICE_STATUS_TEXT_MAP[toStatus(status)] || ''

/** 设备状态 → 下一步动作提示。 */
export const getDeviceNextStep = (status: number | string): string =>
  DEVICE_STATUS_NEXT_MAP[toStatus(status)] || ''

/** 设备状态 → 配色令牌（{ color, bg }），供徽章内联样式使用。 */
export const getDeviceStatusTokens = (status: number | string): { color: string; bg: string } =>
  TONE_TOKEN_MAP[getDeviceStatusTone(status)]

/** 任意色调 → 配色令牌（供订单状态等其它场景复用）。 */
export const getToneTokens = (tone: DeviceStatusTone): { color: string; bg: string } =>
  TONE_TOKEN_MAP[tone] || TONE_TOKEN_MAP.info
