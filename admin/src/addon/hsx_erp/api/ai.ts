import request from '@/utils/request'

/**
 * 读取站点 AI 配置（密钥脱敏）
 */
export function getAiConfig() {
    return request.get('erp/ai/config')
}

/**
 * 保存站点 AI 配置
 */
export function saveAiConfig(data: Record<string, any>) {
    return request.post('erp/ai/config', data, { showSuccessMessage: true })
}

/**
 * 拉取可用模型列表（云雾 /v1/models）
 */
export function getAiModels() {
    return request.get('erp/ai/models')
}

/**
 * 连通性测试
 */
export function pingAi(model = '') {
    return request.post('erp/ai/ping', { model })
}

/**
 * 聊天测试
 * @param messages 完整 messages 数组
 * @param model    指定模型，留空用默认
 */
export function chatAi(messages: Array<{ role: string; content: string }>, model = '') {
    return request.post('erp/ai/chat', { messages, model })
}

/**
 * 场景清单
 */
export function getAiScenes() {
    return request.get('erp/ai/scenes')
}

/**
 * 按场景执行（通用组件入口）
 * @param scene    场景 key（general/summary/finance/report/inspect/...）
 * @param payload  { context, question, model, history }
 */
export function runAi(scene: string, payload: { context?: any; question?: string; model?: string; history?: Array<{ role: string; content: string }> } = {}) {
    return request.post('erp/ai/run', { scene, ...payload })
}

/**
 * 对话历史列表
 */
export function getAiConversations(scene = '') {
    return request.get('erp/ai/conversations', { params: { scene } })
}

/**
 * 对话详情
 */
export function getAiConversation(id: number) {
    return request.get(`erp/ai/conversation/${ id }`)
}

/**
 * 保存对话（新建/更新）
 */
export function saveAiConversation(data: Record<string, any>) {
    return request.post('erp/ai/conversation/save', data)
}

/**
 * 删除对话
 */
export function deleteAiConversation(id: number) {
    return request.delete(`erp/ai/conversation/${ id }`)
}
