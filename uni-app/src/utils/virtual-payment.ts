/** Optional cashier client action. Business eligibility and fulfillment remain server-owned. */
export const WECHAT_VIRTUAL_PAYMENT_ACTION = 'wechat_virtual_payment'

type NativeCallback = (result: any) => void
export interface VirtualPaymentBridge {
    login: (options: { success: NativeCallback; fail: NativeCallback }) => void
    requestVirtualPayment: (options: Record<string, unknown>) => void
    canIUse?: (name: string) => boolean
}

export class VirtualPaymentClientError extends Error {
    constructor(public readonly code: 'unsupported' | 'cancelled' | 'login_failed' | 'invalid_response' | 'payment_failed', message: string) {
        super(message)
        this.name = 'VirtualPaymentClientError'
    }
}

const record = (value: unknown): value is Record<string, any> => value !== null && typeof value === 'object' && !Array.isArray(value)

function requireBridge(bridge?: VirtualPaymentBridge): VirtualPaymentBridge {
    if (!bridge || typeof bridge.login !== 'function' || typeof bridge.requestVirtualPayment !== 'function'
        || (typeof bridge.canIUse === 'function' && !bridge.canIUse('requestVirtualPayment'))) {
        throw new VirtualPaymentClientError('unsupported', '当前客户端暂不支持此付款，请更新微信后在小程序内重试')
    }
    return bridge
}

/** Get a new login code for every explicit attempt. Never cache code, AppKey or sessionKey. */
export async function preparePaymentClientRequest(action: unknown, bridge?: VirtualPaymentBridge): Promise<{ login_code?: string }> {
    if (action === undefined || action === null || action === '') return {}
    if (action !== WECHAT_VIRTUAL_PAYMENT_ACTION) {
        throw new VirtualPaymentClientError('unsupported', '当前付款方式暂不支持，请刷新订单后重试')
    }
    const native = requireBridge(bridge)
    return new Promise((resolve, reject) => {
        try {
            native.login({
                success(result) {
                    const code = result?.code
                    if (typeof code !== 'string' || code.trim() === '' || code.length > 256 || /[\s\x00-\x1f\x7f]/.test(code)) {
                        reject(new VirtualPaymentClientError('login_failed', '微信身份校验未完成，请重新点击付款'))
                        return
                    }
                    resolve({ login_code: code })
                },
                fail() { reject(new VirtualPaymentClientError('login_failed', '微信身份校验未完成，请重新点击付款')) }
            })
        } catch (_) {
            reject(new VirtualPaymentClientError('login_failed', '微信身份校验未完成，请重新点击付款'))
        }
    })
}

export function parseVirtualPaymentData(response: unknown): Record<string, string> {
    if (!record(response) || response.client_action !== WECHAT_VIRTUAL_PAYMENT_ACTION || !record(response.pay_data)) {
        throw new VirtualPaymentClientError('invalid_response', '未取得完整付款参数，请刷新订单后重试')
    }
    const data = response.pay_data
    if (data.mode !== 'short_series_goods' || typeof data.signData !== 'string' || !data.signData || data.signData.length > 8192
        || typeof data.paySig !== 'string' || !/^[a-f0-9]{64}$/i.test(data.paySig)
        || typeof data.signature !== 'string' || !/^[a-f0-9]{64}$/i.test(data.signature)) {
        throw new VirtualPaymentClientError('invalid_response', '未取得完整付款参数，请刷新订单后重试')
    }
    // Parse for shape only. Do not reserialize: the exact signData bytes are signed by the server.
    let signed: unknown
    try { signed = JSON.parse(data.signData) } catch (_) { signed = null }
    if (!record(signed) || typeof signed.offerId !== 'string' || !signed.offerId || signed.currencyType !== 'CNY'
        || !Number.isSafeInteger(signed.goodsPrice) || signed.goodsPrice <= 0 || signed.buyQuantity !== 1
        || ![0, 1].includes(signed.env) || typeof signed.productId !== 'string' || !signed.productId
        || typeof signed.outTradeNo !== 'string' || !/^[A-Za-z0-9-][A-Za-z0-9_-]{7,31}$/.test(signed.outTradeNo)) {
        throw new VirtualPaymentClientError('invalid_response', '付款参数格式异常，请刷新订单后重试')
    }
    // Pass only the four official client fields; no returned callback/function/extra data is executed.
    return { mode: data.mode, signData: data.signData, paySig: data.paySig, signature: data.signature }
}

/** A native success means only that the UI completed; caller must query the server order. */
export async function executePaymentClientAction(action: unknown, response: unknown, bridge?: VirtualPaymentBridge): Promise<void> {
    if (action !== WECHAT_VIRTUAL_PAYMENT_ACTION) {
        throw new VirtualPaymentClientError('unsupported', '当前付款方式暂不支持，请刷新订单后重试')
    }
    const native = requireBridge(bridge)
    const data = parseVirtualPaymentData(response)
    return new Promise((resolve, reject) => {
        try {
            native.requestVirtualPayment({
                ...data,
                success() { resolve() },
                fail(result: any) {
                    const cancelled = typeof result?.errMsg === 'string' && /cancel/i.test(result.errMsg)
                    reject(new VirtualPaymentClientError(cancelled ? 'cancelled' : 'payment_failed', cancelled
                        ? '已退出付款，请返回原订单核对结果' : '付款结果尚未确认，请返回原订单核对'))
                }
            })
        } catch (_) {
            reject(new VirtualPaymentClientError('payment_failed', '付款尚未完成，请查看订单后重试'))
        }
    })
}
