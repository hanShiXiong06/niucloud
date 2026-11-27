import request from '@/utils/request'

export type PaymentHelp = { enabled: boolean; title: string; content: string; qr_image: string; remark: string }
// A read-only, authenticated endpoint; never creates or confirms a payment.
export const getPaymentHelp = () => request.get('hsx_lead/cooperation/payment-help', {}, { showErrorMessage: false })
