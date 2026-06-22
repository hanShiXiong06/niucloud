import request from '@/utils/request'

/**
 * 快速出入库（一单成账）：
 * 选好供货人 + 买家，逐台填型号/IMEI/进价/出货价，
 * 后端每台走 入库(应付供货人) → 出库(应收买家)，两笔账都挂着（后续核销）。
 */
export function quickTradeCreate(data: Record<string, any>) {
    return request.post('erp/quick_trade/create', data, { showErrorMessage: true })
}
