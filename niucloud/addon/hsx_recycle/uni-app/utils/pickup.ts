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
    title: value?.conflict ? '取件记录待人工核实' : String(value?.title || stateCopy[state].title),
    message: value?.conflict ? '取件记录存在冲突，请联系门店核实，不要重复寄件。' : String(value?.message || stateCopy[state].message),
    carrier_name: String(value?.carrier_name || ''),
    pickup_time: String(value?.pickup_time || ''),
    courier_name: String(value?.courier_name || ''),
    courier_phone: String(value?.courier_phone || ''),
    tracking_no: String(value?.tracking_no || ''),
    // 结果未知、受理中即使收到错误的操作标记，也绝不显示另行叫件入口。
    can_manual: !value?.conflict && !['pending', 'unknown', 'manual_review'].includes(value?.cancellation?.state || '') && value?.can_manual === true && ['failed', 'cancelled'].includes(state),
    can_refresh: value?.can_refresh === true,
  }
}

/** 刷新只是核实原预约，不能以 HTTP 成功推断快递预约成功。 */
export function pickupRefreshFeedback(value?: Partial<PickupInfo>): { message: string; retryAfter: number } {
  const result = value?.refresh_result
  const retryAfter = Math.min(60, Math.max(0, Number(result?.retry_after) || 0))
  if (value?.conflict) return { message: '取件记录存在冲突，请联系门店核实，勿重复寄件', retryAfter }
  if (['pending', 'unknown', 'manual_review'].includes(value?.cancellation?.state || '')) return { message: '快递取消尚未确认，请联系门店核实原预约', retryAfter }
  const messages: Record<string, string> = {
    updated: '已核实渠道状态，请查看最新取件安排',
    throttled: `已显示当前记录，请${retryAfter || 60}秒后再刷新`,
    waiting_callback: '暂未收到渠道预约编号，请联系门店核实，勿重复叫件',
    unavailable: '暂未取得渠道最新状态，原预约已保留',
    not_required: '请按当前订单中的寄件方式办理',
  }
  return { message: messages[result?.status || ''] || '已读取当前记录，请查看取件安排', retryAfter }
}

export function pickupReceiverText(receiver?: PickupInfo['receiver']): string {
  if (!receiver) return ''
  const name = String(receiver.name || receiver.contact_name || '')
  const mobile = String(receiver.mobile || receiver.phone || '')
  const address = String(receiver.full_address || [receiver.province, receiver.city, receiver.district, receiver.address].filter(Boolean).join(''))
  return [name, mobile, address].filter(Boolean).join('，')
}

/** 后台安排的预约时段，最终上门仍需快递员确认。 */
export function validatePickupTime(value: string, required = false, now = new Date()): string {
  const text = value.trim()
  if (!text) return required ? '暂未取得取件时段，请刷新后重试' : ''
  // 动态意图由服务端在下单时计算，不能用手机时钟冻结为已经过去的分钟。
  if (text === 'immediate') return ''
  const match = /^(\d{4})-(\d{2})-(\d{2}) ([01]\d|2[0-3]):([0-5]\d)-([01]\d|2[0-3]):([0-5]\d)$/.exec(text)
  if (!match) return '取件时段信息不完整，请刷新后重试'
  const [, year, month, day, startHour, startMinute, endHour, endMinute] = match.map(Number)
  // 门店时段与顺丰接口统一使用北京时间，不能受客户手机时区影响。
  const calendar = new Date(Date.UTC(year, month - 1, day))
  if (calendar.getUTCFullYear() !== year || calendar.getUTCMonth() !== month - 1 || calendar.getUTCDate() !== day) return '取件日期无效'
  const start = new Date(Date.UTC(year, month - 1, day, startHour - 8, startMinute))
  if (endHour * 60 + endMinute <= startHour * 60 + startMinute) return '结束时间须晚于开始时间'
  if (start.getTime() <= now.getTime()) return '取件时段已更新，请刷新后确认新的时间'
  return ''
}
