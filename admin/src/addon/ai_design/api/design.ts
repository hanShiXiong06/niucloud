import request from '@/utils/request'

/**
 * 获取设计列表
 */
export const getDesignList = (params: any) => {
  return request.get('/adminapi/ai_design/design/index', { params })
}

/**
 * 创建设计
 */
export const createDesign = (data: any) => {
  return request.post('/adminapi/ai_design/design/create', data)
}

