import request from '@/utils/request'

// 代理关系分页
export function getAgentList(params: Record<string, any>) {
    return request.get('phone_shop/agent', { params })
}
// 主站添加代理关系
export function addAgent(data: Record<string, any>) {
    return request.post('phone_shop/agent', data)
}
// 编辑（加价/订阅/状态）
export function editAgent(id: number, data: Record<string, any>) {
    return request.put(`phone_shop/agent/${id}`, data)
}
// 删除代理关系
export function deleteAgent(id: number) {
    return request.delete(`phone_shop/agent/${id}`)
}
// 取主站配置（含当前站是否主站）
export function getAgentMasterConfig() {
    return request.get('phone_shop/agent/master_config')
}
// 子站一键同步主站商品
export function syncAgentGoods() {
    return request.post('phone_shop/agent/sync_goods', {})
}
// 设置主站ID
export function setAgentMasterConfig(data: Record<string, any>) {
    return request.post('phone_shop/agent/master_config', data)
}
