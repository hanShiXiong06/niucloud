import request from '@/utils/request'

export interface PayeeCustomer {
    member_id: number
    member_no: string
    name: string
    mobile: string
}

export interface PayeeMethod {
    pay_type: string
    account: string
    qrcode_image: string
    is_default: number
}

export function getErpPayeeCustomers(params: { keyword: string; page: number; limit: number }) {
    return request.get('erp/finance/payee/customers', { params, showErrorMessage: false, timeout: 15000 })
}

export function getErpCustomerPayee(memberId: number) {
    return request.get(`erp/finance/payee/customer/${memberId}`, { showErrorMessage: false, timeout: 15000 })
}
