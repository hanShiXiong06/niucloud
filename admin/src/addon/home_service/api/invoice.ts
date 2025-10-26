import request from '@/utils/request'


/***************************************************** 项目 ****************************************************/

/**
 * 获取发票列表(分页)
 * @param params
 * @returns
 */
export function getInvoiceList(params: Record<string, any>) {
    return request.get(`home_service/invoice`, { params })
}


/**
 * 获取发票列表(分页)
 * @param params
 * @returns
 */
export function setInvoice(params: Record<string, any>) {
    return request.put(`home_service/invoice/issue/${params.id}`,  params, { showErrorMessage: true, showSuccessMessage: true } )
}

