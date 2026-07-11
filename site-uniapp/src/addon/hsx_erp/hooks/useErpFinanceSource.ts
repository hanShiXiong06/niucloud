export type ErpFinanceDirection = 'payable' | 'receivable'

export type ErpFinanceSourceMeta = {
    finance_type_key: string
    finance_type_name: string
    direction: string
    biz_scene: string
    business_source_key: string
    business_source_name: string
    source_plugin: string
    source_plugin_name: string
    channel_code: string
    channel_name: string
    source_no: string
    internal_source_no: string
    party_role_label: string
    business_reason: string
}

const PAYABLE_SOURCE_OPTIONS = [
    { label: '采购入库', value: 'purchase' },
    { label: '客户退货 / 售后', value: 'sale_return' },
    { label: '整备支出', value: 'refurbish' },
]

const RECEIVABLE_SOURCE_OPTIONS = [
    { label: '销售收款', value: 'sale' },
    { label: '采购退货退款', value: 'purchase_return' },
]

export function erpFinanceSourceFilterOptions(direction: ErpFinanceDirection) {
    return direction === 'payable' ? PAYABLE_SOURCE_OPTIONS : RECEIVABLE_SOURCE_OPTIONS
}

/**
 * 财务来源统一解析器。
 * 优先使用后端 source_meta；兼容历史接口字段，避免 PC / 移动端再次各写一套业务判断。
 */
export function erpFinanceSourceMeta(row: any, direction: ErpFinanceDirection): ErpFinanceSourceMeta {
    const raw = normalizeMeta(row?.source_meta)
    const originKey = String(raw.business_source_key || '')
    const sourceType = String(raw.biz_scene || row?.source_type || inferScene(originKey, direction))
    const compensation = sourceType === 'after_sale_compensation'
        || String(row?.sale_return_business_type || row?.business_type || '') === 'after_sale_compensation'

    const fallback = fallbackMeta(sourceType, direction, compensation)
    const isPurchase = sourceType === 'purchase' || sourceType === 'purchase_asset'
    const isSale = sourceType === 'sale'

    return {
        finance_type_key: String(raw.finance_type_key || fallback.finance_type_key),
        finance_type_name: readableName(raw.finance_type_name || row?.source_label, fallback.finance_type_name),
        direction: String(raw.direction || (direction === 'payable' ? 'out' : 'in')),
        biz_scene: sourceType || fallback.biz_scene,
        business_source_key: originKey || fallback.business_source_key,
        business_source_name: readableName(raw.business_source_name, fallback.business_source_name),
        source_plugin: String(raw.source_plugin || ''),
        source_plugin_name: String(raw.source_plugin_name || raw.source_plugin || ''),
        channel_code: String(raw.channel_code || row?.sale_channel_key || ''),
        channel_name: String(raw.channel_name || (isSale ? row?.sale_channel : '') || (isPurchase ? row?.purchase_channel : '') || ''),
        source_no: String(raw.source_no || row?.source_no || row?.batch_no || row?.sale_no || row?.purchase_no || row?.payable_source_no || row?.receivable_no || '').trim(),
        internal_source_no: String(raw.internal_source_no || row?.internal_source_no || '').trim(),
        party_role_label: String(raw.party_role_label || fallback.party_role_label),
        business_reason: String(raw.business_reason || row?.business_reason || row?.payable_remark || row?.return_remark || row?.remark || fallback.business_reason),
    }
}

const SOURCE_NAME_MAP: Record<string, string> = {
    purchase: '采购入库', purchase_asset: '采购入库', sale: '销售出库',
    purchase_return: '采购退货', sale_return: '销售退货',
    after_sale_compensation: '售后补差', sale_compensation: '售后补差', refurbish: '设备整备',
    sale_receivable: '销售应收', purchase_return_receivable: '采购退货退款',
    purchase_payable: '采购应付', sale_return_payable: '销售退货应付',
    sale_compensation_payable: '售后补差应付', refurbish_payable: '整备费用应付',
}

function readableName(value: any, fallback: string) {
    const name = String(value || '').trim()
    return SOURCE_NAME_MAP[name] || name || fallback
}

export function erpFinanceSourceTagType(meta: ErpFinanceSourceMeta) {
    const key = meta.biz_scene
    if (key === 'sale' || key === 'purchase') return 'primary'
    if (key === 'purchase_return') return 'success'
    if (key === 'sale_return') return 'warning'
    if (key === 'after_sale_compensation') return 'warning'
    if (key === 'refurbish') return 'warning'
    return 'info'
}

function normalizeMeta(value: any): Record<string, any> {
    if (value && typeof value === 'object' && !Array.isArray(value)) return value
    if (typeof value !== 'string' || !value.trim()) return {}
    try {
        const parsed = JSON.parse(value)
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}
    } catch {
        return {}
    }
}

function fallbackMeta(sourceType: string, direction: ErpFinanceDirection, compensation: boolean): ErpFinanceSourceMeta {
    if (direction === 'receivable') {
        if (sourceType === 'purchase_return') {
            return base('purchase_return_refund', '采购退货退款', 'purchase_return', '采购退货', '退款供货方', '采购退货已完成，已付款部分需要由供货方退回。')
        }
        return base('sale_receivable', '销售应收', 'sale', '销售出库', '收款客户', '销售出库形成客户应收。')
    }
    if (sourceType === 'sale_return' || sourceType === 'after_sale_compensation') {
        if (compensation) {
            const result = base('after_sale_compensation', '售后补差应付', 'sale_return', '售后补差', '补差客户', '公司同意向客户支付售后补差款。')
            result.biz_scene = 'after_sale_compensation'
            return result
        }
        return base('sale_return_refund', '销售退货应付', 'sale_return', '销售退货', '退款客户', '客户退回设备，已收款部分需要退还客户。')
    }
    if (sourceType === 'refurbish') {
        return base('refurbish_expense', '整备费用应付', 'refurbish', '设备整备', '整备服务商', '设备发生整备费用，需要向服务商付款。')
    }
    return base('purchase_payable', '采购应付', 'purchase', '采购入库', '付款对象', '设备采购入库形成应付。')
}

function base(
    financeTypeKey: string,
    financeTypeName: string,
    sourceKey: string,
    sourceName: string,
    partyRole: string,
    reason: string,
): ErpFinanceSourceMeta {
    return {
        finance_type_key: financeTypeKey,
        finance_type_name: financeTypeName,
        direction: '',
        biz_scene: sourceKey,
        business_source_key: sourceKey,
        business_source_name: sourceName,
        source_plugin: '',
        source_plugin_name: '',
        channel_code: '',
        channel_name: '',
        source_no: '',
        internal_source_no: '',
        party_role_label: partyRole,
        business_reason: reason,
    }
}

function inferScene(originKey: string, direction: ErpFinanceDirection) {
    const key = String(originKey || '').toLowerCase()
    if (key.includes('purchase_return')) return 'purchase_return'
    if (key.includes('compensation')) return 'after_sale_compensation'
    if (key.includes('sale_return')) return 'sale_return'
    if (key.includes('refurbish')) return 'refurbish'
    if (key.includes('sale')) return 'sale'
    if (key.includes('purchase') || key.includes('recycle')) return 'purchase'
    return direction === 'receivable' ? 'sale' : 'purchase'
}
