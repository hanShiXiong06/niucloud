/** Local device payables only. Vendor receipts and allocation are separate evidence. */
export type DeviceBillState = 'pending' | 'partial' | 'settled' | 'void' | 'review'

export function deviceBillState(row: any): DeviceBillState {
    const hint = row?.device_payment_hint
    if (hint?.state === 'void') return 'void'
    const value = hint?.remaining
    if ((typeof value !== 'number' && (typeof value !== 'string' || !/^\d+(?:\.\d{1,2})?$/.test(value))) ||
        !Number.isFinite(Number(value)) || Number(value) < 0) return 'review'
    if (hint.state === 'settled' && Number(value) === 0) return 'settled'
    if (row.order_status !== 'void' && ![row.status,row.asset_status].some(s=>s === 'returned' || s === 'void') &&
        !Number(row.is_returned) && Number(value) > 0 && (hint.state === 'pending' || hint.state === 'partial')) return hint.state
    return 'review'
}

export function purchaseHandoff(devices: any[], external: boolean) {
    if (!devices.length) return null
    const states = devices.map(deviceBillState)
    const review = states.filter(s => s === 'review').length
    const due = states.filter(s => s === 'pending' || s === 'partial').length
    if (review) return {tone:'review', role:'财务核对', title: review + ' 台的原应付待核对', note:
        (due ? '另有 ' + due + ' 台有待付余额。' : '') + '先核对设备原应付，金额不明的设备不按结清处理。'}
    if (due) return {tone:'due', role:'财务跟进', title: due + ' 台有待付余额', note:
        '按设备查看原应付。已付的款项只登记一次；部分付款只看剩余金额。'}
    if (states.every(s => s === 'void')) return {tone:'muted', role:'无需登记', title:'本单设备应付已作废', note:'保留原单供核对。'}
    return {tone:'settled', role:'无需再付', title: states.includes('void') ? '有效设备的本站应付已结清' : '本站应付已结清',
        note: external ? '财务不用再登记付款。外部凭证和原采购核销请看下方回执。' : '财务不用再登记付款。'}
}
