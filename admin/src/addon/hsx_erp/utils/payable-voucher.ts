// A receipt belongs to an ERP voucher, not to the latest payment for a device.
export function voucherId(row: any): number {
    const id = Number(row?.settlement_id ?? row?.id)
    return Number.isSafeInteger(id) && id > 0 ? id : 0
}

export function confirmedOutgoing(row: any): boolean {
    return !!voucherId(row) && row?.settlement_type === 'payment' && row.cash_direction === 'out'
        && (row.settlement_status ?? row.status) === 'confirmed'
}

export function linkedVoucherResults(item: any, voucher: any): any[] {
    if (!confirmedOutgoing(voucher)) return []
    return (Array.isArray(item?.integration_results) ? item.integration_results : [])
        .filter((r: any) => r?.query?.kind === 'payment' && Number(r.query.settlement_id) === voucherId(voucher))
}

export function collectPageVouchers(rows: any[]): any[] {
    const vouchers = new Map<string, any>(), seenLinks = new Map<string, Set<number>>()
    for (const item of rows || []) for (const s of item.settlements || []) {
        const pid = Number(item.payable_id)
        const exact = confirmedOutgoing(s) && !!s.settlement_no && Number.isSafeInteger(pid) && pid > 0 && Number(s.target_id) === pid
        const key = exact ? JSON.stringify([voucherId(s), s.settlement_no, s.amount, s.operator_uid, s.capital_account_id])
            : `line:${s.settlement_id}_${s.applied_amount}_${item.payable_id}`
        const links = linkedVoucherResults(item, s)
        if (!vouchers.has(key)) {
            vouchers.set(key, {...s, _view_key:key, integration_results:links})
            seenLinks.set(key, new Set([pid]))
        } else if (exact && !seenLinks.get(key)!.has(pid)) {
            const v = vouchers.get(key)
            // The voucher was paid once; only the allocation across visible payables is summed.
            v.applied_amount = (Math.round(Number(v.applied_amount) * 100) + Math.round(Number(s.applied_amount) * 100)) / 100
            const unique = new Map([...v.integration_results, ...links].map(r => [JSON.stringify(r), r]))
            v.integration_results = [...unique.values()]
            seenLinks.get(key)!.add(pid)
        }
    }
    return [...vouchers.values()]
}

export function purchaseTargets(row: any): any[] {
    if (!confirmedOutgoing(row) || !(Number(row.party_id) > 0)) return []
    return (Array.isArray(row.links) ? row.links : []).filter((t: any) =>
        t?.target_type === 'payable' && Number.isSafeInteger(Number(t.target_id)) && Number(t.target_id) > 0
        && t.devices?.some((d: any) => d.scene === 'purchase'))
}

export function matchingVoucherResults(rows: any[], target: any, voucher: any): any[] | null {
    if (!Array.isArray(rows) || rows.length !== 1 || !confirmedOutgoing(voucher)) return null
    const item = rows[0]
    if (Number(item.payable_id) !== Number(target.target_id) || !voucher.settlement_no) return null
    const original = (item.settlements || []).filter((s: any) => confirmedOutgoing(s)
        && voucherId(s) === voucherId(voucher) && s.settlement_no === voucher.settlement_no)
    return original.length === 1 ? linkedVoucherResults(item, original[0]) : null
}
