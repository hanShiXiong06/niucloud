import request from '@/utils/request'


/***************************************************** 门店 ****************************************************/

/**
 * 获取门店列表(分页)
 * @param params
 * @returns
 */
export function getstoreList(params: Record<string, any>) {
    return request.get(`home_service/store`, { params })
}

/**
 * 获取门店列表（不分页）
 * @returns
 */
export function getstoreListTo() {
    return request.get(`home_service/store/list`)
}

/**
 * 获取门店列表（不分页 - 弹窗）
 * @returns
 */
export function getstoreSelectList(params: Record<string, any>) {
    return request.get(`home_service/store/select`, { params })
}

/**
 * 添加门店
 * @param params
 * @returns
 */
export function addstore(params: Record<string, any>) {
    return request.post('home_service/store', params, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 编辑门店
 * @param params
 * @returns
 */
export function editstore(params: Record<string, any>) {
    return request.put(`home_service/store/edit/${ params.id }`, params, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 门店详情
 * @param id
 * @returns
 */
export function getstoreDetail(id: number) {
    return request.get(`home_service/store/${ id }`)
}

/**
 * 删除门店
 * @param id
 * @returns
 */
export function deletestore(id: number) {
    return request.delete(`home_service/store/${ id }`, { showErrorMessage: true, showSuccessMessage: true })
}

/**
 * 更改门店状态
 * @param params
 * @returns
 */
export function editstoreStatus(params: Record<string, any>) {
    return request.put(`home_service/store/status/${ params.id }`, params, {
        showErrorMessage: true,
        showSuccessMessage: true
    })
}

/**
 * 获取门店列表(关联会员)
 * @returns
 */
export function getMemberList(params: Record<string, any>) {
    return request.get(`home_service/store/selectmember`, { params })
}

/**
 * 获取门店列表(支持服务)
 * @param params
 * @returns
 */
export function getstoreGoods(params: Record<string, any>) {
    return request.get(`home_service/store/goods/${ params.id }`, { params })
}

/**
 * 获取门店列表（不分页）
 * @returns
 */
export function getStoreListTo() {
    return request.get(`home_service/store`)
}



/**
 * 获取分成方式
 * @returns
 */
export function getdistributetype() {
    return request.get(`home_service/store/distributetype`)
}


/**
 * 审核列表
 * @returns
 */
export function getstoreapplication(params) {
    return request.get(`home_service/storeapplication`, { params })
}




/**
 * 获取审核状态
 * @returns
 */
export function getApplyStatus(params) {
    return request.get(`home_service/storeapplication/status`, { params })
}


/**
 * 获取审核详情
 * @returns
 */
export function getApplyDetail(id) {
    return request.get(`home_service/storeapplication/info//${ id }`)
}



/**
 * 审核通过
 * @returns
 */
export function setExamine(params) {
    return request.put(`home_service/storeapplication/examine/${ params.id }`,params)
}




/**
 * 门店详情订单列表
 * @returns
 */
export function getstoreDetailorder(params) {
    return request.get(`home_service/store/order`, { params })
}

/**
 * 门店详情账单流水列表
 * @returns
 */
export function getstoreDetailAccount(params) {
    return request.get(`home_service/store/${params.store_id}/account`, { params })
}

/**
 * 门店详情评价列表
 * @returns
 */
export function getstoreAccount(params) {
    return request.get(`home_service/store/storeevalstats`, { params })
}


/**
 * 门店详情评价统计数据
 * @returns
 */
export function getTchevalstats(params) {
    return request.get(`home_service/store/storeevalstats`, { params })
}


/**
 * 门店详情投诉列表
 * @returns
 */
export function getstoreRefund(params) {
    return request.get(`home_service/store/refund`, { params })
}


/**
 * 门店详情投诉统计数据
 * @returns
 */
export function getTechrefundstats(params) {
    return request.get(`home_service/store/storerefundstats`, { params })
}

/**
 * 门店详情评价列表
 * @returns
 */
export function getStoreAccount(params) {
    return request.get(`home_service/store/evaluate`, { params })
}

/**
 * 门店详情投诉列表
 * @returns
 */
export function getStoreRefund(params) {
    return request.get(`home_service/Store/refund`, { params })
}

/**
 * 门店详情门店列表
 * @returns
 */
export function getstoreDetailtechnician(params) {
    return request.get(`home_service/store/${params.store_id}/technician`, { params })
}

/**
 * 门店详情门店列表
 * @returns
 */
export function editStore(params) {
    return request.put(`home_service/store/${params.store_id}`,  params)
}

/**
 * 门店详情门店列表
 * @returns
 */
export function setTechnicanRatio(params) {
    return request.post(`home_service/store/technician/rate`,  params)
}

