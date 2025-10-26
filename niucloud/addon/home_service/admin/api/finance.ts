import request from '@/utils/request'


/***************************************************** 项目 ****************************************************/

/**
 * 获取师傅结算列表(分页)
 * @param params
 * @returns
 */
export function getTechnicianListFinance(params: Record<string, any>) {
    return request.get(`home_service/technician/settlement`, { params })
}


/**
 * 获取发票列表(分页)
 * @param params
 * @returns
 */
export function setInvoice(params: Record<string, any>) {
    return request.put(`home_service/invoice/issue/${params.id}`,  params, { showErrorMessage: true, showSuccessMessage: true } )
}


/**
 * 获取师傅结算详情列表(分页)
 * @param params
 * @returns
 */
export function getTechnicianListFinanceDetail(params: Record<string, any>) {
    return request.get(`home_service/technician/settlement/account`, { params })
}

/**
 * 获取师傅结算统计(分页)
 * @param params
 * @returns
 */
export function getTechnicianListFinanceStat(params: Record<string, any>) {
    return request.get(`home_service/technician/settlement/stat`, { params })
}

/**
 * 获取门店结算列表(分页)
 * @param params
 * @returns
 */
export function getStoreFinance(params: Record<string, any>) {
    return request.get(`home_service/store/settlement`, { params })
}

/**
 * 获取门店结算详情列表(分页)
 * @param params
 * @returns
 */
export function getStoreListFinanceDetail(params: Record<string, any>) {
    return request.get(`home_service/store/settlement/account`, { params })
}

/**
 * 获取门店结算统计(分页)
 * @param params
 * @returns
 */
export function getStoreListFinanceStat(params: Record<string, any>) {
    return request.get(`home_service/store/settlement/stat`, { params })
}


/**
 * 获取提现数据列表(分页)
 * @param params
 * @returns
 */
export function getCashOutList(params: Record<string, any>) {
    return request.get(`home_service/cash_out`, { params })
}



/**
 * 获取提现详情
 * @param params
 * @returns
 */
export function getCashOutDetail(id:number) {
    return request.get(`home_service/cash_out/${id}`)
}



/**
 * 提现备注
 * @param params
 * @returns
 */
export function setCashOutNotes(params: Record<string, any>) {
    return request.put(`home_service/cash_out/remark/${params.id}`, params )
}


/**
 * 提现状态列表
 * @param params
 * @returns
 */
export function getCashOutStatus(params: Record<string, any>) {
    return request.get(`home_service/cash_out/status/dict`, params )
}


/**
 * 提现转账
 * @param params
 * @returns
 */
export function setCashOutTransfer(params: Record<string, any>) {
    return request.put(`home_service/cash_out/transfer/${params.cash_out_id}`, params )
}