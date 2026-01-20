import request from '@/utils/request'
export function getStatus(){
    return request.get('kd_api/getstatus')
}
// USER_CODE_BEGIN -- kdapi_api
/**
 * 获取api对接列表
 * @param params
 * @returns
 */
export function getKdapiApiList(params: Record<string, any>) {
    return request.get(`kd_api/kdapi_api`, {params})
}

/**
 * 获取api对接详情
 * @param id api对接id
 * @returns
 */
export function getKdapiApiInfo(id: number) {
    return request.get(`kd_api/kdapi_api/${id}`);
}

/**
 * 添加api对接
 * @param params
 * @returns
 */
export function addKdapiApi(params: Record<string, any>) {
    return request.post('kd_api/kdapi_api', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑api对接
 * @param id
 * @param params
 * @returns
 */
export function editKdapiApi(params: Record<string, any>) {
    return request.put(`kd_api/kdapi_api/${params.id}`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 删除api对接
 * @param id
 * @returns
 */
export function deleteKdapiApi(id: number) {
    return request.delete(`kd_api/kdapi_api/${id}`, { showErrorMessage: true, showSuccessMessage: true })
}

export function getWithMemberList(params: Record<string,any>){
    return request.get('kd_api/member_all', {params})
}

// USER_CODE_END -- kdapi_api
