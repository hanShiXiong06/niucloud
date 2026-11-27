import request from '@/utils/request'



// USER_CODE_BEGIN -- recycle_user_address
/**
 * 获取用户退货地址列表
 * @param params
 * @returns
 */
export function getRecycleUserAddressList(params: Record<string, any>) {
    return request.get(`recycle/recycle_user_address`, {params})
}

/**
 * 获取用户退货地址详情
 * @param id 用户退货地址id
 * @returns
 */
export function getRecycleUserAddressInfo(id: number) {
    return request.get(`recycle/recycle_user_address/${id}`);
}

/**
 * 添加用户退货地址
 * @param params
 * @returns
 */
export function addRecycleUserAddress(params: Record<string, any>) {
    return request.post('recycle/recycle_user_address', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑用户退货地址
 * @param id
 * @param params
 * @returns
 */
export function editRecycleUserAddress(params: Record<string, any>) {
    return request.put(`recycle/recycle_user_address/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除用户退货地址
 * @param id
 * @returns
 */
export function deleteRecycleUserAddress(id: number) {
    return request.delete(`recycle/recycle_user_address/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getWithMemberList(params: Record<string,any>){
    return request.get('recycle/member_all', {params})
}

// USER_CODE_END -- recycle_user_address
