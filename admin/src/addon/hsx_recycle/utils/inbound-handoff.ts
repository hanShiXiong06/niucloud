import type { PaymentScope } from './payment-scope'

type InboundDevice = NonNullable<PaymentScope['devices']>[number] & { status?: number; serial?: string; model?: string; has_placement?: boolean }
export type InboundScope = Omit<PaymentScope, 'devices'> & { devices?: InboundDevice[] }

type Feedback = { tone: 'success' | 'warning'; message: string }
export function resyncFeedback(r?: Record<string, any>): Feedback {
    if (!r || typeof r.has_asset !== 'boolean' || r.error || r.unknown || r.ambiguous) return {tone:'warning', message:'未能确认原资产。请刷新核对此机，不要另开入库或付款。'}
    if (Number(r.still_failed) > 0) return {tone:'warning', message:'仍有 ' + r.still_failed + ' 条下游事件未成功，请管理员按串号查同步日志，不要重新入库或付款。'}
    if (!r.has_asset) return {tone:'warning', message:'ERP 尚未确认这台设备入库。请刷新核对原资产；不要重复提交。'}
    return {tone:'success', message:'已核对原 ERP 资产；应付结果请在回收原单查看。'}
}

export function batchInboundFeedback(results: unknown, count: number): Feedback {
    const rows = Array.isArray(results) ? results.filter(r => r?.target === 'self_erp' && r.consumer === 'hsx_erp') : []
    const r = rows[0], counts = [r?.created_count, r?.existing_count]
    if (rows.length !== 1 || r.error || !['processed','duplicate'].includes(r.status) || counts.some(n => !Number.isInteger(n) || n < 0) || counts[0] + counts[1] !== count) return {tone:'warning', message:'回执与本次设备数量未能对应。请按串号核对原资产，不要重新提交。'}
    const summary = '本站 ERP：新增入库 ' + counts[0] + ' 台，原已入库 ' + counts[1] + ' 台。'
    if (Array.isArray(r.warnings) && r.warnings.length) return {tone:'warning', message:summary + String(r.warnings[0])}
    return {tone:'success', message:summary}
}

// 本站资产、逐台应付是两个事实。外部回执及回收单的汇总状态不能代替财务账。
export function inboundHandoff(d: InboundDevice) {
    const fact = d.erp || {}
    const state = (label: string, next: string, action: 'none' | 'inbound' | 'payable' = 'none') => ({ label, next, action })
    if (d.owner === 'local') return state('回收端结算', '沿用回收原单办理，不到 ERP 再付。')
    if (d.owner !== 'self_erp' || fact.ambiguous === true) return state('关联待核对', '管理员核对本机资产和应付归属后刷新；不要重新入库或付款。')
    if (Number(d.status) === 6) return state('已退回', '不从这里办理回收款。')
    if (Number(d.status) === 9) return state('代卖设备', '按代卖结算，不生成普通回收款。')
    if (typeof fact.has_asset !== 'boolean' || typeof fact.has_payable !== 'boolean') return state('未取得结果', '稍后刷新核对原单；暂不付款。')
    if (!fact.has_asset) {
        if (fact.has_payable) return state('账物不一致', '财务核对原应付和资产；不要重复入库。')
        if (Number(d.status) !== 5) return state('待确认回收', '先确认这台设备的回收价，再入库。')
        return state(d.has_placement ? '待核对入库' : '待补入库位置', d.has_placement ? '库管到设备同步页核对原单；确认未入库再补同步。' : '库管在设备同步页补全仓库和库位，再同步这台设备。', 'inbound')
    }
    if (!fact.has_payable || !(Number(fact.purchase_payable_id) > 0)) return state('已入库 · 应付待核对', '财务核对原采购与这台设备的应付明细；不要重新入库或另建付款。')
    const paid = fact.paid_amount, remaining = fact.remaining_amount
    if ([paid, remaining].some(n => n == null || n === '' || !Number.isFinite(Number(n)) || Number(n) < 0)) return state('账款金额待核对', '财务查看原应付；不要按零元结清。')
    if (!['pending', 'partial', 'settled'].includes(fact.purchase_payable_status) || (Number(remaining) === 0) !== (fact.purchase_payable_status === 'settled')) return state('应付状态待核对', '金额与结算状态未能相互印证，请财务核对原单，不要再付。', 'payable')
    if (Number(remaining) === 0) return state('已入库 · 已结清', Number(d.pay_status) === 1 ? '财务无需再次付款。' : 'ERP 已结清，回收状态待核对；无需再次付款。', 'payable')
    return state(Number(paid) > 0 ? '已入库 · 部分结清' : '已入库 · 待付款', '财务：仅剩 ¥' + Number(remaining).toFixed(2) + '，核对实际转款后登记。', 'payable')
}
