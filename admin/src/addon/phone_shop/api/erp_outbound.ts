import request from '@/utils/request'

/**
 * 商城开单 = 直接调用 ERP 出库逻辑（线下成交）。
 * 出库成功后 ERP 发事件，商城监听器自动建订单 + 商品置已售/锁定。
 */

// 交易人(客户)选项
export function getErpCounterpartyOptions(keyword = '') {
    return request.get('hsx_erp/counterparty/options', { params: { keyword } })
}

// 资金账户(现结收款户头)
export function getErpCapitalAccounts() {
    return request.get('hsx_erp/capital_account/lists')
}

// 快速新建交易人(手机号/姓名)
export function erpQuickContact(params: Record<string, any>) {
    return request.post('hsx_erp/counterparty/quick_contact', params)
}

// 出库开单
export function erpOutboundCreate(params: Record<string, any>) {
    return request.post('hsx_erp/outbound/create', params, { showErrorMessage: true })
}
