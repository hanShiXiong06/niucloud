import request from '@/utils/request'

// 获取包裹规格列表
export const getPackagePrices = (params?: any) => {
  return request.get('sd_xiaoyuan/package_price/list', params)
}

// 添加包裹规格
export const addPackagePrice = (data: any) => {
  return request.post('sd_xiaoyuan/package_price/add', data)
}

// 编辑包裹规格
export const editPackagePrice = (id: number, data: any) => {
  return request.post(`sd_xiaoyuan/package_price/edit/${id}`, data)
}

// 删除包裹规格
export const deletePackagePrice = (id: number) => {
  return request.delete(`sd_xiaoyuan/package_price/del/${id}`)
}

// 获取包裹规格详情
export const getPackagePriceInfo = (id: number) => {
  return request.get(`sd_xiaoyuan/package_price/info/${id}`)
}