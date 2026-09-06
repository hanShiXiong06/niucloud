/** Display-only labels. Identifiers remain available to API requests and searches. */
const businessLabels: Record<string, string> = {
    hsx_erp: 'ERP业务', erp: 'ERP业务', hsx_recycle: '回收业务', recycle: '回收业务',
    phone_shop: '商城业务', hsx_phone_shop: '商城业务', '回收插件': '回收业务',
    '回收插件采购': '回收入库', '回收插件入库': '回收入库', '回收插件销售': '回收销售',
    purchase: '采购入库', purchase_asset: '采购入库', sale: '销售出库',
    purchase_return: '采购退货', sale_return: '销售退货', sale_compensation: '售后补差',
    after_sale_compensation: '售后补差', refurbish: '设备整备',
    operating: '经营收支', operating_expense: '经营支出', operating_income: '经营收入',
    sale_receivable: '销售应收', purchase_return_receivable: '采购退货应收',
    purchase_payable: '采购应付', sale_return_payable: '销售退货应付',
    sale_compensation_payable: '售后补差应付', refurbish_payable: '整备费用应付',
    receivable: '应收款', payable: '应付款', payment: '付款', receipt: '收款', offset: '折账'
}

function text(value: unknown) { return String(value ?? '').trim() }
function technicalSource(value: string) {
    return /hsx_[a-z0-9_]+/i.test(value) || /^[a-z][a-z0-9]*(?:[_:.][a-z0-9]+)+$/i.test(value)
}

/** Use only for business-source/type fields, never for names, models or order numbers. */
export function erpSourceLabel(value: unknown, fallback = '其他业务') {
    const label = text(value)
    return businessLabels[label] || (!label || technicalSource(label) ? fallback : label)
}

/** A missing option name must not fall back to its internal value. */
export function erpNamedLabel(name: unknown, key: unknown, fallback = '其他业务', labels: Record<string, string> = {}) {
    const label = text(name)
    const code = text(key)
    const internalName = /hsx_[a-z0-9_]+/i.test(label) || (label === code && technicalSource(label))
    return labels[label] || businessLabels[label] || (label && !internalName ? label : '')
        || labels[code] || businessLabels[code] || fallback
}

export function erpEnumLabel(value: unknown, labels: Record<string, string>, fallback = '状态待确认') {
    const label = text(value)
    const translated = text(labels[label]) || label
    return /\p{Script=Han}/u.test(translated) && !technicalSource(translated) ? translated : fallback
}

export function erpSerialText(row: Record<string, any> | null | undefined, fallback = '未录入 IMEI / SN') {
    return [row?.imei ? `IMEI ${row.imei}` : '', row?.sn ? `SN ${row.sn}` : ''].filter(Boolean).join(' · ') || fallback
}

export function isInternalErpColumn(key: unknown) {
    return /(?:^|_)(?:id|uid|ids|uids)$/.test(text(key)) || text(key) === 'asset_no'
        || /(?:^|_)(?:source|origin)_plugin(?:_name)?$/.test(text(key))
}
