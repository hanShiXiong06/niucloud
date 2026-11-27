export const PICKUP_NOTICE_KEY = 'hsx_recycle_pickup_update'

export type PickupReceiptRow = {
    id: number | string
    time: string
    channel: string
    order: string
    status: 'recorded'
    statusText: string
    tagType: 'info'
    message: string
}

const objectValue = (value: unknown): Record<string, any> => {
    if (typeof value === 'string') {
        try { value = JSON.parse(value) } catch { return {} }
    }
    return value && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, any> : {}
}

const textValue = (value: unknown): string => typeof value === 'string' || typeof value === 'number' ? String(value).trim() : ''

const displayTime = (value: unknown): string => {
    const raw = textValue(value)
    if (!raw) return '—'
    if (!/^\d{10,13}$/.test(raw)) return raw
    const timestamp = Number(raw)
    const date = new Date(timestamp < 1e12 ? timestamp * 1000 : timestamp)
    if (Number.isNaN(date.getTime())) return '—'
    const two = (part: number) => String(part).padStart(2, '0')
    return `${date.getFullYear()}-${two(date.getMonth() + 1)}-${two(date.getDate())} ${two(date.getHours())}:${two(date.getMinutes())}:${two(date.getSeconds())}`
}

// 框架日志没有可靠的微信发送回执：不能把日志存在、HTTP 200、is_click 或旧标记当作送达证据。
export function toPickupReceiptRow(input: Record<string, any>): PickupReceiptRow {
    const params = objectValue(input.params)
    // 框架小程序日志为 params.vars，公众号日志直接以 params 保存 vars。
    const nestedVars = objectValue(params.vars)
    const vars = Object.keys(nestedVars).length ? nestedVars : params
    const receipt = objectValue(params.receipt)
    const orderId = textValue(params.order_id)
    const failure = textValue(input.result) || (receipt.send_accepted === false ? textValue(receipt.message) : '')
    return {
        id: typeof input.id === 'number' || typeof input.id === 'string' ? input.id : '',
        time: displayTime(input.create_time),
        channel: input.notice_type === 'weapp' ? '微信小程序' : input.notice_type === 'wechat' ? '微信公众号' : '未知渠道',
        order: textValue(vars.order_no) || (orderId ? `订单 ID：${orderId}` : '—'),
        status: 'recorded',
        statusText: '框架已记录',
        tagType: 'info',
        message: failure
            ? `记录说明：${failure}；送达未确认，请核实后处理，不要直接重发`
            : '送达未确认：框架已记录本次通知执行，不能据此判定微信受理、客户收到或已读'
    }
}
