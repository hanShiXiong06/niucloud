import { businessAmountCents } from './business'

export const manualMethods = [
    { value: 'bank', label: '银行转账' },
    { value: 'wechat', label: '微信转账' },
    { value: 'alipay', label: '支付宝转账' },
    { value: 'cash', label: '现金' },
    { value: 'other', label: '其他已到账方式' }
]
export type ManualEvidence = {
    amount: string
    method: string
    account_label: string
    external_no: string
    received_at: string
    reason: string
    confirm: boolean
}
export const emptyManualEvidence = (): ManualEvidence => ({
    amount: '',
    method: '',
    account_label: '',
    external_no: '',
    received_at: '',
    reason: '',
    confirm: false
})
export function manualEvidenceError(value: ManualEvidence, now = Date.now()) {
    if (
        !value ||
        ['amount', 'method', 'account_label', 'external_no', 'reason'].some(
            (key) => typeof value[key as keyof ManualEvidence] !== 'string'
        )
    )
        return '收退款资料未完整填写，请核对表单'
    const cents = businessAmountCents(value.amount)
    if (cents === null || cents < 1 || cents > 9999999999) return '请填写大于零的实际金额，最多两位小数'
    if (!manualMethods.some((item) => item.value === value.method)) return '请选择实际使用的收退款方式'
    if (!value.account_label.trim() || [...value.account_label.trim()].length > 100)
        return '请填写100字以内的实际账户或现金经办信息'
    if (!value.external_no.trim() || [...value.external_no.trim()].length > 100)
        return '请填写100字以内的交易流水号；现金也需要收据号'
    const time = Number(value.received_at)
    if (!Number.isSafeInteger(time) || time <= 0 || time > Math.floor(now / 1000) + 60)
        return '请选择有效的实际到账时间，不能晚于当前时间'
    if ([...value.reason.trim()].length < 4 || [...value.reason.trim()].length > 500) return '请填写4至500字的核对说明'
    if (value.confirm !== true) return '请先勾选已核实实际到账的确认项'
    return ''
}
export function manualEvidencePayload(value: ManualEvidence) {
    const error = manualEvidenceError(value)
    if (error) throw Error(error)
    return {
        amount: (businessAmountCents(value.amount)! / 100).toFixed(2),
        method: value.method,
        account_label: value.account_label.trim(),
        external_no: value.external_no.trim(),
        received_at: Number(value.received_at),
        reason: value.reason.trim(),
        confirm: true as const
    }
}
export const manualRequestId = () => `manual_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 18)}`
