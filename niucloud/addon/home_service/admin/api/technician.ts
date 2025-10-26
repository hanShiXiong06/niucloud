import request from '@/utils/request'


/***************************************************** 师傅 ****************************************************/

/**
 * 获取师傅列表(分页)
 * @param params
 * @returns
 */
export function getTechnicianList(params: Record<string, any>) {
    return request.get(`home_service/technician`, { params })
}

/**
 * 获取师傅列表（不分页）
 * @returns
 */
export function getTechnicianListTo() {
    return request.get(`home_service/technician/list`)
}

/**
 * 获取师傅列表（不分页 - 弹窗）
 * @returns
 */
export function getTechnicianSelectList(params: Record<string, any>) {
    return request.get(`home_service/technician/select`, { params })
}

/**
 * 添加师傅
 * @param params
 * @returns
 */
export function addTechnician(params: Record<string, any>) {
    return request.post('home_service/technician/add', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑师傅
 * @param params
 * @returns
 */
export function editTechnician(params: Record<string, any>) {
    return request.put(`home_service/technician/edit/${ params.id }`, params, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 师傅详情
 * @param id
 * @returns
 */
export function getTechnicianDetail(id: number) {
    return request.get(`home_service/technician/${ id }`)
}

/**
 * 删除师傅
 * @param id
 * @returns
 */
export function deleteTechnician(id: number) {
    return request.delete(`home_service/technician/${ id }`, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 更改师傅状态
 * @param params
 * @returns
 */
export function editTechnicianStatus(params: Record<string, any>) {
    return request.put(`home_service/technician/status/${ params.id }`, params, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 获取师傅列表(关联会员)
 * @returns
 */
export function getMemberList(params: Record<string, any>) {
    return request.get(`home_service/technician/selectmember`, { params })
}

/**
 * 获取师傅列表(支持服务)
 * @param params
 * @returns
 */
export function getTechnicianGoods(params: Record<string, any>) {
    return request.get(`home_service/technician/goods/${ params.id }`, { params })
}

/***************************************************** 师傅岗位 ****************************************************/
/**
 * 获取岗位列表
 * @param params
 * @returns
 */
export function getPositionList(params: Record<string, any>) {
    return request.get(`home_service/position`, { params })
}

/**
 * 获取岗位列表（不分页）
 * @returns
 */
export function getPositionListTo() {
    return request.get(`home_service/position/list`)
}

/**
 * 添加岗位
 * @param params
 * @returns
 */
export function addPosition(params: Record<string, any>) {
    return request.post('home_service/position/edit', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑岗位
 * @returns
 */
export function editPosition(params: Record<string, any>) {
    return request.put(`home_service/position/edit/${ params.id }`, params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 岗位详情
 * @returns
 */
export function getPositionDetail(id: number) {
    return request.get(`home_service/position/${ id }`)
}

/**
 * 删除岗位
 * @returns
 */
export function deletePosition(id: number) {
    return request.delete(`home_service/position/${ id }`, { showErrorMessage: true, showSuccessMessage: true })
}


/**
 * 获取门店列表（不分页）
 * @returns
 */
export function getStoreListTo(params: Record<string, any>) {
    return request.get(`home_service/store`, { params })
}



/**
 * 获取分成方式
 * @returns
 */
export function getdistributetype() {
    return request.get(`home_service/technician/distributetype`)
}


/**
 * 审核列表
 * @returns
 */
export function getTechnicianapplication(params) {
    return request.get(`home_service/technicianapplication`, { params })
}




/**
 * 获取审核状态
 * @returns
 */
export function getApplyStatus(params) {
    return request.get(`home_service/technicianapplication/status`, { params })
}


/**
 * 获取审核详情
 * @returns
 */
export function getApplyDetail(id) {
    return request.get(`home_service/technicianapplication/info/${ id }`)
}



/**
 * 审核通过
 * @returns
 */
export function setExamine(params) {
    return request.put(`home_service/technicianapplication/examine/${ params.id }`,params)
}


/**
 * 审核通过
 * @returns
 */
export function getStoreList(params) {
    return request.get(`home_service/store`,params)
}


/**
 * 师傅详情订单列表
 * @returns
 */
export function getTechnicianDetailorder(params) {
    return request.get(`home_service/technician/order`, { params })
}

/**
 * 师傅详情账单流水列表
 * @returns
 */
export function getTechnicianDetailAccount(params) {
    return request.get(`home_service/technician/${params.technician_id}/account`, { params })
}

/**
 * 师傅详情评价列表
 * @returns
 */
export function getTechnicianAccount(params) {
    return request.get(`home_service/technician/evaluate`, { params })
}


/**
 * 师傅详情评价统计数据
 * @returns
 */
export function getTchevalstats(params) {
    return request.get(`home_service/technician/techevalstats`, { params })
}


/**
 * 师傅详情投诉列表
 * @returns
 */
export function getTechnicianRefund(params) {
    return request.get(`home_service/technician/refund`, { params })
}


/**
 * 师傅详情投诉统计数据
 * @returns
 */
export function getTechrefundstats(params) {
    return request.get(`home_service/technician/techrefundstats`, { params })
}

/**
 * 入驻类型
 * @returns
 */
export function getTechnicianSource(params) {
    return request.get(`home_service/technician/source`, { params })
}


/**
 * 设置休息时间
 * @returns
 */
export function setTechnicianRest(params) {
    return request.post(`home_service/technician/rest`, params )
}

/**
 * 设置休息时间
 * @returns
 */
export function getWorkTimeRecords(params) {
    return request.get(`home_service/technician/restMonthstats`, { params })
}
/**
 * 获取请假理由
 * @returns
 */
export function getTechnicianrestreason(params) {
    return request.get(`home_service/technician/restreason`, {params} )
}

