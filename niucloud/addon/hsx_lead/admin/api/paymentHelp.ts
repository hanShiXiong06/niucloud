import request from '@/utils/request'

export type PaymentHelpSettings = { enabled: boolean; title: string; content: string; qr_image: string; remark: string }
const endpoint = 'hsx_lead/cooperation/config'
const quiet = { showErrorMessage: false }
export const getPaymentHelpSettings = () => request.get(endpoint, { ...quiet, params: { section: 'payment_help' } })
export const savePaymentHelpSettings = (data: PaymentHelpSettings) =>
    request.put(endpoint, { ...data, section: 'payment_help' }, quiet)
