export type PurchaseSelection = {
    company_id: string
    supplier_id: string
    creator_id: string
    buyer_id: string
    lines: { source_line_id: string, product_id: string, warehouse_id: string }[]
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

export function purchaseStateMessage(task: any, result: any = {}): string {
    if (task?.status === 'succeeded') return '入库串号明细已核对；库存余额与博远财务未验证'
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

export function purchaseNextStep(task: any, options: { editable: boolean, canVerify: boolean, complete: boolean, enabled: boolean, canOperate?: boolean }): { title: string, detail: string, tone: string } {
    if (options.canOperate === false) return { title: '当前账号仅可查看', detail: '请有采购核对权限的员工继续处理；不会发送新请求。', tone: 'neutral' }
    if (task?.status === 'succeeded') return { title: '采购与入库串号已核对', detail: '这一步不代表库存余额、应付或付款已核对，不要重复建单。', tone: 'success' }
    if (options.editable) {
        if (!options.enabled) return { title: '先保存核对，暂不发送', detail: '投递未开放、已暂停或账套未核验。已选资料可保存，开放后再确认提交。', tone: 'neutral' }
        return options.complete ? { title: '资料已选齐，请确认后提交', detail: '保存只保留本次选择；确认提交后才由后台创建博远采购单。', tone: 'progress' } : { title: '补齐博远入库资料', detail: '逐台核对商品与仓库，选择公司、供应商和制单人后再提交。', tone: 'progress' }
    }
    if (['pending', 'processing', 'retry'].includes(task?.status)) return { title: '等待后台处理', detail: '页面自动刷新结果。请勿重复提交或到博远另建一张采购单。', tone: 'progress' }
    if (options.canVerify) return task?.external_no ? { title: '核验这张博远原单', detail: '只查询原采购与入库串号，不重新创建，不登记付款。', tone: 'warning' } : { title: '查到原单号后再核验', detail: '创建结果未知。请先在博远确认原操作，再填写已知采购单号。', tone: 'warning' }
    return { title: '请核对当前原因与连接', detail: '任务保持原状态；请刷新结果或联系有权限的员工，不要重复建单。', tone: 'warning' }
}

export function purchaseMoney(value: unknown): string {
    return typeof value === 'string' && /^-?[0-9]+(?:\.[0-9]+)?$/.test(value) ? value + ' 元' : '未取得'
}

export function purchaseClosure(closure: any): { available: boolean, complete: boolean, stages: any[], totals: any, warnings: string[] } {
    if (closure?.schema !== 'boyuan.purchase.closure.v1' || !Array.isArray(closure?.stages)) return { available: false, complete: false, stages: [], totals: {}, warnings: ['闭环状态尚未提供；不能据采购回执推断库存余额、应付或付款完成。'] }
    const expected: Record<string, string> = { local_inbound: '本站入库', remote_purchase: '博远采购记录', remote_stock: '当前库存余额', local_payable: '本站应付', local_payment: '本站确认付款', remote_settlement: '博远付款及指定核销' }
    const statuses = ['verified', 'partial', 'pending', 'unverified', 'blocked', 'unavailable', 'permission_denied']
    const stages = Object.entries(expected).map(([key, label]) => {
        const stage = closure.stages.find((item: any) => item?.key === key)
        if (!stage) return { key, label, status: 'unavailable', reason: '未取得此环节的核对状态', amount: null, paid_amount: null, unpaid_amount: null, count: null }
        const status = statuses.includes(stage.status) ? stage.status : 'unverified'
        return { ...stage, key, label, status, ...(status === 'permission_denied' ? { amount: null, paid_amount: null, unpaid_amount: null, count: null } : {}) }
    })
    return { available: closure.available === true, complete: closure.available === true && closure.complete === true && stages.every((stage: any) => stage.status === 'verified'), stages, totals: stages.some((stage: any) => stage.status === 'permission_denied') ? { amount: null, paid_amount: null, unpaid_amount: null } : closure.totals || {}, warnings: Array.isArray(closure.warnings) ? closure.warnings.filter((warning: any) => typeof warning === 'string') : [] }
}

export function purchaseClosureStatus(status: string): string {
    return ({ verified: '已核对', partial: '部分完成', pending: '待处理', unverified: '未验证', blocked: '暂不可完成', unavailable: '状态未取得', permission_denied: '无查看权限' } as Record<string, string>)[status] || '未验证'
}
