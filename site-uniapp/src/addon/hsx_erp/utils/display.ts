/** Display-only helpers. Keep identifiers in API payloads, lookups and actions. */
const SOURCE_LABELS: Record<string, string> = {
    '回收插件采购': '回收入库',
    hsx_recycle: '回收业务', recycle: '回收业务',
    hsx_erp: 'ERP业务', erp: 'ERP业务',
    phone_shop: '商城业务', hsx_phone_shop: '商城业务', mall: '商城业务',
    purchase: '采购入库', purchase_asset: '采购入库', sale: '销售出库',
    purchase_return: '采购退货', sale_return: '销售退货',
    refurbish: '设备整备', after_sale_compensation: '售后补差',
    operating: '经营收支', operating_income: '经营收入', operating_expense: '经营支出',
}

const text = (value: any): string => String(value ?? '').trim()
const isTechnicalKey = (value: string): boolean => /^(?:hsx_|[a-z][a-z0-9]*_)[a-z0-9_]+$/i.test(value)

/** Names are not identifiers: keep English names such as iPhone, admin and eBay. */
export function erpOptionLabel(name: any, key: any, fallback = '未命名选项'): string {
    const label = text(name)
    if (!label || /^hsx_[a-z0-9_]+$/i.test(label)) return fallback
    if (label === text(key) && isTechnicalKey(label)) return fallback
    return label
}

/** Unknown enum values must not become visible labels. */
export function erpEnumLabel(value: any, labels: Record<string, string>, fallback = '状态待确认'): string {
    const key = text(value)
    const label = text(labels?.[key])
    if (label && !isTechnicalKey(label) && (label !== key || /[\u3400-\u9fff]/.test(label))) {
        return erpOptionLabel(label, key, fallback)
    }
    return /[\u3400-\u9fff]/.test(key) && !/\bhsx_[a-z0-9_]+\b/i.test(key) ? key : fallback
}

/** Use business source names, never plugin identifiers or raw source keys. */
export function erpSourceLabel(value: any, fallback = '其他来源'): string {
    const label = text(value)
    if (SOURCE_LABELS[label]) return SOURCE_LABELS[label]
    if (!label || isTechnicalKey(label)) return fallback
    const readable = label.replace(/\b(?:hsx_[a-z0-9_]+|phone_shop)\b/gi, '')
        .replace(/^[\s·|｜/：:,，-]+|[\s·|｜/：:,，-]+$/g, '').trim()
    return readable || fallback
}
