import request from '@/utils/request'

// 盘点单列表
export function getStocktakeList(params: Record<string, any>) {
    return request.get('erp/stocktake/lists', { params })
}
// 盘点单详情(含明细)
export function getStocktakeInfo(id: number) {
    return request.get(`erp/stocktake/${id}`)
}
// 新建盘点(按仓库/库位快照应在库清单)
export function createStocktake(data: Record<string, any>) {
    return request.post('erp/stocktake/create', data)
}
// 录入实物(一批 IMEI)
export function scanStocktake(id: number, codes: string[]) {
    return request.post(`erp/stocktake/${id}/scan`, { codes })
}
// 完成盘点
export function finishStocktake(id: number, adjustLoss = 1) {
    return request.post(`erp/stocktake/${id}/finish`, { adjust_loss: adjustLoss })
}

// 找回(误判盘亏纠正): 把盘亏设备恢复在库
export function restoreStocktakeItem(itemId: number) {
    return request.post(`erp/stocktake/item/${itemId}/restore`)
}
