import request from '@/utils/request'

/**
 * 获取扣费配置分页列表
 */
export function getDeductionConfigPages(params: Record<string, any>) {
    return request.get('recycle/deduction_config/pages', { params })
}

/**
 * 获取扣费配置列表
 */
export function getDeductionConfigList(params: Record<string, any>) {
    return request.get('recycle/deduction_config/lists', { params })
}

/**
 * 获取扣费配置详情
 */
export function getDeductionConfigInfo(id: number) {
    return request.get(`recycle/deduction_config/${id}`)
}

/**
 * 添加扣费配置
 */
export function addDeductionConfig(params: Record<string, any>) {
    return request.post('recycle/deduction_config', params, { showSuccessMessage: true })
}

/**
 * 编辑扣费配置
 */
export function editDeductionConfig(id: number, params: Record<string, any>) {
    return request.put(`recycle/deduction_config/${id}`, params, { showSuccessMessage: true })
}

/**
 * 删除扣费配置
 */
export function deleteDeductionConfig(id: number) {
    return request.delete(`recycle/deduction_config/${id}`, { showSuccessMessage: true })
}

/**
 * 修改扣费配置状态
 */
export function modifyDeductionConfigStatus(params: Record<string, any>) {
    return request.put('recycle/deduction_config/modify_status', params, { showSuccessMessage: true })
}

