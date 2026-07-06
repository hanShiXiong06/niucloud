import request from '@/utils/request'

const normalizeAsset = (row: Record<string, any>) => ({
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
    return {
        ...res,
        data: {
            asset,
            counterparty: { unit_name: asset.party_name || '' },
            cost_ledger: asset.asset_ledgers || asset.ledger || []
        }
    }
}

/**
 * 调整在库设备成本（写成本流水，并按规则回填回收/财务）
 * @param id          ERP 资产 id
 * @param cost        新成本
 * @param reason      调整原因
 * @param syncPayable 差额是否同步到对供应商的应付（仅有入库应付的设备可用）
 */
export function adjustErpAssetCost(
    id: number | string,
    cost: number,
    reason: string,
    syncPayable: boolean
) {
    return request.post(`erp/stock/${ id }/adjust_cost`, {
        cost,
        reason,
        sync_payable: syncPayable ? 1 : 0
    })
}
