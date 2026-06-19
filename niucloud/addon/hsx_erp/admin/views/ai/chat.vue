<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">AI 测试对话</div>
                    <div class="mt-1 text-sm text-gray-500">用于打通云雾接口、验证配置是否正确。确认无误后再接入业务场景。</div>
                </div>
                <div class="flex items-center gap-2">
                    <el-select v-model="model" placeholder="选择模型" size="default" class="!w-48" filterable allow-create>
                        <el-option v-for="m in models" :key="m" :label="m" :value="m" />
                    </el-select>
                    <el-button @click="clearChat" :disabled="loading">清空</el-button>
                </div>
            </div>

            <el-alert
                v-if="!config.enabled"
                class="mt-4"
                type="warning"
                :closable="false"
                show-icon
                title="AI 功能未开启"
                description="请先到「AI 配置」填写密钥、选择模型并开启开关，否则对话无法使用。"
            />

            <div ref="scrollRef" class="chat-window mt-4">
                <div v-if="!messages.length" class="empty-tip">
                    输入任意内容开始测试，例如「用一句话介绍二手手机回收业务」
                </div>
                <div
                    v-for="(msg, idx) in messages"
                    :key="idx"
                    class="msg-row"
                    :class="msg.role === 'user' ? 'is-user' : 'is-assistant'"
                >
                    <div class="msg-avatar">{{ msg.role === 'user' ? '我' : 'AI' }}</div>
                    <div class="msg-bubble" v-text="msg.content"></div>
                </div>
                <div v-if="loading" class="msg-row is-assistant">
                    <div class="msg-avatar">AI</div>
                    <div class="msg-bubble text-gray-400">思考中…</div>
                </div>
            </div>

            <div class="mt-4 flex items-end gap-3">
                <el-input
                    v-model="input"
                    type="textarea"
                    :rows="3"
                    resize="none"
                    placeholder="输入内容，Enter 发送，Shift+Enter 换行"
                    @keydown="onKeydown"
                />
                <el-button type="primary" :loading="loading" @click="send" class="!h-[72px] !w-24">发送</el-button>
            </div>

            <div v-if="lastUsage" class="mt-2 text-right text-xs text-gray-400">
                本次用量：{{ lastUsage.total_tokens || 0 }} tokens（模型 {{ lastModel }}）
            </div>
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { onMounted, nextTick, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getAiConfig, chatAi } from '@/addon/hsx_erp/api/ai'

interface ChatMessage {
    role: 'user' | 'assistant'
    content: string
}

const config = reactive<Record<string, any>>({ enabled: 0, available_models: [], default_model: '' })
const models = ref<string[]>([])
const model = ref('')
const messages = ref<ChatMessage[]>([])
const input = ref('')
const loading = ref(false)
const scrollRef = ref<HTMLElement | null>(null)
const lastUsage = ref<Record<string, any> | null>(null)
const lastModel = ref('')

const loadConfig = async () => {
    const res: any = await getAiConfig()
    Object.assign(config, res.data || {})
    models.value = config.available_models || []
    model.value = config.default_model || models.value[0] || ''
}

const scrollToBottom = () => {
    nextTick(() => {
        if (scrollRef.value) scrollRef.value.scrollTop = scrollRef.value.scrollHeight
    })
}

const onKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        send()
    }
}

const send = async () => {
    const text = input.value.trim()
    if (!text) return
    if (loading.value) return

    messages.value.push({ role: 'user', content: text })
    input.value = ''
    scrollToBottom()
    loading.value = true

    try {
        const payload = messages.value.map(m => ({ role: m.role, content: m.content }))
        const res: any = await chatAi(payload, model.value)
        const data = res.data || {}
        messages.value.push({ role: 'assistant', content: data.content || '(空回复)' })
        lastUsage.value = data.usage || null
        lastModel.value = data.model || model.value
    } catch (err: any) {
        // 错误已由请求拦截器统一弹窗，这里回滚最后一条用户消息提示可重发
        ElMessage.error(err?.msg || '请求失败')
    } finally {
        loading.value = false
        scrollToBottom()
    }
}

const clearChat = () => {
    messages.value = []
    lastUsage.value = null
}

onMounted(loadConfig)
</script>

<style lang="scss" scoped>
.chat-window {
    height: 480px;
    overflow-y: auto;
    padding: 16px;
    background: #f7f8fa;
    border-radius: 8px;
}

.empty-tip {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #b0b3bb;
    font-size: 14px;
}

.msg-row {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    align-items: flex-start;

    &.is-user {
        flex-direction: row-reverse;
    }
}

.msg-avatar {
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    color: #fff;
    background: #909399;
}

.is-user .msg-avatar {
    background: #409eff;
}

.msg-bubble {
    max-width: 70%;
    padding: 10px 14px;
    border-radius: 8px;
    background: #fff;
    font-size: 14px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.is-user .msg-bubble {
    background: #e8f3ff;
}
</style>
