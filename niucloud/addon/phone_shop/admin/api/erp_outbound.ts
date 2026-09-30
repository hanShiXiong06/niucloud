import request from '@/utils/request'

/**
 * 商城开单 = 直接调用 ERP 出库逻辑（线下成交）。
 * 出库成功后 ERP 发事件，商城监听器自动建订单 + 商品置已售/锁定。
 */

// 交易人(客户)选项 —— 静默失败(ERP 不可用时不弹错、dialog 照常)
export function getErpCounterpartyOptions(keyword = '') {
    return request.get('erp/counterparty/options', { params: { keyword }, showErrorMessage: false })
}

// ERP 会员/往来主体候选 —— 静默失败
export function getErpMemberOptions(params: Record<string, any> = {}) {
    return request.get('erp/counterparty/member_options', { params, showErrorMessage: false })
}

// 解析/创建 ERP 往来主体
export function resolveErpContact(params: Record<string, any>) {
    return request.post('erp/counterparty/resolve_contact', params, { showErrorMessage: true })
}

// 资金账户(现结收款户头) —— 静默失败
export function getErpCapitalAccounts() {
    return request.get('erp/capital_account/lists', { showErrorMessage: false })
}

// ERP 结算配置 —— 静默失败(ERP 未安装时由调用方按默认值兜底)
export function getErpSettleConfig() {
    return request.get('erp/config', { showErrorMessage: false })
}

// 快速新建交易人(手机号/姓名)
export function erpQuickContact(params: Record<string, any>) {
    return request.post('erp/counterparty/quick_contact', params)
}

// 出库开单
export function erpOutboundCreate(params: Record<string, any>) {
    return request.post('erp/outbound/create', params, { showErrorMessage: true })
}
