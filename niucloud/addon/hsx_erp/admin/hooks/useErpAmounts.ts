/**
 * 金额字段经数据库序列化后通常是字符串，`"0.00"` 在 JavaScript 中仍是真值。
 * 标价、预估售价、成本等候选金额必须先转数字，再按大于 0 的顺序回退。
 */
export function firstPositiveErpAmount(...values: any[]): number {
    for (const value of values) {
        const amount = Number(value)
        if (Number.isFinite(amount) && amount > 0) return amount
    }
    return 0
}
