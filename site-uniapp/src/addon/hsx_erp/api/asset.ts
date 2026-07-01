import request from '@/utils/request'

/**
 * ERP 资产（在库设备）列表
 * 走 adminapi：/adminapi/erp/asset/lists
 */
export function getErpAssetList(params: Record<string, any>) {
    return request.get('erp/asset/lists', params)
}

/** ERP 资产详情 */
export function getErpAssetInfo(id: number | string) {
    return request.get(`erp/asset/${ id }`)
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
    return request.post(`erp/asset/${ id }/adjust_cost`, {
        cost,
        reason,
        sync_payable: syncPayable ? 1 : 0
    })
}
