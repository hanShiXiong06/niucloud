<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import HsxBlockRenderer from '@/addon/hsx_components/components/HsxBlockRenderer/index.vue'
import HsxIcon from '@/addon/hsx_components/components/HsxIcon/index.vue'
import HsxMarkdownRenderer from '@/addon/hsx_components/components/HsxMarkdownRenderer/index.vue'
import HsxTag from '@/addon/hsx_components/components/HsxTag/index.vue'
import { useAutoFollowScroll } from '@/addon/hsx_components/hooks'
import '@/addon/hsx_components/styles/theme.scss'
import { deleteAiAssistantConversation, getAiAssistantConfig, getAiAssistantConversation, getAiAssistantConversations, streamAi } from '../../api'

type HsxContentBlock = { type: 'stat_grid' | 'table' | 'chart' | 'notice' | 'action_group'; source_plugin?: string; data?: Record<string, any> }
type Agent = {
    key: string; name: string; short_name: string; description: string; icon: string; tone: string;
    tool_keys: string[]; quick_prompts: string[]; quick_actions?: QuickAction[]; capability_count: number; read_only: boolean
}
type QuickAction = { text: string; tool_key?: string }
type ChatMessage = {
    localId: string; role: 'user' | 'assistant'; content: string; reasoning: string; blocks: HsxContentBlock[];
    status: 'success' | 'streaming' | 'failed'; error: string; toolStatus: string; createAt: number
}
type ConversationSummary = {
    id: number; title: string; agent_key: string; message_count: number; last_message_at: number; status: string
}

const router = useRouter()
const loading = ref(true)
const sending = ref(false)
const agents = ref<Agent[]>([])
const activeAgentKey = ref('')
const actor = ref<any>({})
const operationPolicy = ref<any>({})
const messages = ref<ChatMessage[]>([])
const recentConversations = ref<ConversationSummary[]>([])
const conversationsLoading = ref(false)
const prompt = ref('')
const conversationId = ref(0)
const conversationTitle = ref('')
const page = ref<HTMLElement | null>(null)
const pageHeight = ref('calc(100dvh - 134px)')
const scroller = ref<HTMLElement | null>(null)
const { isFollowing, handleScroll, scrollToBottom, scheduleScrollToBottom, resumeFollowing } = useAutoFollowScroll(scroller, {
    threshold: 72,
    interval: 80
})
const showReasoning = ref(false)
let controller: AbortController | null = null
let paintTimer: number | null = null
let pendingContent = ''
let pendingReasoning = ''
let heightFrame: number | null = null

const activeAgent = computed(() => agents.value.find((item) => item.key === activeAgentKey.value))
const quickActions = computed<QuickAction[]>(() => {
    if (activeAgent.value?.quick_actions?.length) return activeAgent.value.quick_actions
    return (activeAgent.value?.quick_prompts || []).map((text) => ({ text }))
})
const emptyTitle = computed(() => activeAgent.value ? `向${activeAgent.value.name}提问` : '暂无可用智能体')
const storageKey = (agentKey: string) => `hsx_ai_admin_assistant_conversation_${agentKey}`
const makeId = () => `${Date.now()}_${Math.random().toString(36).slice(2)}`

function normalizeBlocks(value: any): HsxContentBlock[] {
    const allowed = new Set(['stat_grid', 'table', 'chart', 'notice', 'action_group'])
    return (Array.isArray(value) ? value : []).filter((item) => item && allowed.has(String(item.type))).slice(0, 16)
}

function toMessage(row: any): ChatMessage {
    return {
        localId: String(row.id || makeId()),
        role: row.role === 'user' ? 'user' : 'assistant',
        content: String(row.content || ''),
        reasoning: String(row.reasoning || ''),
        blocks: normalizeBlocks(row.blocks),
        status: row.status === 'failed' ? 'failed' : 'success',
        error: String(row.error || ''),
        toolStatus: '',
        createAt: Number(row.create_at || Math.floor(Date.now() / 1000))
    }
}

function rememberConversation(id: number) {
    conversationId.value = Math.max(0, Number(id || 0))
    if (!activeAgentKey.value) return
    try {
        if (conversationId.value) sessionStorage.setItem(storageKey(activeAgentKey.value), String(conversationId.value))
        else sessionStorage.removeItem(storageKey(activeAgentKey.value))
    } catch (_) {}
}

function syncPageHeight() {
    if (heightFrame !== null) window.cancelAnimationFrame(heightFrame)
    heightFrame = window.requestAnimationFrame(() => {
        heightFrame = null
        if (!page.value) return
        const rect = page.value.getBoundingClientRect()
        const parentStyle = page.value.parentElement ? window.getComputedStyle(page.value.parentElement) : null
        const bottomGap = Number.parseFloat(parentStyle?.paddingBottom || '0') || 0
        pageHeight.value = `${Math.max(0, Math.floor(window.innerHeight - rect.top - bottomGap))}px`
    })
}

function takePaintCharacter(value: string) {
    const point = value.codePointAt(0)
    if (point === undefined) return ['', '']
    const character = String.fromCodePoint(point)
    return [character, value.slice(character.length)]
}

function paintStream(message: ChatMessage) {
    paintTimer = null
    if (pendingReasoning) {
        const [chunk, rest] = takePaintCharacter(pendingReasoning)
        message.reasoning += chunk
        pendingReasoning = rest
    }
    if (pendingContent) {
        const [chunk, rest] = takePaintCharacter(pendingContent)
        message.content += chunk
        pendingContent = rest
    }
    scheduleScrollToBottom()
    if (pendingContent || pendingReasoning) schedulePaint(message)
}

function schedulePaint(message: ChatMessage) {
    if (paintTimer !== null) return
    paintTimer = window.setTimeout(() => paintStream(message), 16)
}

async function drainPaintQueue(message: ChatMessage) {
    if (pendingContent || pendingReasoning) schedulePaint(message)
    while (paintTimer !== null || pendingContent || pendingReasoning) {
        await new Promise((resolve) => window.setTimeout(resolve, 18))
    }
}

async function openConversation(id: number) {
    if (!id || sending.value) return
    try {
        const data: any = (await getAiAssistantConversation(id)).data || {}
        if (String(data.conversation?.agent_key || '') !== activeAgentKey.value) throw new Error('agent_changed')
        rememberConversation(id)
        conversationTitle.value = String(data.conversation?.title || '')
        messages.value = (data.messages || []).map(toMessage)
        await scrollToBottom(true)
    } catch (_) {
        rememberConversation(0)
        messages.value = []
        conversationTitle.value = ''
    }
}

async function deleteConversation(item: ConversationSummary) {
    if (sending.value) return
    try {
        await ElMessageBox.confirm(`删除会话“${item.title}”？删除后不能恢复。`, '删除会话', {
            type: 'warning', confirmButtonText: '删除', cancelButtonText: '取消'
        })
        await deleteAiAssistantConversation(item.id)
        if (conversationId.value === item.id) {
            rememberConversation(0)
            conversationTitle.value = ''
            messages.value = []
            resumeFollowing()
        }
        await loadRecentConversations(false)
        ElMessage.success('会话已删除')
    } catch (error: any) {
        if (error !== 'cancel' && error !== 'close') ElMessage.error(error?.message || error?.msg || '删除会话失败')
    }
}

async function loadRecentConversations(openLatest = false) {
    const agentKey = activeAgentKey.value
    if (!agentKey) {
        recentConversations.value = []
        return
    }
    conversationsLoading.value = true
    try {
        const rows: any[] = (await getAiAssistantConversations({ agent_key: agentKey, limit: 20 })).data || []
        if (agentKey !== activeAgentKey.value) return
        recentConversations.value = Array.isArray(rows) ? rows.map((row) => ({
            id: Number(row.id || 0),
            title: String(row.title || '经营查询'),
            agent_key: String(row.agent_key || ''),
            message_count: Number(row.message_count || 0),
            last_message_at: Number(row.last_message_at || 0),
            status: String(row.status || 'active')
        })) : []
        if (!openLatest) return
        let rememberedId = 0
        try { rememberedId = Number(sessionStorage.getItem(storageKey(agentKey)) || 0) } catch (_) {}
        const target = recentConversations.value.find((item) => item.id === rememberedId) || recentConversations.value[0]
        if (target) await openConversation(target.id)
        else rememberConversation(0)
    } finally {
        if (agentKey === activeAgentKey.value) conversationsLoading.value = false
    }
}

function formatConversationTime(timestamp: number) {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    const now = new Date()
    const pad = (value: number) => String(value).padStart(2, '0')
    return date.toDateString() === now.toDateString()
        ? `${pad(date.getHours())}:${pad(date.getMinutes())}`
        : `${date.getMonth() + 1}-${pad(date.getDate())}`
}

async function selectAgent(agent: Agent) {
    if (sending.value || agent.key === activeAgentKey.value) return
    activeAgentKey.value = agent.key
    messages.value = []
    conversationTitle.value = ''
    conversationId.value = 0
    resumeFollowing()
    await loadRecentConversations(true)
}

function newConversation() {
    if (sending.value) return
    rememberConversation(0)
    conversationTitle.value = ''
    messages.value = []
    prompt.value = ''
    resumeFollowing()
}

function sendQuick(action: QuickAction) {
    if (sending.value) return
    prompt.value = action.text
    void sendMessage(action.tool_key || '')
}

async function sendMessage(defaultToolKey = '') {
    const content = prompt.value.trim()
    if (!content || sending.value || !activeAgent.value) return
    const userMessage: ChatMessage = { localId: makeId(), role: 'user', content, reasoning: '', blocks: [], status: 'success', error: '', toolStatus: '', createAt: Math.floor(Date.now() / 1000) }
    const assistantDraft: ChatMessage = { localId: makeId(), role: 'assistant', content: '', reasoning: '', blocks: [], status: 'streaming', error: '', toolStatus: '正在理解问题', createAt: Math.floor(Date.now() / 1000) }
    messages.value.push(userMessage, assistantDraft)
    // 后续必须修改数组中的响应式代理；修改 push 前的原始对象只会在请求结束时一次性刷新。
    const assistantMessage = messages.value[messages.value.length - 1]
    prompt.value = ''
    sending.value = true
    pendingContent = ''
    pendingReasoning = ''
    controller = new AbortController()
    await scrollToBottom(true)
    try {
        await streamAi({
            scene_key: 'business.admin_assistant',
            agent_key: activeAgent.value.key,
            conversation_id: conversationId.value,
            prompt: content,
            default_tool_key: defaultToolKey,
            response_mode: 'text',
            stream: true
        }, (event) => {
            if (event.type === 'conversation') {
                rememberConversation(Number(event.conversation_id || 0))
                conversationTitle.value = String(event.conversation_title || conversationTitle.value)
            } else if (event.type === 'tool') {
                assistantMessage.toolStatus = event.status === 'running' ? `正在查询：${event.label || event.tool_key || '业务数据'}` : ''
            } else if (event.type === 'block') {
                const block = normalizeBlocks([event.block || event.item])[0]
                if (block) assistantMessage.blocks.push(block)
                scheduleScrollToBottom()
            } else if (event.type === 'reasoning') {
                assistantMessage.reasoning += String(event.delta || '')
            } else if (event.type === 'content') {
                pendingContent += String(event.delta || '')
                schedulePaint(assistantMessage)
            } else if (event.type === 'done') {
                const blocks = normalizeBlocks(event.blocks)
                if (blocks.length) assistantMessage.blocks = blocks
                rememberConversation(Number(event.conversation_id || conversationId.value))
                conversationTitle.value = String(event.conversation_title || conversationTitle.value)
                assistantMessage.toolStatus = ''
            } else if (event.type === 'error') {
                assistantMessage.error = String(event.message || '经营助手暂时无法回答')
                assistantMessage.status = 'failed'
            }
        }, controller.signal)
    } catch (error: any) {
        if (error?.name !== 'AbortError') {
            assistantMessage.error = error?.message || error?.msg || '经营助手暂时无法回答'
            assistantMessage.status = 'failed'
            ElMessage.error(assistantMessage.error)
        }
    } finally {
        await drainPaintQueue(assistantMessage)
        if (assistantMessage.status === 'streaming') assistantMessage.status = assistantMessage.content || assistantMessage.blocks.length ? 'success' : 'failed'
        sending.value = false
        controller = null
        await loadRecentConversations(false)
        await scrollToBottom()
    }
}

function stopGenerating() {
    controller?.abort()
}

function handleAction(action: Record<string, any>) {
    if (action.approved !== true || action.type !== 'route') {
        ElMessage.warning('该操作未通过系统授权')
        return
    }
    const route = String(action.route || '')
    if (!route.startsWith('/site/')) {
        ElMessage.warning('目标页面不在管理端安全范围内')
        return
    }
    const query: Record<string, string> = {}
    Object.entries(action.params || {}).forEach(([key, value]) => {
        if (/^[a-zA-Z0-9_]{1,60}$/.test(key) && ['string', 'number', 'boolean'].includes(typeof value)) query[key] = String(value)
    })
    void router.push({ path: route, query })
}

async function initialize() {
    loading.value = true
    try {
        const data: any = (await getAiAssistantConfig()).data || {}
        agents.value = Array.isArray(data.agents) ? data.agents : []
        actor.value = data.actor || {}
        operationPolicy.value = data.operation_policy || {}
        activeAgentKey.value = String(data.default_agent_key || agents.value[0]?.key || '')
        if (activeAgentKey.value) await loadRecentConversations(true)
    } finally {
        loading.value = false
        await nextTick()
        syncPageHeight()
    }
}

onMounted(() => {
    window.addEventListener('resize', syncPageHeight)
    void initialize()
})
onBeforeUnmount(() => {
    controller?.abort()
    if (paintTimer !== null) window.clearTimeout(paintTimer)
    if (heightFrame !== null) window.cancelAnimationFrame(heightFrame)
    window.removeEventListener('resize', syncPageHeight)
})
</script>

<template>
    <div ref="page" v-loading="loading" class="main-container assistant-page" :style="{ height: pageHeight }">
        <header class="assistant-header">
            <div>
                <h1>经营助手</h1>
                <p>每个回答都基于当前账号的数据权限和已接入的业务能力</p>
            </div>
            <div class="header-meta">
                <HsxTag :text="actor.is_site_admin ? '站点管理员' : (actor.roles || []).join('、') || '业务成员'" tone="info" />
                <el-button :disabled="sending || !activeAgent" @click="newConversation"><HsxIcon name="element Plus" />新对话</el-button>
            </div>
        </header>

        <div v-if="agents.length" class="assistant-workspace">
            <aside class="agent-rail">
                <div class="agent-rail__title">我的智能体</div>
                <button
                    v-for="agent in agents" :key="agent.key" type="button" class="agent-item"
                    :class="{ 'is-active': agent.key === activeAgentKey }" :disabled="sending"
                    @click="selectAgent(agent)"
                >
                    <span class="agent-item__icon" :class="`is-${agent.tone || 'primary'}`"><HsxIcon :name="agent.icon" :size="19" /></span>
                    <span class="agent-item__copy"><strong>{{ agent.short_name || agent.name }}</strong><small>{{ agent.description }}</small></span>
                    <span class="agent-item__count">{{ agent.capability_count }}</span>
                </button>
                <div class="conversation-section">
                    <div class="conversation-section__title">
                        <span>最近会话</span>
                        <HsxIcon v-if="conversationsLoading" name="element Loading" :size="13" class="is-loading" />
                    </div>
                    <div v-for="item in recentConversations" :key="item.id" class="conversation-item" :class="{ 'is-active': item.id === conversationId }">
                        <button type="button" class="conversation-item__open" :disabled="sending" @click="openConversation(item.id)">
                            <HsxIcon name="element ChatLineRound" :size="14" />
                            <span><strong>{{ item.title }}</strong><small>{{ item.message_count }} 条消息 · {{ formatConversationTime(item.last_message_at) }}</small></span>
                        </button>
                        <button type="button" class="conversation-item__delete" title="删除会话" :disabled="sending" @click.stop="deleteConversation(item)">
                            <HsxIcon name="element Delete" :size="13" />
                        </button>
                    </div>
                    <div v-if="!conversationsLoading && !recentConversations.length" class="conversation-empty">这里会保留你的历史对话</div>
                </div>
                <div class="permission-note">
                    <HsxIcon name="element Lock" :size="15" />
                    <span>{{ operationPolicy.read || '仅返回当前账号有权查看的数据' }}</span>
                </div>
            </aside>

            <main class="chat-panel">
                <header class="chat-header">
                    <div class="chat-agent">
                        <span class="chat-agent__icon"><HsxIcon :name="activeAgent?.icon" :size="21" /></span>
                        <div><strong>{{ activeAgent?.name }}</strong><small>{{ conversationTitle || activeAgent?.description }}</small></div>
                    </div>
                    <HsxTag :text="activeAgent?.read_only ? '只读查询' : '含受控操作'" :tone="activeAgent?.read_only ? 'success' : 'warning'" />
                </header>

                <div class="message-viewport">
                    <div ref="scroller" class="message-list" tabindex="0" aria-label="会话消息" @scroll.passive="handleScroll">
                        <section v-if="!messages.length" class="assistant-empty">
                            <span class="assistant-empty__icon"><HsxIcon :name="activeAgent?.icon" :size="28" /></span>
                            <h2>{{ emptyTitle }}</h2>
                            <p>{{ activeAgent?.description }}</p>
                            <div class="quick-grid">
                                <button v-for="item in quickActions" :key="item.text" type="button" @click="sendQuick(item)">
                                    <span>{{ item.text }}</span><HsxIcon name="element Right" :size="14" />
                                </button>
                            </div>
                        </section>

                        <article v-for="message in messages" :key="message.localId" class="message" :class="`is-${message.role}`">
                            <div class="message-avatar">
                                <HsxIcon :name="message.role === 'user' ? 'element User' : (activeAgent?.icon || 'element ChatDotRound')" :size="17" />
                            </div>
                            <div class="message-body">
                                <div class="message-meta"><strong>{{ message.role === 'user' ? actor.name || '我' : activeAgent?.name }}</strong></div>
                                <div v-if="message.toolStatus" class="tool-running"><i />{{ message.toolStatus }}</div>
                                <HsxMarkdownRenderer v-if="message.content" :content="message.content" />
                                <div v-else-if="message.status === 'streaming'" class="thinking"><span /><span /><span /><em>正在分析业务数据</em></div>
                                <HsxBlockRenderer v-if="message.blocks.length" class="message-blocks" :blocks="message.blocks" @action="handleAction" />
                                <el-collapse v-if="message.reasoning && showReasoning" class="reasoning-panel">
                                    <el-collapse-item title="推理过程"><pre>{{ message.reasoning }}</pre></el-collapse-item>
                                </el-collapse>
                                <el-alert v-if="message.error" :title="message.error" type="error" :closable="false" show-icon />
                            </div>
                        </article>
                    </div>
                    <button v-if="!isFollowing && messages.length" type="button" class="follow-latest" @click="resumeFollowing">
                        <HsxIcon name="element ArrowDown" :size="14" />回到最新
                    </button>
                </div>

                <footer class="composer">
                    <div v-if="messages.length" class="quick-strip">
                        <button v-for="item in quickActions.slice(0, 3)" :key="item.text" type="button" :disabled="sending" @click="sendQuick(item)">{{ item.text }}</button>
                    </div>
                    <div class="composer-box">
                        <el-input
                            v-model="prompt" type="textarea" :autosize="{ minRows: 2, maxRows: 6 }" maxlength="2000"
                            :disabled="sending || !activeAgent" :placeholder="`问${activeAgent?.short_name || '经营助手'}，例如：${quickActions[0]?.text || '今天经营情况怎么样？'}`"
                            @keydown.enter.exact.prevent="sendMessage()"
                        />
                        <div class="composer-actions">
                            <el-checkbox v-model="showReasoning" label="显示推理" />
                            <span>Enter 发送，Shift + Enter 换行</span>
                            <el-button v-if="sending" :icon="undefined" @click="stopGenerating"><HsxIcon name="element VideoPause" />停止</el-button>
                            <el-button v-else type="primary" :disabled="!prompt.trim() || !activeAgent" @click="sendMessage()"><HsxIcon name="element Promotion" />发送</el-button>
                        </div>
                    </div>
                    <p class="composer-hint">经营助手只提供有权限的本站业务数据；涉及修改、付款等操作必须回到原业务页面确认。</p>
                </footer>
            </main>
        </div>

        <el-empty v-else-if="!loading" description="当前账号暂无可用智能体，请检查业务插件接入和菜单权限" />
    </div>
</template>

<style scoped>
.assistant-page { display: flex; min-height: 0; max-height: 100%; padding: 0; flex-direction: column; overflow: hidden; overscroll-behavior: none; background: #fff; }
.assistant-header { display: flex; min-height: 70px; box-sizing: border-box; align-items: center; justify-content: space-between; gap: 20px; padding: 14px 20px; border-bottom: 1px solid #e8eaed; }
.assistant-header h1 { margin: 0; color: #17191c; font-size: 20px; letter-spacing: 0; }
.assistant-header p { margin: 5px 0 0; color: #858b94; font-size: 12px; }
.header-meta { display: flex; align-items: center; gap: 10px; }
.assistant-workspace { display: grid; min-height: 0; flex: 1; grid-template-columns: 250px minmax(0, 1fr); overflow: hidden; }
.agent-rail { min-height: 0; padding: 16px 12px; border-right: 1px solid #e8eaed; overflow-y: auto; background: #f8f9fb; }
.agent-rail__title { padding: 0 8px 10px; color: #8b9098; font-size: 12px; font-weight: 600; }
.agent-item { display: grid; width: 100%; min-height: 68px; margin-bottom: 6px; padding: 10px; border: 1px solid transparent; border-radius: 7px; grid-template-columns: 36px minmax(0, 1fr) 22px; align-items: center; gap: 9px; color: #303133; text-align: left; cursor: pointer; background: transparent; transition: border-color .18s ease, background .18s ease; }
.agent-item:hover { border-color: #d9dde4; background: #fff; }
.agent-item.is-active { border-color: #b9d3f7; background: #eef5ff; }
.agent-item:disabled { cursor: default; opacity: .65; }
.agent-item__icon, .chat-agent__icon, .assistant-empty__icon { display: inline-flex; width: 36px; height: 36px; border-radius: 7px; align-items: center; justify-content: center; color: #1f6fd1; background: #e9f2ff; }
.agent-item__icon.is-success { color: #16845b; background: #e9f7f1; }
.agent-item__icon.is-warning { color: #ad6c00; background: #fff4dd; }
.agent-item__icon.is-info { color: #326d8c; background: #e9f4f8; }
.agent-item__copy { display: grid; min-width: 0; gap: 4px; }
.agent-item__copy strong { color: #202327; font-size: 14px; }
.agent-item__copy small { display: -webkit-box; color: #878d96; font-size: 11px; line-height: 1.45; overflow: hidden; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.agent-item__count { display: inline-flex; width: 20px; height: 20px; border-radius: 50%; align-items: center; justify-content: center; color: #6c737d; font-size: 11px; background: #e9ecf0; }
.conversation-section { margin-top: 16px; padding-top: 13px; border-top: 1px solid #e1e4e8; }
.conversation-section__title { display: flex; min-height: 26px; padding: 0 8px 7px; align-items: center; justify-content: space-between; color: #8b9098; font-size: 12px; font-weight: 600; }
.conversation-section__title .is-loading { animation: rotate 1s linear infinite; }
.conversation-item { display: grid; width: 100%; min-height: 48px; border: 1px solid transparent; border-radius: 6px; grid-template-columns: minmax(0, 1fr) 26px; align-items: center; color: #7b828c; background: transparent; }
.conversation-item:hover { border-color: #dfe3e8; color: #1769c2; background: #fff; }
.conversation-item.is-active { color: #1769c2; background: #e9f2ff; }
.conversation-item__open { display: grid; min-width: 0; min-height: 46px; padding: 7px 4px 7px 9px; border: 0; grid-template-columns: 18px minmax(0, 1fr); align-items: center; gap: 5px; color: inherit; text-align: left; cursor: pointer; background: transparent; }
.conversation-item__open:disabled, .conversation-item__delete:disabled { cursor: default; opacity: .6; }
.conversation-item__open > span { display: grid; min-width: 0; gap: 3px; }
.conversation-item strong { color: #4b515a; font-size: 12px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.conversation-item.is-active strong { color: #1769c2; }
.conversation-item small { color: #9aa0a8; font-size: 10px; }
.conversation-item__delete { display: inline-flex; width: 24px; height: 24px; padding: 0; border: 0; border-radius: 4px; align-items: center; justify-content: center; color: #a3a8b0; cursor: pointer; background: transparent; opacity: 0; transition: opacity .16s ease, color .16s ease, background .16s ease; }
.conversation-item:hover .conversation-item__delete, .conversation-item.is-active .conversation-item__delete { opacity: 1; }
.conversation-item__delete:hover { color: #d34a4a; background: #fff0f0; }
.conversation-empty { padding: 9px 8px; color: #a3a8b0; font-size: 11px; line-height: 1.5; }
.permission-note { display: flex; margin: 16px 8px 0; padding-top: 14px; border-top: 1px solid #e1e4e8; gap: 7px; color: #8a9099; font-size: 11px; line-height: 1.55; }
.chat-panel { display: grid; min-width: 0; min-height: 0; grid-template-rows: 64px minmax(0, 1fr) auto; overflow: hidden; background: #fff; }
.message-viewport { position: relative; min-width: 0; min-height: 0; overflow: hidden; }
.chat-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 10px 20px; border-bottom: 1px solid #eceef1; }
.chat-agent { display: flex; min-width: 0; align-items: center; gap: 10px; }
.chat-agent__icon { width: 38px; height: 38px; }
.chat-agent div { display: grid; min-width: 0; gap: 3px; }
.chat-agent strong { color: #202327; font-size: 14px; }
.chat-agent small { max-width: 620px; color: #8a9099; font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.message-list { width: 100%; height: 100%; min-height: 0; box-sizing: border-box; padding: 20px max(24px, calc((100% - 980px) / 2)); overflow-x: hidden; overflow-y: auto; overflow-anchor: none; overscroll-behavior: contain; scrollbar-gutter: stable; }
.follow-latest { position: absolute; right: 24px; bottom: 14px; z-index: 2; display: inline-flex; min-height: 32px; padding: 6px 11px; border: 1px solid #c9d9ec; border-radius: 6px; align-items: center; gap: 5px; color: #1769c2; font-size: 12px; cursor: pointer; background: rgba(255, 255, 255, .96); box-shadow: 0 5px 16px rgba(31, 65, 105, .12); }
.follow-latest:hover { border-color: #8cb8eb; background: #f5f9ff; }
.assistant-empty { max-width: 720px; margin: 9vh auto 0; text-align: center; }
.assistant-empty__icon { width: 52px; height: 52px; margin-bottom: 12px; }
.assistant-empty h2 { margin: 0; color: #202327; font-size: 20px; }
.assistant-empty p { margin: 8px 0 22px; color: #858b94; font-size: 13px; }
.quick-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; text-align: left; }
.quick-grid button { display: flex; min-height: 48px; padding: 11px 13px; border: 1px solid #e0e3e8; border-radius: 7px; align-items: center; justify-content: space-between; gap: 12px; color: #454b53; cursor: pointer; background: #fff; }
.quick-grid button:hover { border-color: #9fc3f3; color: #1769c2; background: #f7fbff; }
.message { display: grid; max-width: 980px; margin: 0 auto 22px; grid-template-columns: 34px minmax(0, 1fr); gap: 10px; }
.message-avatar { display: flex; width: 30px; height: 30px; border: 1px solid #dfe3e8; border-radius: 6px; align-items: center; justify-content: center; color: #5b6470; background: #f7f8fa; }
.message.is-assistant .message-avatar { border-color: #cfe0f6; color: #1769c2; background: #edf5ff; }
.message-body { min-width: 0; padding-top: 3px; }
.message.is-user .message-body { padding: 10px 13px; border-radius: 7px; background: #f4f6f8; }
.message-meta { margin-bottom: 6px; color: #6b727c; font-size: 11px; }
.message-meta strong { color: #343941; font-size: 12px; }
.message-blocks { margin-top: 12px; }
.tool-running { display: inline-flex; margin-bottom: 9px; align-items: center; gap: 7px; color: #6e7580; font-size: 12px; }
.tool-running i { width: 7px; height: 7px; border-radius: 50%; background: #409eff; box-shadow: 0 0 0 4px #e9f3ff; animation: pulse 1.2s ease-in-out infinite; }
.thinking { display: flex; height: 28px; align-items: center; gap: 5px; color: #8a9099; font-size: 12px; }
.thinking span { width: 6px; height: 6px; border-radius: 50%; background: #7aaee8; animation: dot 1s ease-in-out infinite; }
.thinking span:nth-child(2) { animation-delay: .14s; }.thinking span:nth-child(3) { animation-delay: .28s; }.thinking em { margin-left: 5px; font-style: normal; }
.reasoning-panel { margin-top: 10px; border: 0; }.reasoning-panel :deep(.el-collapse-item__header) { height: 34px; color: #8a9099; font-size: 12px; }.reasoning-panel pre { margin: 0; white-space: pre-wrap; color: #737983; font-size: 12px; line-height: 1.6; }
.composer { padding: 9px max(24px, calc((100% - 980px) / 2)) 14px; border-top: 1px solid #eceef1; background: #fff; }
.quick-strip { display: flex; margin-bottom: 8px; gap: 7px; overflow-x: auto; }
.quick-strip button { flex: none; padding: 5px 9px; border: 1px solid #dfe3e8; border-radius: 6px; color: #69707a; font-size: 11px; cursor: pointer; background: #fff; }
.quick-strip button:hover { border-color: #a9c9ef; color: #1769c2; }.quick-strip button:disabled { cursor: default; opacity: .55; }
.composer-box { padding: 10px 12px 9px; border: 1px solid #cfd4dc; border-radius: 8px; background: #fff; box-shadow: 0 5px 18px rgba(30, 43, 60, .06); }
.composer-box:focus-within { border-color: #7eafe8; box-shadow: 0 0 0 3px rgba(64, 158, 255, .1); }
.composer-box :deep(.el-textarea__inner) { padding: 0; border: 0; box-shadow: none; resize: none; }
.composer-actions { display: flex; min-height: 30px; margin-top: 8px; align-items: center; gap: 10px; color: #a0a5ad; font-size: 11px; }
.composer-actions > span { flex: 1; text-align: right; }
.composer-hint { margin: 7px 0 0; color: #a1a6ae; font-size: 10px; text-align: center; }
@keyframes dot { 0%, 70%, 100% { opacity: .35; transform: translateY(0); } 35% { opacity: 1; transform: translateY(-3px); } }
@keyframes pulse { 0%, 100% { opacity: .55; } 50% { opacity: 1; } }
@keyframes rotate { to { transform: rotate(360deg); } }
@media (max-width: 900px) { .assistant-workspace { grid-template-columns: 210px minmax(0, 1fr); }.message-list, .composer { padding-right: 18px; padding-left: 18px; }.quick-grid { grid-template-columns: 1fr; } }
</style>
