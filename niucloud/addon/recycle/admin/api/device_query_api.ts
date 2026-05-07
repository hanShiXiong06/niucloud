import request from '@/utils/request'

export function getDeviceQueryApiList(params: Record<string, any>) {
  return request.get('recycle/device_query_result/lists', { params })
}

export function getDeviceQueryApiInfo(id: number) {
  return request.get(`recycle/device_query_result/${id}`)
}

export function deleteDeviceQueryApi(id: number) {
  return request.delete(`recycle/device_query_result/${id}`)
}

export function initDefaultApiList() {
  return request.post('recycle/device_query_result/clean_cache', { expire_time: 604800 })
}

export function queryDeviceByService(params: Record<string, any>) {
  return request.post('recycle/device_query_api/query', params, { showErrorMessage: false })
}

/**
 * 兼容旧版设备查询接口
 * 这些方法仍被回收订单质检弹窗、订单列表等旧页面直接调用。
 */
export function getCoverage(params: Record<string, any>) {
  return request.get('recycle/device_query_api/coverage', { params })
}

export function getActivationlock(imei: string) {
  return request.get('recycle/device_query_api/activationlock', {
    params: { imei }
  })
}

export function getMdm(imei: string) {
  return request.get('recycle/device_query_api/mdm', {
    params: { imei }
  })
}

export function getExpress(expressCode: string, mobile: string) {
  return request.get('recycle/device_query_api/express', {
    params: {
      express_code: expressCode,
      mobile
    }
  })
}
