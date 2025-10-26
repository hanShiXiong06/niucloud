import request from '@/utils/request'

/**
 * 获取我的门店信息
 * @returns 门店信息数据
 */
export function getMyStore() {
  return request.get('home_service/technician/mystore')
}

/**
 * 获取门店信息
 * @returns 门店信息数据
 */
export function getStoreInfo() {
  return request.get('home_service/store/info')
}