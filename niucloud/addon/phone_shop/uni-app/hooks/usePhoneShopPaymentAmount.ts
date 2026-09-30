import { onBeforeUnmount } from 'vue'
import { onHide } from '@dcloudio/uni-app'

type Payment = { trade_type?: string, trade_id?: number, money?: string | number }

/**
 * 公共收银台没有请求参数插槽，使用 uni 已有的请求拦截扩展补充核对金额。
 * 只为当前打开的 phone_shop 收银台生效；不改 URL、不改金额来源、不替换公共 API。
 */
export function usePhoneShopPaymentAmount(getPayment: () => Payment | null | undefined) {
    let activeTradeId = 0
    let installed = false
    const interceptor = {
        invoke(options: any) {
            const data = options.data
            let baseUrl = import.meta.env.VITE_APP_BASE_URL
            // #ifdef H5
            baseUrl = baseUrl || `${location.origin}/api/`
            // #endif
            const paymentUrl = baseUrl ? `${String(baseUrl).replace(/\/$/, '')}/pay` : ''
            if (!activeTradeId || String(options.method || 'GET').toUpperCase() !== 'POST'
                || !paymentUrl || String(options.url || '').split('?')[0].replace(/\/$/, '') !== paymentUrl
                || !data || typeof data !== 'object' || data.trade_type !== 'phone_shop') return
            const payment = getPayment()
            if (payment?.trade_type !== 'phone_shop' || !(Number(payment.trade_id) > 0)
                || Number(payment.trade_id) !== Number(data.trade_id)) return
            // 这个字段只用于比较，不参与服务端定价；空值由服务端作非法值处理。
            options.data = { ...data, phone_shop_expected_money: String(payment.money ?? 'invalid') }
        },
    }
    const deactivate = () => {
        activeTradeId = 0
        if (installed) uni.removeInterceptor('request', interceptor)
        installed = false
    }
    const activate = (tradeType: string, tradeId: number) => {
        deactivate()
        if (tradeType !== 'phone_shop' || !(Number(tradeId) > 0)) return
        activeTradeId = Number(tradeId)
        uni.addInterceptor('request', interceptor)
        installed = true
    }
    onHide(deactivate)
    onBeforeUnmount(deactivate)
    return { activate, deactivate }
}
