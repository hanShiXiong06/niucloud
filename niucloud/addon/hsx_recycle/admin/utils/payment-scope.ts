export type PaymentOwner = 'local' | 'self_erp' | 'mixed' | 'unknown'

export interface PaymentScope {
    payment_owner?: PaymentOwner
    local_allowed?: boolean
    payment_path?: string
    message?: string
    devices?: Array<{ device_id: number | string; owner: 'local' | 'self_erp' | 'unknown'; pay_status?: number; erp?: Record<string, any> }>
}

// 混合归属只能沿用订单原本的按设备模式，不能把整单订单强制改成按设备付款。
export function canOpenLocalPayment(scope: PaymentScope, mode: string) {
    return (scope.payment_owner === 'local' && scope.local_allowed === true)
        || (scope.payment_owner === 'mixed' && mode === 'device')
}

export function getDevicePaymentOwner(scope: PaymentScope, deviceId: number | string) {
    return scope.devices?.find(item => String(item.device_id) === String(deviceId))?.owner || 'unknown'
}
