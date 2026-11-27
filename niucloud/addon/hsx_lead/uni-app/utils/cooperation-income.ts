export const cooperationIncomeTypes = [
    { value: 'all', label: '全部收益' },
    { value: 'membership', label: '会员推广' },
    { value: 'maintenance', label: '维护费推广' },
    { value: 'publisher', label: '线索收益' }
]

export function cooperationIncomeType(value: unknown) {
    const key = String(value || 'all')
    return cooperationIncomeTypes.some(item => item.value === key) ? key : 'all'
}

export function cooperationIncomeLabel(value: unknown) {
    return cooperationIncomeTypes.find(item => item.value === value)?.label || '协作收益'
}

export function cooperationIncomeKey(row: Record<string, any>) {
    return String(row.record_key || `${row.income_type || row.source || 'unknown'}:${row.id}`)
}

/** A reverted reward remains an audit record, never a new positive credit. */
export function cooperationIncomeCredited(row: Record<string, any>) {
    return ['paid', 'settled'].includes(String(row.status))
}

export function cooperationIncomeStatus(row: Record<string, any>) {
    return String(row.status_label || ({ pending: '待结算', settled: '已入账', paid: '已入账', held: '待核对', reversed: '已撤销', cancelled: '已取消', refund_review: '退款核对中' } as Record<string, string>)[String(row.status)] || '状态待核对')
}
