import type { PickupInfo, PickupState } from '../types/order'

const stateCopy: Record<PickupState, { title: string; message: string }> = {
  not_requested: { title: '未预约上门取件', message: '回收订单与取件预约独立，请按已确认的交付方式寄送，疑问请联系门店。' },
  submitting: { title: '预约提交中', message: '正在确认取件预约，请勿重复叫件或自行另叫快递。' },
  accepted: { title: '预约已受理，待确认', message: '取件申请已受理，尚未确认预约成功，请等待更新，勿重复叫件。' },
  confirmed: { title: '取件预约已确认', message: '请准备好设备并保持电话畅通，取件员信息将继续更新。' },
  assigned: { title: '已分配取件员', message: '请保持电话畅通；如需确认上门安排，可联系取件员。' },
  picked_up: { title: '快递已揽收', message: '设备已由快递取走，请关注物流进度。' },
  in_transit: { title: '运输中', message: '设备正在寄往门店，请关注物流进度。' },
  delivered: { title: '物流已送达', message: '物流送达不等于门店验收，请等待门店更新回收进度。' },
  cancelled: { title: '取件预约已取消', message: '请确认后续寄件方式，是否可自行寄件以订单页面提示为准。' },
  failed: { title: '取件预约失败', message: '回收订单已保留，请查看下方处理入口，或联系门店确认寄件方式。' },
  unknown: { title: '预约结果待核实', message: '暂时无法确认预约结果，请联系门店核实。请勿重复叫件或自行另叫快递。' },
  exception: { title: '取件或运输异常', message: '请联系门店核实当前取件或物流情况，不要自行重复寄件。' },
  manual: { title: '已登记自行寄件', message: '运单已关联当前回收订单，请关注物流及门店收件进度。' }
}

export function normalizePickup(value?: Partial<PickupInfo> | null): PickupInfo {
  const state = value?.state && Object.prototype.hasOwnProperty.call(stateCopy, value.state)
    ? value.state : (value ? 'unknown' : 'not_requested')
  return {
    ...value,
    state,
    title: String(value?.title || stateCopy[state].title),
    message: String(value?.message || stateCopy[state].message),
    carrier_name: String(value?.carrier_name || ''),
    pickup_time: String(value?.pickup_time || ''),
    courier_name: String(value?.courier_name || ''),
    courier_phone: String(value?.courier_phone || ''),
    tracking_no: String(value?.tracking_no || ''),
    // 结果未知、受理中即使收到错误的操作标记，也绝不显示另行叫件入口。
    can_manual: value?.can_manual === true && ['failed', 'cancelled'].includes(state),
    can_refresh: value?.can_refresh === true,
  }
}

export function pickupReceiverText(receiver?: PickupInfo['receiver']): string {
  if (!receiver) return ''
  const name = String(receiver.name || receiver.contact_name || '')
  const mobile = String(receiver.mobile || receiver.phone || '')
  const address = String(receiver.full_address || [receiver.province, receiver.city, receiver.district, receiver.address].filter(Boolean).join(''))
  return [name, mobile, address].filter(Boolean).join('，')
}

/** 客户选择的是申请时段，最终安排仍由门店所配置的寄件服务确认。 */
export function validatePickupTime(value: string, required = false, now = new Date()): string {
  const text = value.trim()
  if (!text) return required ? '请选择期望取件日期和时段' : ''
  const match = /^(\d{4})-(\d{2})-(\d{2}) ([01]\d|2[0-3]):([0-5]\d)-([01]\d|2[0-3]):([0-5]\d)$/.exec(text)
  if (!match) return '请选择完整的日期和取件时段'
  const [, year, month, day, startHour, startMinute, endHour, endMinute] = match.map(Number)
  const start = new Date(year, month - 1, day, startHour, startMinute)
  if (start.getFullYear() !== year || start.getMonth() !== month - 1 || start.getDate() !== day) return '取件日期无效'
  if (endHour * 60 + endMinute <= startHour * 60 + startMinute) return '结束时间须晚于开始时间'
  if (start.getTime() <= now.getTime()) return '请选择尚未开始的取件时段'
  return ''
}
