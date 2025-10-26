import request from '@/utils/request'
/**
 * 获取下级地址列表
 * @param pid
 */
export function getAreaListByPid(pid: number = 0) {
    return request.get(`sys/area/list_by_pid/${ pid }`)
}
/**
 * 获取下级地址列表
 * @param pid
 */
export function getmakerListByPid(pid: number = 0) {
    return request.get(`home_service/CityStrategy`)
}
// 删除
export function deleteCityStrategy(id: number) {
    return request.delete(`home_service/citystrategy/${ id }`, { showErrorMessage: true, showSuccessMessage: true })
}


/**
 * 获取地址树列表
 * @param level
 */
export function getAreatree(level: number = 1) {
    return request.get(`sys/area/tree/${ level }`)
}

/**
 * 获取地址信息
 */
export function getAddressInfo(params: any) {
    return request.get(`sys/area/get_info`, { params })
}

/**
 * 获取地址信息
 */
export function getContraryAddress(params: any) {
    return request.get(`sys/area/contrary`, { params })
}

/**
 * 获取地址
 * @param code
 */
export function getAreaByCode(code: number | string) {
    return request.get(`sys/area/code/${ code }`)
}

/**
 * 修改数量
 * @param code
 */
export function makerLimit(params: any) {
    return request.put(`sys/area/edit/maker`, {params}, { showSuccessMessage: true })
}

/***************************************************** 存储设置 ****************************************************/

/**
 * 获取存储配置列表
 */
export function getStorageList() {
    return request.get(`sys/storage`)
}

/**
 * 获取存储详情
 * @param type
 */
export function getStorageInfo(type: string) {
    return request.get(`sys/storage/${ type }`)
}

/**
 * 修改存储
 * @param params
 * @returns
 */
export function editStorage(params: Record<string, any>) {
    return request.put(`sys/storage/${ params.storage_type }`, params, { showSuccessMessage: true })
}

/**
 * 修改人数
 * @returns
 */
export function setAddressNum(params: Record<string, any>) {
    return request.put('maker/area',params,{
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 修改人数
 * @returns
 */
export function batchSetAddressNum(params: Record<string, any>) {
    return request.put('maker/area',params,{
        showErrorMessage: true,
        showSuccessMessage: true
    })
}


/**
 * 获取推荐人数
 * @returns
 */
export function getRecommedNum(id: number) {
    return request.get(`maker/area/recommend/${id}`)
}

/**
 * 设置推荐人数
 * @returns
 */
export function setRecommedNum(params: Record<string, any>) {
    return request.post(`maker/area/recommend`,params)
}

/**
 * 获取修改人数
 * @returns
 */
export function getUpdateLog(id: Record<string, any>) {
    return request.get(`maker/area/update_log/${id}`)
}


/**
 * 获取地区权限数据
 * @returns
 */
export function getAreaPermissions(id: Record<string, any>) {
    return request.get(`maker/maker_permission/${id}`)
}


/**
 * 修改地区权限数据
 * @returns
 */
export function setAreaPermissions(params: Record<string, any>) {
    return request.put(`maker/maker_permission/${params.uid}`,params)
}

/**
 * 获取所有地区数据
 * @returns
 */
export function getCityStrategy() {
    return request.get(`home_service/citystrategy`)
}


/**
 * 添加地区数据
 * @returns
 */
export function saveCityStrategy(params: Record<string, any>) {
    return request.post(`home_service/citystrategy`,params)
}

/**
 * 编辑地区数据
 * @returns
 */
export function editCityStrategy(params: Record<string, any>) {
    return request.put(`home_service/citystrategy/${params.id}`, params, { showSuccessMessage: true })
}


