import request from '@/utils/request'

/**
 * 获取门店信息
 * @returns 门店信息数据
 */
export function getStoreInfo() {
  return request.get('home_service/store/info',{},{  showErrorMessage: false })
}

/**
 * 更新门店联系方式
 * @param params 更新参数
 * @returns 更新结果
 */
export function updateStoreContact(params: Record<string, any>) {
  return request.put('home_service/store/editContact', params, { showSuccessMessage: true })
}

/**
 * 获取门店今日信息
 * @returns 门店信息数据
 */
export function getStoreTodayInfo() {
  return request.get('home_service/store/statistics/todayData')
}


/**
 * 获取门店看板数据
 * @returns 门店信息数据
 */
export function getStoreOrderdashboard() {
  return request.get('home_service/store/statistics/orderdashboard')
}

/**
 * 获取门店列表
 * @param params 查询参数
 * @returns 门店列表数据
 */
export function getStoreList(params: Record<string, any>) {
  return request.get('home_service/store/lists',  params )
}


/**
 * 门店入驻申请资料
 * @param params 门店入驻申请参数
 * @returns
 */
export function getStoreApply() {
    return request.get(`home_service/store/apply`)
}



/**
 * 门店入驻申请
 * @param params 门店入驻申请参数
 * @returns
 */
export function applyStore(params: Record<string, any>) {
    return request.post(`home_service/store/apply`, params, { showErrorMessage: true })
}


/**
 * 门店重新申请（修改审核被拒绝的申请）
 * @param id 门店申请ID
 * @param params 门店重新申请参数
 * @returns
 */
export function reapplyStore(id: string | number, params: Record<string, any>) {
    // 根据提供的curl命令，使用PUT请求并包含id参数
    return request.put(`home_service/store/apply/${id}`, params, { showSuccessMessage: true,showErrorMessage: true })
}

/**
 * 切换门店
 * @param storeId 门店ID
 * @returns 切换结果
 */
export function storeSwitch(storeId: string | number) {
  return request.put(`home_service/store/storeSwitch?store_id=${storeId}`, {showSuccessMessage: true, showErrorMessage: true })
}



