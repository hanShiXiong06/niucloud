import { formatMoney } from './helper'

const DEVICE_STATUS_PENDING_CONFIRM = 4
const DEVICE_STATUS_RECYCLED = 5
const DEVICE_STATUS_RETURNED = 6
const DEVICE_STATUS_PRICED = 7
const DEVICE_STATUS_PRICED_REPRICE = 8
const DEVICE_STATUS_CONSIGNED = 9

const CONFIRM_STATUS_PENDING = 0
const CONFIRM_STATUS_CONFIRMED = 1
const CONFIRM_STATUS_REJECTED = 2

const DISPOSE_STATUS_RETURNED = 2

export const isConsignedDevice = (device: Record<string, any> = {}) => {
    return device.dispose_type === 'consign'
        || Number(device.status || 0) === DEVICE_STATUS_CONSIGNED
        || Number(device.consignment_order_id || 0) > 0
}

export const isReturnedDevice = (device: Record<string, any> = {}) => {
    return Number(device.status || 0) === DEVICE_STATUS_RETURNED
        || Number(device.confirm_status || 0) === CONFIRM_STATUS_REJECTED
        // 兜底：设备 status 字段未跟上时，按处置维度判定已退回
        || Number(device.dispose_status || 0) === DISPOSE_STATUS_RETURNED
        || device.dispose_type === 'return'
}

export const getDeviceQuoteAmount = (device: Record<string, any> = {}) => {
    const finalPrice = Number(device.final_price || 0)
    if (finalPrice > 0) return finalPrice

    const initialPrice = Number(device.initial_price || 0)
    if (initialPrice > 0) return initialPrice

    return 0
}

export const getDeviceSettlementAmount = (device: Record<string, any> = {}) => {
    if (isConsignedDevice(device)) return 0
    return getDeviceQuoteAmount(device)
}

export const hasQuotedDevice = (device: Record<string, any> = {}) => {
    const status = Number(device.status || 0)
    if (isConsignedDevice(device)) return true
    if (Number(device.final_price || 0) > 0) return true
    if (Number(device.confirm_status || 0) !== CONFIRM_STATUS_PENDING) return true

    return [
        DEVICE_STATUS_PENDING_CONFIRM,
        DEVICE_STATUS_RECYCLED,
        DEVICE_STATUS_PRICED,
        DEVICE_STATUS_PRICED_REPRICE
    ].includes(status)
}

export const shouldShowConfirmStatus = (device: Record<string, any> = {}) => {
    if (isReturnedDevice(device)) return true
    return hasQuotedDevice(device)
}

export const shouldShowInitialPrice = (device: Record<string, any> = {}) => {
    if (isConsignedDevice(device)) return false
    const initialPrice = Number(device.initial_price || 0)
    const finalPrice = Number(device.final_price || 0)
    return initialPrice > 0 && finalPrice > 0 && initialPrice !== finalPrice
}

export const getDevicePriceLabel = (device: Record<string, any> = {}) => {
    if (isReturnedDevice(device)) return device.status_name || '已退回'
    if (isConsignedDevice(device)) {
        const consignment = device.consignmentOrder || {}
        const listingPrice = Number(consignment.listing_price || 0)
        if (listingPrice > 0) return `挂牌价 ¥${formatMoney(listingPrice)}`
        return '已转代卖'
    }

    const amount = getDeviceQuoteAmount(device)
    if (amount > 0) return `¥${formatMoney(amount)}`
    return '待定价'
}

export const getDevicePriceSubLabel = (device: Record<string, any> = {}) => {
    if (isReturnedDevice(device)) return ''
    if (isConsignedDevice(device)) {
        const consignment = device.consignmentOrder || {}
        const statusName = consignment.status_name || device.dispose_status_name || ''
        const soldPrice = Number(consignment.sold_price || 0)
        const settlementAmount = Number(consignment.settlement_amount || 0)

        if (settlementAmount > 0) return `结算 ¥${formatMoney(settlementAmount)}`
        if (soldPrice > 0) return `成交 ¥${formatMoney(soldPrice)}`
        return statusName || '代卖处理中'
    }

    if (shouldShowInitialPrice(device)) {
        return `初始报价 ¥${formatMoney(device.initial_price || 0)}`
    }

    return ''
}

export const getDeviceListPriceMeta = (device: Record<string, any> = {}) => {
    const initialPrice = Number(device.initial_price || 0)
    const finalPrice = Number(device.final_price || 0)
    const payAmount = Number(device.pay_amount || 0)

    if (isReturnedDevice(device)) {
        const referencePrice = finalPrice > 0 ? finalPrice : initialPrice
        return {
            label: device.status_name || '已退回',
            subLabel: referencePrice > 0 ? `参考 ¥${formatMoney(referencePrice)}` : '',
            tone: 'muted'
        }
    }

    if (isConsignedDevice(device)) {
        const consignment = device.consignmentOrder || {}
        const settlementAmount = Number(consignment.settlement_amount || 0)
        const soldPrice = Number(consignment.sold_price || 0)
        const listingPrice = Number(consignment.listing_price || 0)
        const referencePrice = settlementAmount > 0 ? settlementAmount : (soldPrice > 0 ? soldPrice : listingPrice)

        return {
            label: consignment.status_name || device.dispose_status_name || '已转代卖',
            subLabel: referencePrice > 0 ? `代卖参考 ¥${formatMoney(referencePrice)}` : '',
            tone: 'muted'
        }
    }

    if (payAmount > 0) {
        return {
            label: `已打款 ¥${formatMoney(payAmount)}`,
            subLabel: finalPrice > 0 && finalPrice !== payAmount ? `定价 ¥${formatMoney(finalPrice)}` : '',
            tone: 'strong'
        }
    }

    if (finalPrice > 0) {
        return {
            label: `定价 ¥${formatMoney(finalPrice)}`,
            subLabel: initialPrice > 0 && initialPrice !== finalPrice ? `质检 ¥${formatMoney(initialPrice)}` : '',
            tone: 'strong'
        }
    }

    if (initialPrice > 0) {
        return {
            label: `质检 ¥${formatMoney(initialPrice)}`,
            subLabel: '待定价',
            tone: 'info'
        }
    }

    return {
        label: '待定价',
        subLabel: '',
        tone: 'info'
    }
}

export const buildDeviceFlowHighlights = (device: Record<string, any> = {}) => {
    const highlights: Array<{ label: string, value: string }> = []

    // 已退回：终态，只显示拒绝/退回，不再露"报价已进入报价阶段"等误导信息
    if (isReturnedDevice(device)) {
        if (device.confirm_status_name) highlights.push({ label: '客户确认', value: device.confirm_status_name })
        highlights.push({ label: '处置', value: device.dispose_status_name || device.status_name || '已退回' })
        return highlights
    }

    if (device.check_at || device.check_result || device.check_result_seller || device.check_result_buyer) {
        highlights.push({ label: '质检', value: '已完成' })
    }

    if (hasQuotedDevice(device)) {
        highlights.push({ label: '报价', value: Number(device.final_price || 0) > 0 ? `¥${formatMoney(device.final_price || 0)}` : '已进入报价阶段' })
    }

    if (shouldShowConfirmStatus(device) && device.confirm_status_name) {
        highlights.push({ label: '客户确认', value: device.confirm_status_name })
    }
    // 只有打款后才会显示
    if ( Number(device.pay_status) === 1 ) {
        highlights.push({ label: '打款', value: device.pay_status_name || '已打款' })
    }

    if (isConsignedDevice(device)) {
        highlights.push({ label: '代卖', value: device.consignmentOrder?.status_name || device.dispose_status_name || '已转代卖' })
    }

    return highlights
}
