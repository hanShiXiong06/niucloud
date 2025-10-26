import request from '@/utils/request'

/*****************************************************  服务保障 ****************************************************/

/**
 * 获取 服务保障列表
 * @param params
 * @returns
 */
export function getGuaranteeList(params: Record<string, any>) {
    return request.get(`home_service/goods/guarantee`, { params })
}
/**
 * 添加 服务保障
 * @param params
 * @returns
 */
export function addGuarantee(params: Record<string, any>) {
    return request.post('home_service/goods/guarantee', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑 服务保障
 * @param params
 * @returns
 */
export function editGuarantee(params: Record<string, any>) {
    return request.put(`home_service/goods/guarantee/${ params.id }`, params, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 删除 服务保障
 * @param Guarantee_id
 * @returns
 */
export function deleteGuarantee(id: number) {
    return request.delete(`home_service/goods/guarantee/${ id }`, { showErrorMessage: true, showSuccessMessage: true })
}


