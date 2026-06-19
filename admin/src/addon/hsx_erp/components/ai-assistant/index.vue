<template>
    <span v-if="allowed" class="ai-assistant-inline">
        <el-button
            :type="buttonType"
            :size="buttonSize"
            :link="link"
            :plain="!link && plain"
            @click="open"
        >
            <el-icon v-if="showIcon" class="mr-1"><MagicStick /></el-icon>
            {{ buttonText }}
        </el-button>

        <el-drawer v-model="visible" :title="drawerTitle" :size="drawerSize" append-to-body class="ai-drawer" @closed="onClosed">
            <template #header>
                <div class="flex w-full items-center justify-between pr-4">
                    <span class="font-medium">{{ drawerTitle }}</span>
                    <div class="flex items-center gap-2">
                        <el-button size="small" text @click="newConversation">新对话</el-button>
                        <el-button size="small" text @click="openHistory">历史</el-button>
                    </div>
                </div>
            </template>

            <div class="ai-chat">
                <div ref="scrollRef" class="ai-thread">
                    <el-empty v-if="!thread.length" description="输入问题开始，例如：查 138xxxx 的账目往来" :image-size="70" />

                    <div v-for="(msg, idx) in thread" :key="idx" class="msg" :class="msg.role">
                        <div class="msg-avatar">{{ msg.role === 'user' ? '我' : 'AI' }}</div>
                        <div class="msg-body">
                            <div v-if="msg.role === 'user'" class="bubble-user" v-text="msg.content"></div>
                            <template v-else>
                                <!-- 进度步骤 -->
                                <div v-if="msg.steps && msg.steps.length" class="steps">
                                    <div v-for="(s, i) in msg.steps" :key="i" class="step">
                                        <span class="dot"></span><span>{{ s }}</span>
                                    </div>
                                </div>
                                <div v-if="msg.streaming && !msg.content" class="thinking">
                                    <el-icon class="is-loading"><Loading /></el-icon> 正在分析…
                                </div>

                                <!-- 答案：文字 + (可选)图表 -->
                                <div v-if="msg.content" class="bubble-ai" :id="'ai-msg-' + idx">
                                    <md-view :content="parsed(msg).text" />
                                    <vue-chart
                                        v-for="(c, ci) in parsed(msg).charts"
                                        :key="ci"
                                        :option="c.option"
                                        height="280px"
                                        class="ai-chart"
                                    />
                                </div>

                                <!-- 可跳转引用 -->
                                <div v-if="msg.references && msg.references.length" class="refs">
                                    <span class="refs-label">相关：</span>
                                    <el-button
                                        v-for="r in msg.references"
                                        :key="r.type + r.id"
                                        size="small"
                                        type="primary"
                                        plain
                                        @click="jumpRef(r)"
                                    >
                                        {{ r.label }}
                                    </el-button>
                                </div>

                                <!-- 操作：复制 / 导出 Word / 导出 Excel(有图表时) / 采纳 -->
                                <div v-if="!msg.streaming && msg.content" class="msg-actions">
                                    <el-button size="small" text @click="copy(msg.content)">复制</el-button>
                                    <el-button size="small" text @click="exportWord(idx)">导出 Word</el-button>
                                    <el-button v-if="parsed(msg).charts.length" size="small" text @click="exportExcel(msg)">导出 Excel</el-button>
                                    <el-button v-if="adoptable && idx === thread.length - 1" size="small" type="primary" plain @click="adopt(msg.content)">采纳</el-button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="ai-input">
                    <div v-if="quickPrompts.length" class="quick-row">
                        <el-tag
                            v-for="(q, i) in quickPrompts"
                            :key="i"
                            size="small"
                            effect="plain"
                            class="quick-chip"
                            @click="useSuggestion(q)"
                        >{{ q }}</el-tag>
                    </div>
                    <el-input
                        v-model="input"
                        type="textarea"
                        :rows="3"
                        resize="none"
                        :placeholder="placeholder"
                        @keydown="onKeydown"
                    />
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-xs text-gray-300">Enter 发送 · Shift+Enter 换行</span>
                        <el-button type="primary" :loading="loading" @click="send">发送</el-button>
                    </div>
                </div>
            </div>

            <el-drawer v-model="historyVisible" title="对话历史" size="320px" append-to-body>
                <div v-loading="historyLoading">
                    <el-empty v-if="!historyList.length" description="暂无历史对话" :image-size="60" />
                    <div v-for="h in historyList" :key="h.id" class="hist-item" @click="openConversation(h.id)">
                        <div class="flex items-center justify-between">
                            <span class="hist-title">{{ h.title || '新对话' }}</span>
                            <el-button size="small" link type="danger" @click.stop="removeConversation(h.id)">删除</el-button>
                        </div>
                        <div class="hist-time">{{ fmtTime(h.update_time) }}</div>
                    </div>
                </div>
            </el-drawer>
        </el-drawer>

        <!-- 设备全链路：点引用直接在本页弹窗看，不跳转 -->
        <trace-detail v-model="traceDrawer.visible" :device-id="traceDrawer.deviceId" />
    </span>
</template>

<script setup lang="ts">
import { computed, reactive, ref, nextTick } from 'vue'
import { ElMessage } from 'element-plus'
import { MagicStick, Loading } from '@element-plus/icons-vue'
import useUserStore from '@/stores/modules/user'
import {
    getAiConversations,
    getAiConversation,
    saveAiConversation,
    deleteAiConversation,
} from '@/addon/hsx_erp/api/ai'
import { streamAi } from '@/addon/hsx_erp/api/ai-stream'
import MdView from './md-view.vue'
import TraceDetail from '@/addon/hsx_erp/views/device_trace/trace-detail.vue'
import VueChart from '@/addon/hsx_erp/components/vue-chart/index.vue'
import * as XLSX from 'xlsx'

interface AiRef { type: string; id: number; label: string }
interface Msg { role: 'user' | 'assistant'; content: string; references?: AiRef[]; steps?: string[]; streaming?: boolean }

const props = withDefaults(defineProps<{
    scene?: string
    title?: string
    buttonText?: string
    buttonType?: string
    buttonSize?: string
    link?: boolean
    plain?: boolean
    icon?: boolean
    permission?: string
    question?: string
    placeholder?: string
    getPayload?: () => any | Promise<any>
    model?: string
    auto?: boolean
    adoptable?: boolean
    drawerSize?: string | number
    suggestions?: string[]
}>(), {
    scene: 'general',
    buttonText: 'AI',
    buttonType: 'primary',
    buttonSize: 'default',
    link: false,
    plain: true,
    icon: true,
    question: '',
    placeholder: '输入你想问的问题',
    model: '',
    auto: false,
    adoptable: false,
    drawerSize: '46%',
    suggestions: () => [],
})

const emit = defineEmits<{ (e: 'adopt', text: string): void }>()

const userStore = useUserStore()
const allowed = computed(() => !props.permission || (userStore.rules || []).includes(props.permission))

const visible = ref(false)
const loading = ref(false)
const thread = ref<Msg[]>([])
const input = ref('')
const contextData = ref<any>(null)
const tokens = ref(0)
const conversationId = ref(0)
const scrollRef = ref<HTMLElement | null>(null)
let abort: AbortController | null = null

const showIcon = computed(() => props.icon)
const drawerTitle = computed(() => props.title || props.buttonText || 'AI 助手')

const scrollToBottom = () => {
    nextTick(() => { if (scrollRef.value) scrollRef.value.scrollTop = scrollRef.value.scrollHeight })
}

const open = () => {
    visible.value = true
    if (!thread.value.length) {
        input.value = props.question || ''
        if (props.auto && input.value.trim()) nextTick(send)
    }
}

const onKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send() }
}

const resolveContext = async () => {
    if (!props.getPayload) return
    try {
        const payload: any = await props.getPayload()
        if (payload && typeof payload === 'object' && ('context' in payload || 'question' in payload)) {
            contextData.value = payload.context ?? null
            if (payload.question && !input.value) input.value = payload.question
        } else {
            contextData.value = payload ?? null
        }
    } catch { /* 取数失败不阻断 */ }
}

const send = async () => {
    const text = input.value.trim()
    if (!text || loading.value) return

    const isFirst = thread.value.length === 0
    if (isFirst) await resolveContext()

    const history = thread.value.map(m => ({ role: m.role, content: m.content }))
    thread.value.push({ role: 'user', content: text })
    const aiMsg = reactive<Msg>({ role: 'assistant', content: '', steps: [], references: [], streaming: true })
    thread.value.push(aiMsg)
    input.value = ''
    scrollToBottom()
    loading.value = true
    abort = new AbortController()

    await streamAi(
        {
            scene: props.scene,
            context: isFirst ? contextData.value : undefined,
            question: text,
            history,
            model: props.model,
        },
        {
            onStatus: (t) => { if (t) { aiMsg.steps!.push(t); scrollToBottom() } },
            onDelta: (t) => { aiMsg.content += t; scrollToBottom() },
            onDone: (d) => {
                aiMsg.references = d?.references || []
                aiMsg.streaming = false
                tokens.value += Number(d?.usage?.total_tokens || 0)
                persist()
            },
            onError: (m) => { aiMsg.streaming = false; ElMessage.error(m || 'AI 请求失败') },
        },
        abort.signal,
    )

    aiMsg.streaming = false
    loading.value = false
    scrollToBottom()
}

const persist = async () => {
    try {
        const res: any = await saveAiConversation({
            id: conversationId.value,
            scene: props.scene,
            messages: thread.value.map(m => ({ role: m.role, content: m.content, references: m.references || [] })),
            tokens: tokens.value,
        })
        if (res.data?.id) conversationId.value = res.data.id
    } catch { /* 存储失败不影响对话 */ }
}

const newConversation = () => {
    abort?.abort()
    thread.value = []
    conversationId.value = 0
    tokens.value = 0
    contextData.value = null
    input.value = props.question || ''
    loading.value = false
}

const onClosed = () => { abort?.abort(); loading.value = false }

// 历史
const historyVisible = ref(false)
const historyLoading = ref(false)
const historyList = ref<any[]>([])

const openHistory = async () => {
    historyVisible.value = true
    historyLoading.value = true
    try {
        const res: any = await getAiConversations(props.scene)
        historyList.value = res.data || []
    } finally {
        historyLoading.value = false
    }
}

const openConversation = async (id: number) => {
    const res: any = await getAiConversation(id)
    const data = res.data || {}
    thread.value = (data.messages || []).map((m: any) => ({ role: m.role, content: m.content, references: m.references || [], steps: [], streaming: false }))
    conversationId.value = data.id || id
    tokens.value = data.tokens || 0
    historyVisible.value = false
    scrollToBottom()
}

const removeConversation = async (id: number) => {
    await deleteAiConversation(id)
    historyList.value = historyList.value.filter(h => h.id !== id)
    if (conversationId.value === id) newConversation()
}

// 设备全链路抽屉（本页内打开，不跳转）
const traceDrawer = reactive<{ visible: boolean; deviceId: number }>({ visible: false, deviceId: 0 })
const jumpRef = (r: AiRef) => {
    if (r.type === 'device') {
        traceDrawer.deviceId = Number(r.id) || 0
        traceDrawer.visible = true
    }
}

const copy = async (text: string) => {
    try { await navigator.clipboard.writeText(text); ElMessage.success('已复制') }
    catch { ElMessage.warning('复制失败') }
}
const adopt = (text: string) => { emit('adopt', text); visible.value = false }

// —— 快捷提示词（按场景给默认，外部可用 suggestions 覆盖） ——
const SCENE_PROMPTS: Record<string, string[]> = {
    finance: ['本月经营情况如何？', '查“韩”这个人的往来账', '近30天采购与销售对比，做个柱状图', '应收账龄构成，做个饼图'],
    report: ['本月经营分析', '近6个月毛利趋势，折线图', '各仓库在库占比，饼图'],
}
const quickPrompts = computed<string[]>(() => (props.suggestions && props.suggestions.length) ? props.suggestions : (SCENE_PROMPTS[props.scene] || []))
const useSuggestion = (q: string) => { if (loading.value) return; input.value = q; send() }

// —— 图表：解析回答里的 ```chart {json}``` 块，转成 ECharts option ——
function buildChartOption(spec: any) {
    const type = String(spec?.type || 'bar')
    const title = spec?.title ? { text: String(spec.title), left: 'center', textStyle: { fontSize: 14 } } : undefined
    if (type === 'pie' || type === 'funnel') {
        const raw = spec.data || (spec.series?.[0]?.data) || []
        const data = raw.map((d: any, i: number) => (d && typeof d === 'object')
            ? { name: String(d.name ?? ('项' + (i + 1))), value: Number(d.value || 0) }
            : { name: String(spec.categories?.[i] ?? ('项' + (i + 1))), value: Number(d || 0) })
        return {
            title, tooltip: { trigger: 'item' }, legend: { bottom: 0, type: 'scroll' },
            series: [type === 'funnel'
                ? { type: 'funnel', left: '10%', width: '80%', data, label: { show: true } }
                : { type: 'pie', radius: ['35%', '65%'], data }],
        }
    }
    const series = (spec.series || []).map((s: any) => ({
        name: String(s.name || '值'), type, data: (s.data || []).map((n: any) => Number(n)),
        smooth: type === 'line', barMaxWidth: 40,
    }))
    return {
        title, tooltip: { trigger: 'axis' }, legend: { bottom: 0, type: 'scroll' },
        grid: { left: '3%', right: '4%', bottom: '14%', top: title ? 40 : 20, containLabel: true },
        xAxis: { type: 'category', data: (spec.categories || []).map(String) },
        yAxis: { type: 'value' }, series,
    }
}
function parseContent(content: string) {
    const charts: any[] = []
    const re = /```chart\s*([\s\S]*?)```/g
    const text = (content || '').replace(re, (m, p1) => {
        try { const spec = JSON.parse(String(p1).trim()); charts.push({ spec, option: buildChartOption(spec) }) }
        catch { return m } // 未闭合/非法 JSON：原样留着（流式期间未传完）
        return ''
    }).trim()
    return { text, charts }
}
// 解析结果缓存（按消息对象+内容），避免渲染时重复解析、且不污染响应式
const parseCache = new WeakMap<object, { key: string; val: { text: string; charts: any[] } }>()
const parsed = (msg: any) => {
    const c = msg?.content || ''
    const hit = parseCache.get(msg)
    if (hit && hit.key === c) return hit.val
    const val = parseContent(c)
    parseCache.set(msg, { key: c, val })
    return val
}

// —— 导出 ——
const ts = () => { const d = new Date(); const p = (x: number) => String(x).padStart(2, '0'); return `${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}_${p(d.getHours())}${p(d.getMinutes())}` }
const downloadBlob = (blob: Blob, filename: string) => {
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = filename; document.body.appendChild(a); a.click(); a.remove()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
}
const exportWord = (idx: number) => {
    const root = document.getElementById('ai-msg-' + idx)
    const md = root?.querySelector('.md-view') as HTMLElement | null
    const inner = md?.innerHTML || (thread.value[idx]?.content || '')
    const html = `<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'><head><meta charset='utf-8'><style>body{font-family:'微软雅黑','Microsoft YaHei',sans-serif;font-size:14px;line-height:1.7;color:#303133;} table{border-collapse:collapse;} th,td{border:1px solid #999;padding:4px 8px;font-size:13px;} h1,h2,h3{margin:10px 0 6px;}</style></head><body>${inner}</body></html>`
    downloadBlob(new Blob(['﻿' + html], { type: 'application/msword' }), `AI财务分析_${ts()}.doc`)
    ElMessage.success('已导出 Word')
}
const exportExcel = (msg: any) => {
    const charts = parsed(msg).charts
    if (!charts.length) { ElMessage.warning('本条没有可导出的图表数据'); return }
    const wb = XLSX.utils.book_new()
    charts.forEach((c: any, i: number) => {
        const spec = c.spec || {}
        let aoa: any[][] = []
        if (spec.type === 'pie' || spec.type === 'funnel') {
            aoa = [['项目', '数值']]
            const data = spec.data || (spec.series?.[0]?.data) || []
            data.forEach((d: any, j: number) => aoa.push([
                (d && typeof d === 'object') ? d.name : (spec.categories?.[j] ?? ('项' + (j + 1))),
                (d && typeof d === 'object') ? d.value : d,
            ]))
        } else {
            const series = spec.series || []
            aoa = [['类别', ...series.map((s: any) => s.name || '值')]]
            ;(spec.categories || []).forEach((cat: string, j: number) => aoa.push([cat, ...series.map((s: any) => s.data?.[j])]))
        }
        const ws = XLSX.utils.aoa_to_sheet(aoa)
        const name = (String(spec.title || ('图表' + (i + 1))).replace(/[\\/?*\[\]:]/g, '') || ('Sheet' + (i + 1))).slice(0, 28)
        XLSX.utils.book_append_sheet(wb, ws, name)
    })
    XLSX.writeFile(wb, `AI图表数据_${ts()}.xlsx`)
    ElMessage.success('已导出 Excel')
}

const fmtTime = (t: any) => {
    const n = Number(t || 0)
    if (!n) return ''
    const d = new Date(n * 1000)
    const p = (x: number) => String(x).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
}
</script>

<style lang="scss" scoped>
.ai-assistant-inline { display: inline-flex; }

.ai-chat { display: flex; flex-direction: column; height: 100%; }
.ai-thread { flex: 1; overflow-y: auto; padding: 4px 2px 12px; }

.msg { display: flex; gap: 10px; margin-bottom: 16px; align-items: flex-start; &.user { flex-direction: row-reverse; } }
.msg-avatar {
    flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; color: #fff; background: #909399;
}
.msg.user .msg-avatar { background: #409eff; }
.msg-body { max-width: 82%; }

.bubble-user {
    padding: 9px 13px; border-radius: 8px; background: #e8f3ff;
    font-size: 14px; line-height: 1.6; white-space: pre-wrap; word-break: break-word;
}
.bubble-ai {
    padding: 10px 14px; border-radius: 8px; background: #f7f8fa;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.steps { margin-bottom: 8px; }
.step {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: #909399; line-height: 1.9;
}
.step .dot {
    width: 6px; height: 6px; border-radius: 50%; background: #67c23a; flex-shrink: 0;
}
.thinking { font-size: 13px; color: #b0b3bb; display: flex; align-items: center; gap: 6px; }

.refs { margin-top: 6px; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.refs-label { font-size: 12px; color: #909399; }

.ai-input { border-top: 1px solid #ebeef5; padding-top: 10px; }
.quick-row { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.quick-chip { cursor: pointer; }
.ai-chart { margin-top: 10px; }
.msg-actions { margin-top: 6px; display: flex; flex-wrap: wrap; align-items: center; gap: 2px; }

.hist-item { padding: 10px 12px; border-radius: 6px; cursor: pointer; transition: background 0.15s; &:hover { background: #f5f7fa; } }
.hist-title { font-size: 14px; color: #303133; }
.hist-time { font-size: 12px; color: #b0b3bb; margin-top: 2px; }
</style>
