import request from '@/utils/request'

// 推荐角色预览
export function getRolePresetPreview() {
    return request.get('erp/role_preset/preview')
}

// 一键生成/更新推荐角色
export function generateRolePreset(data: Record<string, any> = {}) {
    return request.post('erp/role_preset/generate', data)
}
