<template>
    <view class="assistant-page" :style="themeColor()">
        <view class="assistant-header safe-area-inset-top">
            <view class="header-icon" @click="goBack"><u-icon name="arrow-left" size="22" color="#1f2937" /></view>
            <view class="header-title"><text>{{ currentTitle }}</text><text class="header-subtitle">仅使用本站实时业务数据</text></view>
            <view class="header-actions">
                <view v-if="capability.conversation?.history" class="header-icon" @click="openHistory"><u-icon name="clock" size="20" color="#475467" /></view>
                <view class="header-icon" @click="newConversation"><u-icon name="plus" size="21" color="#475467" /></view>
            </view>
        </view>

        <scroll-view class="conversation" :style="conversationStyle" scroll-y :scroll-into-view="scrollTarget" :show-scrollbar="false">
            <view v-if="!messages.length" class="welcome-panel">
                <view class="welcome-mark">AI</view>
                <view><view class="welcome-title">想找什么机器？</view><view class="welcome-text">{{ capability.welcome }}</view></view>
            </view>

            <view v-if="conversationId && historyHasMore" class="load-earlier" :class="{ disabled: loadingEarlier }" @click="loadEarlierMessages">
                <u-loading-icon v-if="loadingEarlier" mode="circle" size="15" color="#667085" />
                <u-icon v-else name="arrow-up" size="15" color="#667085" />
                <text>{{ loadingEarlier ? '正在读取' : '加载更早消息' }}</text>
            </view>

            <AiMessageRenderer
                v-for="(message, index) in messages"
                :id="`message-${index}`"
                :key="message.id"
                :message="message"
                :voice-enabled="Boolean(capability.voice.tts)"
                :speaking="speakingMessageId === message.id"
                @speak="speakMessage"
                @open-product="openProduct"
                @action="runAction"
            />

            <view v-if="!messages.length" class="suggestions">
                <view v-for="item in capability.suggestions" :key="item" class="suggestion" @click="submitSuggestion(item)">{{ item }}</view>
            </view>
            <view id="conversation-bottom" class="conversation-bottom" />
        </scroll-view>

        <view class="composer safe-area-inset-bottom">
            <scroll-view v-if="capability.quick_actions?.length" class="quick-actions" scroll-x :show-scrollbar="false">
                <view class="quick-action-track">
                    <view v-for="action in capability.quick_actions" :key="`${action.integration_key}:${action.key}`" class="quick-action" :class="{ disabled: sending || recording || recognizing }" @click="applyQuickAction(action)">
                        <u-icon :name="action.icon || 'chat'" size="14" color="#475467" />
                        <text>{{ action.label }}</text>
                    </view>
                </view>
            </scroll-view>
            <view v-if="capability.voice.tts" class="composer-setting" @click="toggleAutoRead">
                <u-icon :name="autoRead ? 'volume-fill' : 'volume-off'" size="15" :color="autoRead ? '#2563eb' : '#667085'" />
                <text>{{ autoRead ? '自动朗读已开启' : '自动朗读已关闭' }}</text>
            </view>
            <view v-if="recording" class="recording-panel">
                <view class="voice-wave"><view v-for="item in 5" :key="item" class="voice-wave-bar" :style="{ animationDelay: `${item * 80}ms` }" /></view>
                <view class="recording-copy"><text>正在听 {{ recordingTime }}</text><text>再点麦克风，结束并发送</text></view>
            </view>
            <view v-else-if="recognizing" class="recording-panel recognizing">
                <u-loading-icon mode="circle" size="20" color="#2563eb" />
                <view class="recording-copy"><text>正在转换文字</text><text>识别完成后自动发送</text></view>
            </view>
            <view class="composer-row">
                <view v-if="capability.voice.stt && voiceInputSupported" class="icon-button" :class="{ active: recording, disabled: sending || recognizing }" @click="toggleRecording">
                    <u-icon :name="recording ? 'pause-circle' : 'mic'" size="23" :color="recording ? '#ffffff' : '#334155'" />
                </view>
                <textarea v-model="draft" class="composer-input" :focus="inputFocus" :disabled="sending || recording || recognizing" auto-height maxlength="500" confirm-type="send" :placeholder="recording ? '正在听你说话…' : '说说预算、品牌、内存或成色'" @blur="inputFocus = false" @confirm="send" />
                <view class="send-button" :class="{ stop: sending, disabled: !draft.trim() && !sending }" @click="sending ? cancelSend() : send()"><u-icon :name="sending ? 'pause' : 'arrow-upward'" size="21" color="#ffffff" /></view>
            </view>
        </view>

        <u-popup :show="historyVisible" mode="bottom" :round="8" :safe-area-inset-bottom="true" @close="historyVisible = false">
            <view class="history-panel">
                <view class="history-head"><view><text class="history-title">历史咨询</text><text class="history-subtitle">仅展示当前账号的会话</text></view><view class="history-close" @click="historyVisible = false"><u-icon name="close" size="20" color="#475467" /></view></view>
                <scroll-view scroll-y class="history-list">
                    <view v-if="historyLoading" class="history-loading"><u-loading-icon mode="circle" size="20" color="#2563eb" /><text>正在读取会话</text></view>
                    <view v-else-if="!conversationList.length" class="history-empty"><u-icon name="chat" size="30" color="#98a2b3" /><text>还没有历史咨询</text></view>
                    <view v-for="item in conversationList" :key="item.id" class="history-row" :class="{ active: Number(item.id) === conversationId }" @click="selectConversation(item)">
                        <view class="history-copy"><text class="history-name">{{ item.title || '新的咨询' }}</text><text class="history-meta">{{ formatConversationTime(item.last_message_at) }} · {{ item.message_count }} 条消息</text></view>
                        <u-icon name="arrow-right" size="16" color="#98a2b3" />
                    </view>
                </scroll-view>
                <view class="history-footer" @click="newConversation"><u-icon name="plus" size="18" color="#ffffff" /><text>开始新咨询</text></view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, reactive, ref } from 'vue'
import { onLoad, onUnload } from '@dcloudio/uni-app'
import { redirect } from '@/utils/common'
import AiMessageRenderer from '@/addon/hsx_ai/components/AiMessageRenderer.vue'
import { getAiAssistantCapability, getAiConversations, getAiConversationMessages, speechBlobToText, speechToText, streamAiAssistant, textToSpeech } from '@/addon/hsx_ai/api/assistant'

type ChatMessage = { id: string | number; role: 'user' | 'assistant'; content: string; loading?: boolean; loadingPhase?: string; streaming?: boolean; resources?: any[]; actions?: any[]; blocks?: any[]; create_at?: number }
const capability = reactive<any>({ welcome: '告诉我你的预算和要求，我会按本站在售商品帮你挑选。', suggestions: [], quick_actions: [], conversation: { persistent: false, history: false }, voice: { stt: false, tts: false, auto_read_default: false, max_seconds: 60 } })
const messages = ref<ChatMessage[]>([])
const conversationId = ref(0)
const currentTitle = ref('AI 选机助手')
const historyVisible = ref(false)
const historyLoading = ref(false)
const conversationList = ref<any[]>([])
const historyMessagePage = ref(1)
const historyHasMore = ref(false)
const loadingEarlier = ref(false)
const draft = ref('')
const inputFocus = ref(false)
const sending = ref(false)
const recording = ref(false)
const recognizing = ref(false)
const recordingSeconds = ref(0)
const speaking = ref(false)
const speakingMessageId = ref('')
const autoRead = ref(false)
const scrollTarget = ref('conversation-bottom')
const voiceInputSupported = ref(false)
let recorder: any = null
let audio: any = null
let recordingTimer: any = null
let streamController: AbortController | null = null
let disposed = false

const recordingTime = computed(() => {
    const seconds = Math.max(0, recordingSeconds.value)
    return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`
})
const conversationStyle = computed(() => {
    const composerHeight = 126 + (capability.voice?.tts ? 48 : 0) + (capability.quick_actions?.length ? 72 : 0)
    return { height: `calc(100vh - 112rpx - ${composerHeight}rpx - env(safe-area-inset-bottom))` }
})
const uid = () => `${Date.now()}_${Math.random().toString(36).slice(2)}`
const scrollBottom = () => nextTick(() => { scrollTarget.value = ''; nextTick(() => { scrollTarget.value = 'conversation-bottom' }) })
const goBack = () => uni.navigateBack({ fail: () => redirect({ url: '/addon/phone_shop/pages/index', mode: 'reLaunch' }) })
const submitSuggestion = (text: string) => { draft.value = text; void send() }
const applyQuickAction = (action: any) => {
    if (sending.value || recording.value || recognizing.value) return
    const prompt = String(action?.prompt || '').trim()
    if (!prompt) return
    draft.value = prompt
    if (action?.submit_mode === 'send') {
        void nextTick(() => send())
        return
    }
    inputFocus.value = false
    void nextTick(() => { inputFocus.value = true })
}
const openProduct = (item: any) => redirect({ url: '/addon/phone_shop/pages/goods/detail', param: { goods_id: item.goods_id } })
const runAction = (action: any) => {
    const route = String(action?.route || '')
    if (action?.approved === true && action?.type === 'route' && route.startsWith('/addon/')) {
        redirect({ url: route, param: action.params || {} })
    }
}
const formatConversationTime = (timestamp: any) => {
    const date = new Date(Number(timestamp || 0) * 1000)
    if (!Number(timestamp) || Number.isNaN(date.getTime())) return '刚刚'
    const today = new Date()
    const sameDay = date.toDateString() === today.toDateString()
    return sameDay
        ? `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
        : `${date.getMonth() + 1}月${date.getDate()}日`
}
const loadHistory = async () => {
    if (!capability.conversation?.history) return
    historyLoading.value = true
    try {
        const response: any = await getAiConversations({ page: 1, limit: 30, status: 'active' })
        conversationList.value = response?.data?.data || []
    } catch (_) { conversationList.value = [] }
    finally { historyLoading.value = false }
}
const openHistory = () => { historyVisible.value = true; void loadHistory() }
const newConversation = () => {
    stopSpeaking()
    conversationId.value = 0
    historyMessagePage.value = 1
    historyHasMore.value = false
    currentTitle.value = 'AI 选机助手'
    messages.value = []
    historyVisible.value = false
    draft.value = ''
    scrollBottom()
}
const selectConversation = async (item: any) => {
    if (!item?.id || historyLoading.value) return
    historyLoading.value = true
    try {
        const response: any = await getAiConversationMessages(Number(item.id), { page: 1, limit: 60 })
        messages.value = (response?.data?.messages || []).map((message: any) => ({ ...message, id: message.id || uid(), loading: false }))
        conversationId.value = Number(item.id)
        historyMessagePage.value = 1
        historyHasMore.value = Boolean(response?.data?.pagination?.has_more)
        currentTitle.value = String(response?.data?.conversation?.title || item.title || 'AI 选机助手')
        historyVisible.value = false
        stopSpeaking()
        scrollBottom()
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '会话读取失败', icon: 'none' })
    } finally { historyLoading.value = false }
}
const loadEarlierMessages = async () => {
    if (!conversationId.value || !historyHasMore.value || loadingEarlier.value) return
    loadingEarlier.value = true
    try {
        const nextPage = historyMessagePage.value + 1
        const response: any = await getAiConversationMessages(conversationId.value, { page: nextPage, limit: 60 })
        const earlier = (response?.data?.messages || []).map((message: any) => ({ ...message, id: message.id || uid(), loading: false }))
        messages.value = [...earlier, ...messages.value]
        historyMessagePage.value = nextPage
        historyHasMore.value = Boolean(response?.data?.pagination?.has_more)
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '更早消息读取失败', icon: 'none' })
    } finally { loadingEarlier.value = false }
}
const stopRecordingTimer = () => {
    if (recordingTimer) clearInterval(recordingTimer)
    recordingTimer = null
}
const startRecordingTimer = () => {
    stopRecordingTimer()
    recordingSeconds.value = 0
    recordingTimer = setInterval(() => {
        recordingSeconds.value += 1
        if (recordingSeconds.value >= Number(capability.voice.max_seconds || 60)) void stopRecording()
    }, 1000)
}

const send = async () => {
    const content = draft.value.trim()
    if (!content || sending.value) return
    draft.value = ''
    messages.value.push({ id: uid(), role: 'user', content })
    // 后续 SSE 回调会持续修改该对象；必须直接持有 Vue 代理，否则原始对象的
    // token 写入不会触发界面重绘，只会在请求结束后的其他状态变更时一次性显示。
    const assistant = reactive<ChatMessage>({ id: uid(), role: 'assistant', content: '', loading: true, loadingPhase: '正在理解你的需求', streaming: false, resources: [], actions: [] })
    messages.value.push(assistant)
    sending.value = true
    const AbortControllerClass = (globalThis as any).AbortController
    streamController = AbortControllerClass ? new AbortControllerClass() : null
    if (autoRead.value) unlockAudio()
    scrollBottom()
    let completed = false
    const waitingPhases = ['正在理解你的需求', '正在查询本站实时库存', '正在整理价格与质检信息']
    let waitingPhaseIndex = 0
    let waitingTimer: any = setInterval(() => {
        if (!assistant.loading || waitingPhaseIndex >= waitingPhases.length - 1) return
        waitingPhaseIndex += 1
        assistant.loadingPhase = waitingPhases[waitingPhaseIndex]
    }, 1600)
    let contentBuffer = ''
    let contentTimer: any = null
    let scrollTimer: any = null
    const flushContent = () => {
        if (!contentBuffer) return
        assistant.content += contentBuffer
        contentBuffer = ''
    }
    const scheduleContent = () => {
        if (contentTimer) return
        contentTimer = setTimeout(() => { contentTimer = null; flushContent() }, 36)
    }
    const scheduleScroll = () => {
        if (scrollTimer) return
        scrollTimer = setTimeout(() => { scrollTimer = null; scrollBottom() }, 96)
    }
    const clearStreamTimers = () => {
        if (waitingTimer) clearInterval(waitingTimer)
        if (contentTimer) clearTimeout(contentTimer)
        if (scrollTimer) clearTimeout(scrollTimer)
        waitingTimer = null; contentTimer = null; scrollTimer = null
    }
    try {
        const history = conversationId.value > 0 ? [] : messages.value.slice(0, -1).slice(-10).map(({ role, content }) => ({ role, content }))
        await streamAiAssistant({ request_id: `mall_${uid()}`, conversation_id: conversationId.value, prompt: content, messages: history }, (event) => {
            if (event.type === 'content') {
                if (waitingTimer) { clearInterval(waitingTimer); waitingTimer = null }
                assistant.loading = false
                assistant.streaming = true
                contentBuffer += event.delta || ''
                scheduleContent()
                scheduleScroll()
            }
            if (event.type === 'resources') {
                assistant.resources = event.items || []
                assistant.loadingPhase = assistant.resources.length
                    ? `已找到 ${assistant.resources.length} 台，正在比较价格与质检`
                    : '正在整理购买建议'
                scheduleScroll()
            }
            if (event.type === 'blocks') {
                if (waitingTimer) { clearInterval(waitingTimer); waitingTimer = null }
                assistant.blocks = event.items || []
                assistant.loadingPhase = '正在整理报价数据'
                scheduleScroll()
            }
            if (event.type === 'conversation' && Number(event.conversation_id) > 0) {
                conversationId.value = Number(event.conversation_id)
                assistant.loadingPhase = '正在查询本站实时数据'
            }
            if (event.type === 'meta' && assistant.loading) assistant.loadingPhase = assistant.resources?.length ? '正在整理选机建议' : '正在分析你的需求'
            if (event.type === 'reasoning' && assistant.loading) assistant.loadingPhase = assistant.resources?.length ? '正在比较候选设备' : '正在梳理关键条件'
            if (event.type === 'done') {
                flushContent()
                assistant.loading = false
                assistant.streaming = false
                if (Number(event.conversation_id) > 0) conversationId.value = Number(event.conversation_id)
                if (Number(event.message_id) > 0) assistant.id = Number(event.message_id)
            }
            if (event.type === 'error') throw new Error(event.message || 'AI 响应失败')
        }, streamController?.signal)
        flushContent()
        assistant.loading = false
        assistant.streaming = false
        if (!assistant.content) assistant.content = '暂时没有找到合适的商品，可以换个预算或品牌再问我。'
        completed = true
    } catch (error: any) {
        flushContent()
        assistant.loading = false
        assistant.streaming = false
        assistant.content = error?.name === 'AbortError' || /abort/i.test(String(error?.message || ''))
            ? (assistant.content || '已停止生成')
            : (assistant.content ? `${assistant.content}\n\n回答中断，可以重新发送刚才的问题。` : (error?.msg || error?.message || '服务暂时没有响应，请稍后再试。'))
    } finally { clearStreamTimers(); sending.value = false; streamController = null; scrollBottom() }
    if (completed && autoRead.value && capability.voice.tts) void speak(assistant.content, String(assistant.id), true)
    if (completed && capability.conversation?.history) void loadHistory()
}
const cancelSend = () => streamController?.abort()

const recognizedAndSend = async (text: string) => {
    draft.value = String(text || '').trim()
    if (!draft.value) throw new Error('没有识别到有效内容')
    await send()
}

const initRecorder = () => {
    // #ifdef H5
    const browserWindow = window as any
    voiceInputSupported.value = Boolean(navigator.mediaDevices?.getUserMedia && (browserWindow.AudioContext || browserWindow.webkitAudioContext))
    // #endif
    // #ifndef H5
    voiceInputSupported.value = true
    recorder = uni.getRecorderManager()
    recorder.onStop(async (result: any) => {
        recording.value = false
        stopRecordingTimer()
        if (disposed) return
        recognizing.value = true
        try {
            const response: any = await speechToText(result.tempFilePath)
            await recognizedAndSend(String(response?.data?.text || ''))
        } catch (error: any) {
            uni.showToast({ title: error?.msg || error?.message || '语音识别失败', icon: 'none' })
        } finally { recognizing.value = false }
    })
    recorder.onError(() => {
        recording.value = false
        stopRecordingTimer()
        if (!disposed) uni.showToast({ title: '无法使用麦克风', icon: 'none' })
    })
    // #endif
}

let h5Context: any = null
let h5Source: any = null
let h5Processor: any = null
let h5Stream: any = null
let h5Chunks: Float32Array[] = []
let h5SampleRate = 16000

const mergeAudio = (chunks: Float32Array[]) => {
    const result = new Float32Array(chunks.reduce((total, chunk) => total + chunk.length, 0))
    let offset = 0
    chunks.forEach((chunk) => { result.set(chunk, offset); offset += chunk.length })
    return result
}
const resampleAudio = (input: Float32Array, inputRate: number, outputRate = 16000) => {
    if (inputRate === outputRate) return input
    const ratio = inputRate / outputRate
    const result = new Float32Array(Math.max(1, Math.round(input.length / ratio)))
    for (let index = 0; index < result.length; index++) {
        const start = Math.floor(index * ratio)
        const end = Math.min(input.length, Math.floor((index + 1) * ratio))
        let total = 0
        for (let cursor = start; cursor < end; cursor++) total += input[cursor]
        result[index] = total / Math.max(1, end - start)
    }
    return result
}
const encodeWav = (samples: Float32Array, sampleRate = 16000) => {
    const buffer = new ArrayBuffer(44 + samples.length * 2)
    const view = new DataView(buffer)
    const writeText = (offset: number, text: string) => {
        for (let index = 0; index < text.length; index++) view.setUint8(offset + index, text.charCodeAt(index))
    }
    writeText(0, 'RIFF'); view.setUint32(4, 36 + samples.length * 2, true); writeText(8, 'WAVE'); writeText(12, 'fmt ')
    view.setUint32(16, 16, true); view.setUint16(20, 1, true); view.setUint16(22, 1, true)
    view.setUint32(24, sampleRate, true); view.setUint32(28, sampleRate * 2, true); view.setUint16(32, 2, true); view.setUint16(34, 16, true)
    writeText(36, 'data'); view.setUint32(40, samples.length * 2, true)
    let offset = 44
    samples.forEach((sample) => {
        const value = Math.max(-1, Math.min(1, sample))
        view.setInt16(offset, value < 0 ? value * 0x8000 : value * 0x7fff, true)
        offset += 2
    })
    return new Blob([buffer], { type: 'audio/wav' })
}
const releaseH5Recorder = async () => {
    h5Processor?.disconnect(); h5Source?.disconnect(); h5Stream?.getTracks().forEach((track: any) => track.stop())
    if (h5Context && h5Context.state !== 'closed') await h5Context.close()
    h5Processor = null; h5Source = null; h5Stream = null; h5Context = null
}
const startH5Recording = async () => {
    try {
        h5Stream = await navigator.mediaDevices.getUserMedia({ audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true } })
        const browserWindow = window as any
        const AudioContextClass = browserWindow.AudioContext || browserWindow.webkitAudioContext
        h5Context = new AudioContextClass()
        if (h5Context.state === 'suspended') await h5Context.resume()
        h5SampleRate = h5Context.sampleRate
        h5Chunks = []
        h5Source = h5Context.createMediaStreamSource(h5Stream)
        h5Processor = h5Context.createScriptProcessor(4096, 1, 1)
        h5Processor.onaudioprocess = (event: any) => h5Chunks.push(new Float32Array(event.inputBuffer.getChannelData(0)))
        h5Source.connect(h5Processor); h5Processor.connect(h5Context.destination)
        recording.value = true
        startRecordingTimer()
    } catch (error: any) {
        await releaseH5Recorder()
        uni.showToast({ title: error?.name === 'NotAllowedError' ? '请允许网页使用麦克风' : '麦克风启动失败', icon: 'none' })
    }
}
const stopH5Recording = async () => {
    const chunks = h5Chunks.slice()
    const sampleRate = h5SampleRate
    recording.value = false
    stopRecordingTimer()
    await releaseH5Recorder()
    if (!chunks.length || recordingSeconds.value < 1) return uni.showToast({ title: '录音时间太短，请重新录制', icon: 'none' })
    recognizing.value = true
    try {
        const audioBlob = encodeWav(resampleAudio(mergeAudio(chunks), sampleRate))
        const response: any = await speechBlobToText(audioBlob)
        await recognizedAndSend(String(response?.data?.text || ''))
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '语音识别失败', icon: 'none' })
    } finally { recognizing.value = false }
}
const startRecording = async () => {
    if (sending.value || recognizing.value) return
    unlockAudio()
    // #ifdef H5
    await startH5Recording()
    return
    // #endif
    if (!recorder) return
    recording.value = true
    startRecordingTimer()
    recorder.start({ duration: 60000, sampleRate: 16000, numberOfChannels: 1, encodeBitRate: 48000, format: 'm4a' })
}
const stopRecording = async () => {
    if (!recording.value) return
    // #ifdef H5
    await stopH5Recording()
    return
    // #endif
    recorder?.stop()
}
const toggleRecording = () => recording.value ? stopRecording() : startRecording()

const audioSource = (base64: string) => {
    // #ifdef MP-WEIXIN
    const path = `${wx.env.USER_DATA_PATH}/hsx_ai_reply.mp3`
    wx.getFileSystemManager().writeFileSync(path, base64, 'base64')
    return path
    // #endif
    // #ifndef MP-WEIXIN
    return `data:audio/mpeg;base64,${base64}`
    // #endif
}
const stopSpeaking = () => {
    audio?.stop()
    speaking.value = false
    speakingMessageId.value = ''
}
const unlockAudio = () => {
    if (!audio) audio = uni.createInnerAudioContext()
}
const toggleAutoRead = () => {
    autoRead.value = !autoRead.value
    if (autoRead.value) unlockAudio()
    else stopSpeaking()
    uni.showToast({ title: autoRead.value ? '已开启自动朗读' : '已关闭自动朗读', icon: 'none' })
}
const speak = async (text: string, messageId = '', automatic = false) => {
    if (speaking.value && speakingMessageId.value === messageId) return stopSpeaking()
    stopSpeaking()
    if (!automatic) uni.showLoading({ title: '生成语音' })
    try {
        const readable = String(text || '').replace(/```[\s\S]*?```/g, ' ').replace(/[#*_>`~\[\]{}]/g, ' ').replace(/\s+/g, ' ').trim()
        const response: any = await textToSpeech(readable)
        audio?.destroy()
        audio = uni.createInnerAudioContext()
        audio.src = audioSource(String(response?.data?.audio_base64 || ''))
        audio.onEnded(() => { speaking.value = false; speakingMessageId.value = '' })
        audio.onError(() => { speaking.value = false; speakingMessageId.value = ''; uni.showToast({ title: '语音播放失败', icon: 'none' }) })
        speaking.value = true
        speakingMessageId.value = messageId
        audio.play()
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '语音合成失败', icon: 'none' })
    } finally { if (!automatic) uni.hideLoading() }
}
const speakMessage = (message: ChatMessage) => void speak(message.content, String(message.id))

onLoad(async () => {
    disposed = false
    try {
        const response: any = await getAiAssistantCapability()
        Object.assign(capability, response?.data || {})
        if (!capability.available) return goBack()
        autoRead.value = Boolean(capability.voice?.auto_read_default)
        initRecorder()
    } catch (_) { goBack() }
})
const dispose = () => {
    if (disposed) return
    disposed = true
    streamController?.abort()
    if (recording.value) recorder?.stop()
    recording.value = false
    stopRecordingTimer()
    void releaseH5Recorder()
    audio?.destroy()
    audio = null
}
onUnload(dispose)
onBeforeUnmount(dispose)
</script>

<style lang="scss" scoped>
.assistant-page { height: 100vh; overflow: hidden; background: #f4f6f8; color: #172033; }
.assistant-header { position: relative; box-sizing: border-box; height: 112rpx; display: flex; align-items: center; justify-content: space-between; padding: 0 20rpx; border-bottom: 1rpx solid #e6e9ee; background: #fff; }
.header-icon { width: 64rpx; height: 64rpx; display: flex; align-items: center; justify-content: center; border-radius: 8rpx; }.header-icon:active { background: #f0f2f5; }
.header-title { position: absolute; left: 160rpx; right: 160rpx; display: flex; flex-direction: column; align-items: center; overflow: hidden; font-size: 29rpx; font-weight: 600; }.header-title > text:first-child { width: 100%; overflow: hidden; text-align: center; text-overflow: ellipsis; white-space: nowrap; }.header-subtitle { margin-top: 3rpx; color: #8992a3; font-size: 19rpx; font-weight: 400; }.header-actions { display: flex; align-items: center; gap: 4rpx; margin-left: auto; }
.conversation { box-sizing: border-box; padding: 24rpx; }
.welcome-panel { display: flex; gap: 20rpx; padding: 24rpx; margin-bottom: 26rpx; border: 1rpx solid #e3e7ec; border-radius: 8rpx; background: #fff; }.welcome-mark { flex: 0 0 64rpx; height: 64rpx; display: flex; align-items: center; justify-content: center; border-radius: 8rpx; background: #1f2937; color: #fff; font-size: 21rpx; font-weight: 700; }.welcome-title { font-size: 28rpx; font-weight: 600; }.welcome-text { margin-top: 8rpx; color: #667085; font-size: 24rpx; line-height: 1.58; }
.load-earlier { display: flex; width: max-content; height: 48rpx; align-items: center; gap: 7rpx; margin: 0 auto 12rpx; padding: 0 14rpx; color: #667085; font-size: 21rpx; }.load-earlier.disabled { opacity: .55; }
.suggestions { display: flex; flex-wrap: wrap; gap: 12rpx; margin: 18rpx 0; }.suggestion { padding: 14rpx 19rpx; border: 1rpx solid #d9dee6; border-radius: 8rpx; background: #fff; color: #475467; font-size: 23rpx; }.suggestion:active { border-color: #b9c7e8; background: #f5f8ff; }.conversation-bottom { height: 34rpx; }
.composer { position: fixed; left: 0; right: 0; bottom: 0; box-sizing: border-box; padding: 12rpx 22rpx 18rpx; border-top: 1rpx solid #e4e7ec; background: #fff; }.composer-setting { display: flex; width: max-content; height: 40rpx; align-items: center; gap: 7rpx; margin: 0 0 8rpx 90rpx; color: #667085; font-size: 20rpx; }.composer-row { display: flex; align-items: flex-end; gap: 12rpx; }.composer-input { box-sizing: border-box; flex: 1; min-height: 76rpx; max-height: 180rpx; padding: 18rpx 20rpx; border: 1rpx solid transparent; border-radius: 8rpx; background: #f2f4f7; font-size: 26rpx; line-height: 40rpx; }.composer-input:focus { border-color: #b9c9ed; background: #fff; }.icon-button, .send-button { flex: 0 0 76rpx; height: 76rpx; display: flex; align-items: center; justify-content: center; border-radius: 8rpx; background: #eef1f4; }.icon-button.active, .send-button { background: var(--primary-color); }.send-button.stop { background: #344054; }.icon-button.disabled, .send-button.disabled { opacity: .38; }
.quick-actions { width: 100%; margin-bottom: 10rpx; white-space: nowrap; }.quick-action-track { display: flex; width: max-content; align-items: center; gap: 10rpx; padding-right: 18rpx; }.quick-action { display: flex; height: 52rpx; box-sizing: border-box; align-items: center; gap: 7rpx; padding: 0 16rpx; border: 1rpx solid #d9dee6; border-radius: 8rpx; background: #fff; color: #344054; font-size: 22rpx; }.quick-action:active { border-color: #b9c9ed; background: #f5f8ff; }.quick-action.disabled { opacity: .42; }
.recording-panel { display: flex; min-height: 92rpx; box-sizing: border-box; align-items: center; justify-content: center; gap: 20rpx; margin-bottom: 12rpx; padding: 14rpx 20rpx; border: 1rpx solid #dbe3ef; border-radius: 8rpx; background: #f7f9fc; }.recording-copy { display: flex; flex-direction: column; color: #172033; font-size: 24rpx; line-height: 1.45; }.recording-copy text:last-child { color: #7c8799; font-size: 20rpx; }.voice-wave { display: flex; width: 72rpx; height: 44rpx; align-items: center; justify-content: center; gap: 6rpx; }.voice-wave-bar { width: 5rpx; height: 14rpx; border-radius: 3rpx; background: #ef4444; animation: voice-wave .72s ease-in-out infinite alternate; }.recognizing { border-color: #cddcfb; background: #f5f8ff; }
.history-panel { box-sizing: border-box; height: 72vh; padding: 0 24rpx 24rpx; background: #fff; }.history-head { display: flex; height: 112rpx; align-items: center; justify-content: space-between; border-bottom: 1rpx solid #e7eaee; }.history-head > view:first-child { display: flex; flex-direction: column; }.history-title { font-size: 30rpx; font-weight: 650; }.history-subtitle { margin-top: 5rpx; color: #98a2b3; font-size: 20rpx; }.history-close { width: 64rpx; height: 64rpx; display: flex; align-items: center; justify-content: center; }.history-list { height: calc(72vh - 208rpx); }.history-row { display: flex; min-height: 104rpx; box-sizing: border-box; align-items: center; justify-content: space-between; gap: 20rpx; padding: 18rpx 8rpx; border-bottom: 1rpx solid #edf0f3; }.history-row.active { background: #f5f8ff; }.history-copy { display: flex; min-width: 0; flex: 1; flex-direction: column; }.history-name { overflow: hidden; font-size: 26rpx; font-weight: 550; text-overflow: ellipsis; white-space: nowrap; }.history-meta { margin-top: 7rpx; color: #98a2b3; font-size: 20rpx; }.history-loading, .history-empty { display: flex; height: 300rpx; align-items: center; justify-content: center; gap: 14rpx; color: #98a2b3; font-size: 24rpx; }.history-empty { flex-direction: column; }.history-footer { display: flex; height: 76rpx; align-items: center; justify-content: center; gap: 9rpx; border-radius: 8rpx; background: var(--primary-color); color: #fff; font-size: 25rpx; }
@keyframes voice-wave { from { height: 12rpx; opacity: .55; } to { height: 40rpx; opacity: 1; } }
</style>
