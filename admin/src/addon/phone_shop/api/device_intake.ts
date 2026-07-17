import request from '@/utils/request'

/**
 * 待上架货源 分页列表
 */
export function getDeviceIntakePages(params: Record<string, any>) {
    return request.get('phone_shop/device_intake', { params })
}

/**
 * 待上架货源 详情
 */
export function getDeviceIntakeInfo(id: number) {
    return request.get(`phone_shop/device_intake/${id}`)
}

/**
 * 待上架货源 状态标记（1已建品 / 2忽略 / 0回到待建品）
 */
export function setDeviceIntakeStatus(params: Record<string, any>) {
    return request.put('phone_shop/device_intake/status', params, { showSuccessMessage: true })
}

/**
 * 待建品数量
 */
export function getDeviceIntakePendingCount() {
    return request.get('phone_shop/device_intake/pending_count')
}

export function getDeviceIntakeMaterialPolicy() {
    return request.get('phone_shop/device_intake/material_policy')
}

/**
 * 由货源建品并上架
 */
export function buildDeviceIntake(params: Record<string, any>) {
    return request.post('phone_shop/device_intake/build', params, { showSuccessMessage: true, showErrorMessage: true })
}

/**
 * 建品预览：按"清洗映射引擎+站点配置"算出 6 字段默认，供表单预填
 */
export function previewDeviceIntake(params: Record<string, any>) {
    return request.post('phone_shop/device_intake/preview', params)
}

/**
 * 上架映射配置（扩展口）：读取
 */
export function getIntakeMappingConfig() {
    return request.get('phone_shop/device_intake/mapping_config')
}

/**
 * 上架映射配置（扩展口）：保存
 */
export function saveIntakeMappingConfig(params: Record<string, any>) {
    return request.post('phone_shop/device_intake/mapping_config', params, { showSuccessMessage: true })
}

/**
 * 手动同步表结构（给老库补缺列，等价 reinstall 跑迁移）
 */
export function syncDeviceIntakeSchema() {
    return request.get('phone_shop/device_intake/sync_schema')
}

/**
 * 【临时】造测试货源数据（上线前删）
 */
export function seedTestDeviceIntake() {
    return request.get('phone_shop/device_intake/seed_test')
}
