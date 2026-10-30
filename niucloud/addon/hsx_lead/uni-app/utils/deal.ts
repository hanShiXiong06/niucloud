type Row = Record<string, any>
export function taskRequirements(task: Row): Row[] { return task.requirements || task.lead?.requirements || [] }
export function buildDealRows(task: Row): Row[] { return taskRequirements(task).map(item => { const reported = task.status === 'deal_pending' ? (task.deal_items || []).find((row: Row) => String(row.catalog_id) === String(item.catalog_id)) : undefined; return { ...item, confirmed_quantity: Number(item.confirmed_quantity || 0), remaining_quantity: Number(item.remaining_quantity ?? Math.max(0, Number(item.quantity || 1) - Number(item.confirmed_quantity || 0))), sale_quantity: reported ? Number(reported.quantity) : 0, unit_price: reported ? Number(reported.unit_price) : undefined } }) }
export function saleTotal(rows: Row[]): number { return rows.reduce((sum, row) => sum + Math.round(Number(row.sale_quantity || 0) * Number(row.unit_price || 0) * 100), 0) / 100 }
export function validateDeal(task: Row, rows: Row[], closeRemaining: boolean, reason: string): string {
    for (const row of rows) { const quantity = Number(row.sale_quantity || 0); if (!Number.isFinite(quantity) || !Number.isInteger(quantity) || quantity < 0) return `${row.title}：本批数量需为大于或等于 0 的整数。` }
    const selected = rows.filter(row => Number(row.sale_quantity) > 0)
    if (!selected.length) return '请至少填写一项本次实际售出数量；仅结束剩余需求请使用“结束剩余”。'
    const mode = task.demand_mode || task.lead?.demand_mode || 'any'
    if (mode === 'any' && selected.length > 1) return '任选一款模式，本批只能选择一种意向机型。'
    for (const row of selected) {
        if (!Number.isInteger(Number(row.sale_quantity)) || Number(row.sale_quantity) > Number(row.remaining_quantity) || Number(row.sale_quantity) < 1) return `${row.title}：本次售出数量需为不超过剩余需求的正整数。`
        if (task.any_selected_catalog_id && String(row.catalog_id) !== String(task.any_selected_catalog_id)) return '已部分成交的任选需求，只能继续同一款机型或结束剩余需求。'
        if (!Number.isFinite(Number(row.unit_price)) || Number(row.unit_price) <= 0) return `${row.title}：请填写本次实际单价。`
        if (Math.abs(Number(row.unit_price) * 100 - Math.round(Number(row.unit_price) * 100)) > 0.000001) return `${row.title}：实际单价最多填写两位小数。`
    }
    if (closeRemaining && !reason.trim()) return '选择结束剩余需求时，请填写原因。'
    return ''
}
export function dealItems(rows: Row[]): Row[] { return rows.filter(row => Number(row.sale_quantity) > 0).map(row => ({ catalog_id: row.catalog_id, quantity: Number(row.sale_quantity), unit_price: Number(row.unit_price) })) }
export const completionLabels: Record<string, string> = { none: '尚未成交', partial: '部分成交，继续跟进', complete: '需求已全部完成', partial_closed: '部分成交，剩余已结束', cancelled: '成交已撤销' }
export function deadlineLabel(deadline: any, now = Date.now()): string { const seconds = Math.ceil((Number(deadline || 0) * 1000 - now) / 1000); return seconds <= 0 ? '接单已超时，需平台重新派单' : seconds < 60 ? '剩余不足 1 分钟' : seconds < 3600 ? `还剩 ${Math.ceil(seconds / 60)} 分钟` : `还剩 ${Math.floor(seconds / 3600)} 小时 ${Math.ceil((seconds % 3600) / 60)} 分钟` }
export function isExpired(task: Row, now = Date.now()): boolean { return task.status === 'assigned' && Number(task.response_deadline || 0) * 1000 <= now }
export function progressLabel(task: Row): string { const mode = task.demand_mode || task.lead?.demand_mode || 'any'; let rows = taskRequirements(task); if (mode === 'any') { if (!task.any_selected_catalog_id) return '尚未成交 · 待确定一种备选机型'; rows = rows.filter(row => String(row.catalog_id) === String(task.any_selected_catalog_id)) }; const confirmed = rows.reduce((sum, row) => sum + Number(row.confirmed_quantity || 0), 0), remaining = rows.reduce((sum, row) => sum + Number(row.remaining_quantity ?? row.quantity ?? 0), 0); return `已核验成交 ${confirmed} 台 · 剩余需求 ${remaining} 台` }
