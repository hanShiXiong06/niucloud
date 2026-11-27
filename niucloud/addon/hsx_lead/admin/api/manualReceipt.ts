import request from '@/utils/request'
import type { CoopRow } from '../composables/cooperation'

const root = 'hsx_lead/cooperation',
    quiet = { showErrorMessage: false }
export type ManualReceiptConfirmInput = {
    amount: string
    request_id: string
    expected_fingerprint: string
    confirm: true
}
export const previewManualReceipt = (id: number, request_id = '') =>
    request.get(`${root}/orders/${id}/manual-receipt`, { ...quiet, params: request_id ? { request_id } : {} })
export const confirmManualReceipt = (id: number, data: ManualReceiptConfirmInput) =>
    request.post(
        `${root}/orders/${id}/manual-receipt`,
        {
            amount: data.amount,
            request_id: data.request_id,
            expected_fingerprint: data.expected_fingerprint,
            confirm: data.confirm
        },
        { ...quiet, timeout: 45000 }
    )
export const getManualReceipt = (id: number) => request.get(`${root}/manual-receipts/${id}`, quiet)
export const retryManualReceipt = (id: number) =>
    request.post(`${root}/manual-receipts/${id}/retry`, { confirm: true }, { ...quiet, timeout: 45000 })
export const confirmManualRefund = (id: number, data: CoopRow) =>
    request.post(`${root}/orders/${id}/manual-refund`, data, { ...quiet, timeout: 45000 })
