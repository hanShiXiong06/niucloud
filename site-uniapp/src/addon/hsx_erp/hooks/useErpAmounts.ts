function firstDefinedNumber(values: any[]): number | null {
    for (const value of values) {
        if (value === null || value === undefined || value === '') continue
        const number = Number(value)
        if (Number.isFinite(number)) return number
    }
    return null
}

/**
 * 金额字段经数据库序列化后常为字符串 "0.00"，不能用 `a || b` 取候选值。
 * 本方法只接受大于 0 的价格，适合标价/预计售价/建议售价的逐级回退。
 */
export function firstPositiveErpAmount(...values: any[]): number {
    for (const value of values) {
        const number = Number(value)
        if (Number.isFinite(number) && number > 0) return number
    }
    return 0
}

export function erpSaleCompensationAmount(row: any): number {
    return Math.max(0, firstDefinedNumber([
        row?.sale_compensation_amount,
        row?.outbound_compensation_amount,
        row?.compensation_amount,
    ]) || 0)
}

export function erpOriginalSaleAmount(row: any): number {
    return Math.max(0, firstDefinedNumber([
        row?.gross_total_amount,
        row?.sale_price,
        row?.outbound_sale_price,
        row?.total_amount,
    ]) || 0)
}

/**
 * 实际销售收入优先采用后端核算字段；旧接口没有该字段时再用原成交减售后补差。
 * 显式的 0 元也是合法值，不能被原成交金额覆盖。
 */
export function erpNetSaleAmount(row: any): number {
    const explicit = firstDefinedNumber([
        row?.net_sale_amount,
        row?.outbound_net_sale_amount,
        row?.net_total_amount,
    ])
    if (explicit !== null) return Math.max(0, explicit)
    return Math.max(0, erpOriginalSaleAmount(row) - erpSaleCompensationAmount(row))
}
