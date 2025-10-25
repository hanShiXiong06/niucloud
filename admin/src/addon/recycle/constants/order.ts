import { OrderStatus, DeliveryType, OrderAction } from '../types'

export { OrderStatus, DeliveryType }

export const ORDER_STATUS_TEXT: Record<OrderStatus, string> = {
    [OrderStatus.PENDING]: '待处理',
    [OrderStatus.PENDING_CONFIRM]: '待平台签收',
    [OrderStatus.PENDING_CHECK]: '待质检',
    [OrderStatus.CHECKING]: '质检中',
    [OrderStatus.CHECKED]: '质检完成',
    [OrderStatus.PENDING_PAYMENT]: '待打款',
    [OrderStatus.COMPLETED]: '已完成',
    [OrderStatus.CLOSED]: '已关闭'
}

export const DELIVERY_TYPE_TEXT: Record<DeliveryType, string> = {
    [DeliveryType.MAIL]: '邮寄',
    [DeliveryType.SELF]: '自送'
}

export const ORDER_ACTION: Record<string, OrderAction> = {
    confirm: { type: 'confirm', name: '签收' },
    check: { type: 'check', name: '质检' },
    complete_check: { type: 'complete_check', name: '完成质检' },
    pay: { type: 'pay', name: '打款' },
    complete: { type: 'complete', name: '完成' },
    close: { type: 'close', name: '关闭' }
}

export function getStatusActions(status: OrderStatus): OrderAction[] {
    const actionMap: Record<OrderStatus, string[]> = {
        [OrderStatus.PENDING]: ['confirm', 'close'],
        [OrderStatus.PENDING_CONFIRM]: ['confirm', 'close'],
        [OrderStatus.PENDING_CHECK]: ['check', 'close'],
        [OrderStatus.CHECKING]: ['complete_check', 'close'],
        [OrderStatus.CHECKED]: ['pay', 'close'],
        [OrderStatus.PENDING_PAYMENT]: ['complete', 'close'],
        [OrderStatus.COMPLETED]: [],
        [OrderStatus.CLOSED]: []
    }

    return (actionMap[status] || []).map(type => ORDER_ACTION[type])
}