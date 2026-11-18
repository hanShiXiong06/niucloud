export type PurchaseSelection = {
    company_id: string
    supplier_id: string
    creator_id: string
    buyer_id: string
    lines: { source_line_id: string, product_id: string, warehouse_id: string }[]
}

// These codes are returned by draft selection validation before its enqueue transaction commits.
const correctableSelectionCodes = new Set([
    'PURCHASE_SELECTION_REQUIRED', 'PURCHASE_SELECTION_INVALID', 'ARCHIVE_STALE', 'ARCHIVE_ID_INVALID',
    'CREATOR_NAME_INVALID', 'WAREHOUSE_COMPANY_MISMATCH', 'PURCHASE_MULTI_WAREHOUSE', 'SUPPLIER_ROLE_INVALID',
    'BUYER_ROLE_INVALID', 'PRODUCT_SERIAL_UNSUPPORTED', 'PRODUCT_SERIAL_LENGTH', 'MAPPING_CONFLICT', 'STAFF_ALREADY_BOUND'
])
export function purchaseSelectionProblem(error: unknown): string | null {
    if (!error || typeof error !== 'object') return null
    const { code, msg } = error as { code?: unknown, msg?: unknown }
    if (typeof code !== 'number' || !Number.isInteger(code) || code === 1 || typeof msg !== 'string') return null
    const parts = /^\[([A-Z_]+)\]\s*(.+)$/.exec(msg)
    return parts && correctableSelectionCodes.has(parts[1]) ? parts[2].slice(0, 400) : null
}

export function purchaseRouteId(value: unknown): number {
    if (typeof value !== 'string' && typeof value !== 'number') return 0
    const text = String(value)
    if (!/^[1-9][0-9]*$/.test(text)) return 0
    const id = Number(text)
    return Number.isSafeInteger(id) && id > 0 ? id : 0
}

// Only external associations may be posted. Local serials, quantities and costs
// always come from the server snapshot, never from an editable form.
export function purchaseSelection(selection: any = {}, source: any = {}): PurchaseSelection {
    return {
        company_id: String(selection.company_id || ''),
        supplier_id: String(selection.supplier_id || ''),
        creator_id: String(selection.creator_id || ''),
        buyer_id: String(selection.buyer_id || ''),
        lines: (source.lines || []).map((line: any) => {
            const selected = (selection.lines || []).find((item: any) => String(item.source_line_id) === String(line.source_line_id)) || {}
            return { source_line_id: String(line.source_line_id || ''), product_id: String(selected.product_id || ''), warehouse_id: String(selected.warehouse_id || '') }
        })
    }
}

export function purchaseSelectionComplete(selection: PurchaseSelection): boolean {
    return !!selection.company_id && !!selection.supplier_id && !!selection.creator_id && !!selection.lines.length
        && new Set(selection.lines.map(line => line.source_line_id)).size === selection.lines.length
        && selection.lines.every(line => !!line.source_line_id && !!line.product_id && !!line.warehouse_id)
}

export function purchaseStateMessage(task: any, result: any = {}, inventory?: any): string {
    if (task?.status === 'succeeded') return purchaseInventory(inventory).status === 'succeeded' ? '采购记录与本批次当前在库已核对' : '入库串号明细已核对；当前在库见本批次查询结果'
    if (task?.external_no && (result.created === true || task.status === 'created_review')) return ['pending', 'processing', 'retry', 'confirming'].includes(task.status) ? '博远已建单，入库核验中' : '博远已建单，待核验入库串号明细'
    if (task?.external_no) return '已记录博远单号，待核验原采购与入库串号；单号不代表成功'
    if (task?.status === 'awaiting_confirmation') return '待人工核对，尚未发送到博远'
    if (task?.status === 'confirming' || task?.side_effect === 'unknown') return '建单结果未知，请先核对原单，不能重复建单'
    if (['pending', 'processing', 'retry'].includes(task?.status)) return '已提交后台处理，尚未确认博远入库'
    return '查看当前状态和失败原因，未确认成功前请勿重复建单'
}

export function purchaseMissingFields(selection: PurchaseSelection): string[] {
    const missing: string[] = []
    if (!selection.company_id) missing.push('公司')
    if (!selection.supplier_id) missing.push('供应商')
    if (!selection.creator_id) missing.push('博远制单人')
    selection.lines.forEach((line, index) => {
        if (!line.product_id) missing.push('第' + (index + 1) + '台商品')
        if (!line.warehouse_id) missing.push('第' + (index + 1) + '台仓库')
    })
    return missing
}

type PurchaseHandoff = { title: string, detail: string, tone: string, action?: 'payable' | 'payment' | 'receipt' }
type NextStepOptions = { editable: boolean, canVerify: boolean, complete: boolean, enabled: boolean, canOperate?: boolean, canCheckInventory?: boolean, inventory?: any, operatorComplete?: boolean, closure?: ReturnType<typeof purchaseClosure>, payment?: any }

// A purchase receipt proves inbound facts, not a payable balance or a payment.
// Current-stock checks remain available independently; they do not own the handoff.
function financeHandoff(options: NextStepOptions): PurchaseHandoff {
    const closure = options.closure, payment = options.payment
    const stage = (key: string) => closure?.stages.find(s => s.key === key)?.status
    const state = payment?.status, original = purchaseRouteId(payment?.task_id) > 0
    const basic = { title: '采购入库已核对', detail: '采购员无需再提交。', tone: 'success' }
    if (stage('local_payable') === 'permission_denied' || state === 'permission_denied') return { ...basic, detail: '采购员无需再提交。账款由财务处理。' }
    // Uncertain or in-flight registrations always lead to their original task, even
    // if an older batch total would otherwise appear settled.
    if (original && (state === 'attention' || (state === 'registered_review' && payment.operator_complete !== true)))
        return { title: '付款同步结果待核实', detail: '请核对原付款单，不要重新同步。', tone: 'warning', action: 'payment' }
    if (original && ['pending', 'processing'].includes(state))
        return { title: '正在同步付款记录', detail: '等待博远回执，无需再次提交。', tone: 'progress', action: 'payment' }
    if (closure?.operatorComplete === true)
        return { title: '本批付款已同步到博远', detail: '本单无需再处理。', tone: 'success', action: 'receipt' }
    if (!closure?.available || !['verified','partial','pending'].includes(stage('local_payable') || ''))
        return { ...basic, detail: '暂未取得应付状态；请刷新或让财务核对原单。', tone: 'neutral' }
    const paid = closure.totals?.paid_amount, unpaid = closure.totals?.unpaid_amount, amount = closure.totals?.amount
    const valid = (v: unknown): v is string => typeof v === 'string' && /^\d+(?:\.\d{1,2})?$/.test(v)
    if (![paid, unpaid, amount].every(valid))
        return { ...basic, detail: '应付金额暂不可用，请财务核对原单。', tone: 'neutral' }
    const hasPaid = /[1-9]/.test(paid), owes = /[1-9]/.test(unpaid)
    const remainder = owes ? '剩余应付 ' + purchaseMoney(unpaid) + '。' : ''
    if (hasPaid && (payment?.can_capture === true || (original && state === 'awaiting_confirmation')))
        return { title: '已记账，待同步付款记录', detail: 'ERP 已记账 ' + purchaseMoney(paid) + '。' + remainder + '同步不会再次扣款。', tone: 'progress', action: 'payment' }
    if (owes) return { title: '采购已入库，等待财务处理', detail: remainder + (hasPaid ? '已记账 ' + purchaseMoney(paid) + '。' : '采购员无需再操作。'), tone: 'neutral', action: 'payable' }
    if (hasPaid && state === 'registered_review' && original)
        return { title: '已有付款回执', detail: '请核对本批其余付款记录。', tone: 'neutral', action: 'receipt' }
    if (hasPaid) return { title: 'ERP 已记账', detail: '本地已记账 ' + purchaseMoney(paid) + '。' + (state === 'unavailable' ? '暂不能确认博远同步状态。' : payment?.enabled === false ? '当前未开启付款同步。' : '请财务核对付款同步状态。'), tone: 'neutral' }
    if (stage('local_payable') === 'verified' && !/[1-9]/.test(amount)) return { ...basic, detail: '本批应付为零，无需付款。' }
    return { ...basic, detail: '暂无可结算的应付。需要时请财务核对原单。', tone: 'neutral' }
}

export function purchaseNextStep(task: any, options: NextStepOptions): PurchaseHandoff {
    if (task?.status === 'succeeded') return financeHandoff(options)
    if (options.canOperate === false) return { title: '当前账号仅可查看', detail: '请有采购核对权限的员工处理。', tone: 'neutral' }
    if (options.editable) {
        if (!options.enabled) return { title: '先保存核对，暂不发送', detail: '投递未开放、已暂停或账套未核验。已选资料可保存，开放后再确认提交。', tone: 'neutral' }
        return options.complete ? { title: '资料已齐，可以提交入库', detail: '确认提交后将创建一张博远采购单。', tone: 'progress' } : { title: '请核对入库资料', detail: '选择博远已有的公司、供应商、制单人，并为每台设备选好商品和仓库。', tone: 'progress' }
    }
    if (['pending', 'processing', 'retry'].includes(task?.status)) return { title: '等待后台处理', detail: '页面自动刷新结果。请勿重复提交或到博远另建一张采购单。', tone: 'progress' }
    if (options.canVerify) return task?.external_no ? { title: '核验这张博远原单', detail: '只查询原采购与入库串号，不重新创建，不登记付款。', tone: 'warning' } : { title: '查到原单号后再核验', detail: '创建结果未知。请先在博远确认原操作，再填写已知采购单号。', tone: 'warning' }
    return { title: '请核对当前原因与连接', detail: '任务保持原状态；请刷新结果或联系有权限的员工，不要重复建单。', tone: 'warning' }
}

export function purchaseMoney(value: unknown): string {
    return typeof value === 'string' && /^-?[0-9]+(?:\.[0-9]+)?$/.test(value) ? value + ' 元' : '未取得'
}

export function purchaseClosure(closure: any): { available: boolean, complete: boolean, operatorComplete: boolean, stages: any[], totals: any, warnings: string[] } {
    if (closure?.schema !== 'boyuan.purchase.closure.v1' || !Array.isArray(closure?.stages)) return { available: false, complete: false, operatorComplete: false, stages: [], totals: {}, warnings: ['闭环状态尚未提供；不能据采购回执推断本批次当前在库、应付或付款完成。'] }
    const expected: Record<string, string> = { local_inbound: '本站入库', remote_purchase: '博远采购记录', remote_stock: '本批次当前在库', local_payable: '本站应付', local_payment: '本站确认付款', remote_settlement: '博远付款登记' }
    const statuses = ['verified', 'partial', 'pending', 'unverified', 'blocked', 'unavailable', 'permission_denied']
    const stages = Object.entries(expected).map(([key, label]) => {
        const stage = closure.stages.find((item: any) => item?.key === key)
        if (!stage) return { key, label, status: 'unavailable', reason: '未取得此环节的核对状态', amount: null, paid_amount: null, unpaid_amount: null, count: null }
        if (key === 'remote_settlement' && typeof closure.operator_complete !== 'boolean' && ['partial', 'blocked'].includes(stage.status))
            return { key, label, status: 'unverified', reason: '尚无本批付款登记汇总，可查看单笔付款回执。', amount: null, paid_amount: null, unpaid_amount: null, count: null }
        const status = statuses.includes(stage.status) ? stage.status : 'unverified'
        return { ...stage, key, label, status, ...(status === 'permission_denied' ? { amount: null, paid_amount: null, unpaid_amount: null, count: null } : {}) }
    })
    return { available: closure.available === true, operatorComplete: closure.available === true && closure.operator_complete === true && stages.filter(stage => ['local_payable', 'local_payment', 'remote_settlement'].includes(stage.key)).every(stage => stage.status === 'verified'), complete: closure.available === true && closure.complete === true && stages.every((stage: any) => stage.status === 'verified'), stages, totals: stages.some((stage: any) => stage.status === 'permission_denied') ? { amount: null, paid_amount: null, unpaid_amount: null } : closure.totals || {}, warnings: Array.isArray(closure.warnings) ? closure.warnings.filter((warning: any) => typeof warning === 'string') : [] }
}

export function purchaseClosureStatus(status: string): string {
    return ({ verified: '已核对', partial: '部分完成', pending: '待处理', unverified: '未验证', blocked: '暂不可完成', unavailable: '状态未取得', permission_denied: '无查看权限' } as Record<string, string>)[status] || '未验证'
}

export type PurchaseInventory = { task_id: number | null, status: string, query_date: string | null, checked_at: number | null, total_count: number | null, in_stock_count: number | null, reason: string }

export function purchaseInventory(value: any): PurchaseInventory {
    const statuses = ['not_checked', 'pending', 'processing', 'succeeded', 'failed', 'stale', 'unavailable']
    let status = statuses.includes(value?.status) ? value.status : 'not_checked'
    const count = (input: any) => typeof input === 'number' && Number.isSafeInteger(input) && input >= 0 ? input : null
    const freshResult = status === 'succeeded' || status === 'failed'
    const total = freshResult ? count(value?.total_count) : null
    const inStock = freshResult ? count(value?.in_stock_count) : null
    const queryDate = typeof value?.query_date === 'string' && /^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(value.query_date) ? value.query_date : null
    const checkedAt = typeof value?.checked_at === 'number' && Number.isSafeInteger(value.checked_at) && value.checked_at > 0 ? value.checked_at : null
    let reason = typeof value?.reason === 'string' ? value.reason : ''
    if (status === 'succeeded' && !(total != null && total > 0 && inStock === total && queryDate && checkedAt)) { status = 'unavailable'; reason = '当前在库回执缺少完整数量或查询时间，请刷新核对状态。' }
    const showCounts = status === 'succeeded' || status === 'failed'
    return { task_id: purchaseRouteId(value?.task_id) || null, status, query_date: queryDate, checked_at: checkedAt, total_count: showCounts ? total : null, in_stock_count: showCounts && total != null && inStock != null && inStock <= total ? inStock : null, reason }
}

export function purchaseInventoryLabel(value: PurchaseInventory): string {
    if (value.status === 'failed' && value.total_count != null && value.in_stock_count != null && value.in_stock_count < value.total_count) return '当前在库有差异'
    return ({ not_checked: '尚未核对', pending: '已排队', processing: '核对中', succeeded: '本批次当前在库已核对', failed: '核对未完成', stale: '结果已过期', unavailable: '暂不可核对' } as Record<string, string>)[value.status] || '尚未核对'
}

export function purchaseCheckedAt(value: number | null): string {
    return value ? new Date(value * 1000).toLocaleString('zh-CN', { hour12: false }) : '尚未取得'
}
