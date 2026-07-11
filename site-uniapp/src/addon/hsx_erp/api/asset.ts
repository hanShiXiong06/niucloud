import request from '@/utils/request'

function withAssetRequestId(data: Record<string, any>, prefix: string) {
    if (String(data?.request_id ?? '').trim()) return data
    return { ...data, request_id: `${prefix}:${Date.now().toString(36)}:${Math.random().toString(36).slice(2, 12)}` }
}

const normalizeAsset = (row: Record<string, any>): Record<string, any> => ({
    ...row,
    inventory_status: row.inventory_status || row.status || '',
    current_cost: row.current_cost ?? row.total_cost ?? 0,
})

/**
 * ERP 资产（在库设备）列表
 * 复用库存接口：/adminapi/erp/stock/lists
 */
export async function getErpAssetList(params: Record<string, any>) {
    const query = { ...params }
    if (query.inventory_status === 'onhand') query.status = 'in_stock'
    else if (query.inventory_status) query.status = query.inventory_status
    delete query.inventory_status
    const res: any = await request.get('erp/stock/lists', query)
    const data = res?.data || {}
    const rows = data.list || data.data || []
    const normalized = rows.map((row: Record<string, any>) => normalizeAsset(row))
    if (data.list) data.list = normalized
    if (data.data) data.data = normalized
    return res
}

/** ERP 资产详情 */
export async function getErpAssetInfo(id: number | string) {
    const res: any = await request.get(`erp/stock/${ id }`)
    const asset = normalizeAsset(res?.data || {})
    const costTypeText: Record<string, string> = {
        purchase_adjust: '供应商调价',
        supplier_adjust: '供应商调价',
        refurbish: '整备费用',
        internal_adjust: '内部成本修正',
    }
    const costLedger = (asset.asset_ledgers || asset.ledger || []).map((row: Record<string, any>) => {
        const costType = String(row.source_type || row.action || '')
        return {
            ...row,
            before_cost: row.before_cost ?? row.before_total_cost ?? 0,
            after_cost: row.after_cost ?? row.after_total_cost ?? 0,
            cost_type: costType,
            cost_type_text: row.cost_type_text || costTypeText[costType] || '成本调整',
        }
    })
    return {
        ...res,
        data: {
            asset,
            counterparty: { unit_name: asset.party_name || '' },
            cost_ledger: costLedger
        }
    }
}

/**
 * 调整在库设备成本（写成本流水，并按规则回填回收/财务）
 * @param id          ERP 资产 id
 * @param cost        新成本
 * @param reason      调整原因
 * @param syncPayable 差额是否同步到对供应商的应付（仅有入库应付的设备可用）
 * @param costType   purchase_adjust供应商调价/refurbish整备费用/internal_adjust内部修正
 */
export function adjustErpAssetCost(
    id: number | string,
    cost: number,
    reason: string,
    syncPayable: boolean,
    costType: 'purchase_adjust' | 'refurbish' | 'internal_adjust' = 'internal_adjust',
    context: { expense_type_key?: string; party_id?: number; party_name?: string; refurbish_items?: { name: string; amount: number; party_id: number; party_name: string }[] } = {}
) {
    return request.post(`erp/stock/${ id }/adjust_cost`, withAssetRequestId({
        cost,
        reason,
        sync_payable: syncPayable ? 1 : 0,
        cost_type: costType,
        ...context,
    }, 'cost-adjust'))
}

export function getErpFinanceCategories() {
    return request.get('erp/config/finance_categories')
}
