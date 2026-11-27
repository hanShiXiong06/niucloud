<template>
    <u-popup :show="show" mode="bottom" :round="18" :safe-area-inset-bottom="true" @close="close">
        <view class="project-ai-panel">
            <view class="project-ai-header">
                <view class="assistant-brand"><view class="assistant-mark">AI</view><view class="assistant-heading"><text>{{ capability.title || 'AI 项目顾问' }}</text><text>仅依据当前项目知识回答</text></view></view>
                <view class="close-button" @click="close"><u-icon name="close" size="21" color="#475467" /></view>
            </view>

            <scroll-view class="project-ai-chat" scroll-y :scroll-into-view="scrollTarget" :show-scrollbar="false">
                <view v-if="!messages.length" class="welcome-card">
                    <view class="welcome-title">有什么想先了解的？</view>
                    <view class="welcome-text">{{ capability.welcome }}</view>
                    <view class="knowledge-note"><u-icon name="lock-fill" size="13" color="#315cf5" /><text>当前项目独立知识库 · 资料不足会转人工确认</text></view>
                </view>

                <view v-for="message in messages" :id="`project-ai-${message.id}`" :key="message.id" class="message-row" :class="`role-${message.role}`">
                    <view v-if="message.role === 'assistant'" class="message-avatar">AI</view>
                    <view class="message-main">
                        <view class="message-bubble">
                            <view v-if="message.loading && !message.content" class="answer-loading"><u-loading-icon mode="circle" size="16" color="#315cf5" /><text>{{ message.loadingText || '正在查询项目知识' }}</text></view>
                            <text v-else selectable class="message-text">{{ message.content }}</text>
                        </view>
                        <view v-if="message.role === 'assistant' && message.content" class="message-tools">
                            <view v-if="capability.voice?.tts" class="message-tool" @click="speak(message)"><u-icon :name="speakingId === String(message.id) ? 'pause-circle' : 'volume'" size="15" color="#667085" /><text>{{ speakingId === String(message.id) ? '停止' : '朗读' }}</text></view>
                            <view v-if="message.humanRequired" class="message-tool human" @click="contactHuman"><u-icon name="account" size="15" color="#315cf5" /><text>转人工</text></view>
                        </view>
                    </view>
                </view>

                <view v-if="messages.length && !sending" class="after-actions">
                    <view class="after-action" @click="contactHuman"><u-icon name="account-fill" size="16" color="#315cf5" /><text>没有解决？联系群内工作人员</text></view>
                </view>
                <view id="project-ai-bottom" class="chat-bottom" />
            </scroll-view>

            <view class="project-ai-composer">
                <view v-if="capability.suggestions?.length" class="persistent-questions">
                    <view class="question-heading"><text>猜你想问</text><text>可随时选择</text></view>
                    <scroll-view class="question-scroll" scroll-x :show-scrollbar="false">
                        <view class="question-list">
                            <view v-for="question in capability.suggestions" :key="question" class="question-chip" :class="{ disabled: sending }" @click="ask(question)">{{ question }}</view>
                        </view>
                    </scroll-view>
                </view>
                <view v-if="capability.voice?.tts" class="auto-read" :class="{ active: autoRead }" @click="toggleAutoRead"><u-icon :name="autoRead ? 'volume-fill' : 'volume-off'" size="14" :color="autoRead ? '#315cf5' : '#98a2b3'" /><text>{{ autoRead ? '自动朗读已开启' : '开启自动朗读' }}</text></view>
                <view class="composer-row">
                    <textarea v-model="draft" class="composer-input" auto-height maxlength="500" confirm-type="send" :disabled="sending" placeholder="请输入你想了解的项目问题" @confirm="send" />
                    <view class="send-button" :class="{ disabled: !draft.trim() && !sending, stop: sending }" @click="sending ? cancel() : send()"><u-icon :name="sending ? 'pause' : 'arrow-upward'" size="20" color="#fff" /></view>
                </view>
                <view class="ai-disclaimer">AI 仅提供项目说明，付款到账、审核和退款结果以工作人员确认为准</view>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { getProjectAiCapability, projectAiTextToSpeech, streamProjectAiAssistant } from '@/addon/hsx_ai/api/assistant'

type Message = { id:string; role:'user'|'assistant'; content:string; loading?:boolean; loadingText?:string; humanRequired?:boolean }
const props = defineProps({ show:{ type:Boolean, default:false }, projectId:{ type:Number, required:true }, groupNo:{ type:String, default:'' } })
const emit = defineEmits(['update:show'])
const capability = reactive<any>({ title:'AI 项目顾问', welcome:'你可以咨询当前项目的费用、资料、流程、审核和退款规则。', suggestions:[], voice:{ tts:false, auto_read_default:false } })
const messages = ref<Message[]>([]), draft = ref(''), sending = ref(false), conversationId = ref(0), scrollTarget = ref('project-ai-bottom'), speakingId = ref(''), autoRead = ref(false)
let controller:AbortController|null = null, audio:any = null, loadedProjectId = 0
const uid = () => `${Date.now()}_${Math.random().toString(36).slice(2)}`
const scrollBottom = () => nextTick(() => { scrollTarget.value = ''; nextTick(() => { scrollTarget.value = 'project-ai-bottom' }) })
const close = () => { cancel(); stopSpeaking(); emit('update:show', false) }

async function loadCapability() {
    if (!props.projectId || loadedProjectId === props.projectId) return
    try {
        const response:any = await getProjectAiCapability(props.projectId, props.groupNo)
        const data = response?.data || {}
        if (!data.available) throw new Error('当前项目暂未启用 AI 顾问')
        Object.assign(capability, data)
        autoRead.value = Boolean(data.voice?.auto_read_default)
        loadedProjectId = props.projectId
    } catch (error:any) {
        uni.showToast({ title:error?.msg || error?.message || 'AI 顾问暂不可用', icon:'none' })
        emit('update:show', false)
    }
}

function ask(question:string) { if (sending.value) return; draft.value = question; void send() }
async function send() {
    const prompt = draft.value.trim()
    if (!prompt || sending.value || !props.projectId) return
    draft.value = ''
    messages.value.push({ id:uid(), role:'user', content:prompt })
    const assistant = reactive<Message>({ id:uid(), role:'assistant', content:'', loading:true, loadingText:'正在查询当前项目知识', humanRequired:false })
    messages.value.push(assistant)
    sending.value = true
    if (autoRead.value && !audio) audio = uni.createInnerAudioContext()
    const AbortControllerClass = (globalThis as any).AbortController
    controller = AbortControllerClass ? new AbortControllerClass() : null
    scrollBottom()
    let done:any = null
    try {
        await streamProjectAiAssistant(props.projectId, {
            request_id:uid(), conversation_id:conversationId.value, prompt, group_no:props.groupNo,
            messages:messages.value.slice(-10).filter(item => !item.loading).map(item => ({ role:item.role, content:item.content }))
        }, (event:any) => {
            if (event.type === 'content') { assistant.loading = false; assistant.content += String(event.delta || ''); scrollBottom() }
            if (event.type === 'conversation' && event.conversation_id) conversationId.value = Number(event.conversation_id)
            if (event.type === 'done') done = event
            if (event.type === 'error') throw new Error(event.message || 'AI 回答失败')
        }, controller?.signal)
        assistant.loading = false
        if (!assistant.content.trim()) assistant.content = '这个问题当前项目资料没有明确说明，需要群内工作人员确认。'
        assistant.humanRequired = Boolean(done?.human_handoff) || /群内工作人员|人工确认|没有明确说明|无法确认/.test(assistant.content)
        if (done?.conversation_id) conversationId.value = Number(done.conversation_id)
        if (Array.isArray(done?.suggestions) && done.suggestions.length) capability.suggestions = done.suggestions
        if (autoRead.value && capability.voice?.tts) void speak(assistant, true)
    } catch (error:any) {
        if (error?.name === 'AbortError') {
            if (!assistant.content) messages.value = messages.value.filter(item => item.id !== assistant.id)
        } else {
            assistant.loading = false
            assistant.content = error?.msg || error?.message || 'AI 暂时没有响应，请稍后重试或联系群内工作人员。'
            assistant.humanRequired = true
        }
    } finally { sending.value = false; controller = null; scrollBottom() }
}
function cancel() { controller?.abort(); controller = null; sending.value = false }
function audioSource(base64:string) {
    // #ifdef MP-WEIXIN
    const path = `${wx.env.USER_DATA_PATH}/hsx_project_ai_reply.mp3`; wx.getFileSystemManager().writeFileSync(path, base64, 'base64'); return path
    // #endif
    // #ifndef MP-WEIXIN
    return `data:audio/mpeg;base64,${base64}`
    // #endif
}
function stopSpeaking() { audio?.stop?.(); speakingId.value = '' }
async function speak(message:Message, automatic = false) {
    if (speakingId.value === String(message.id)) return stopSpeaking()
    stopSpeaking()
    if (!automatic) uni.showLoading({ title:'生成语音' })
    try {
        const text = message.content.replace(/[#*_>`~\[\]{}]/g, ' ').replace(/\s+/g, ' ').trim()
        const response:any = await projectAiTextToSpeech(props.projectId, text, props.groupNo)
        audio?.destroy?.(); audio = uni.createInnerAudioContext(); audio.src = audioSource(String(response?.data?.audio_base64 || ''))
        audio.onEnded(() => { speakingId.value = '' }); audio.onError(() => { speakingId.value = ''; uni.showToast({ title:'语音播放失败', icon:'none' }) })
        speakingId.value = String(message.id); audio.play()
    } catch (error:any) { uni.showToast({ title:error?.msg || error?.message || '语音生成失败', icon:'none' }) }
    finally { if (!automatic) uni.hideLoading() }
}
function toggleAutoRead() { autoRead.value = !autoRead.value; if (autoRead.value && !audio) audio = uni.createInnerAudioContext(); if (!autoRead.value) stopSpeaking() }
function contactHuman() {
    const question = [...messages.value].reverse().find(item => item.role === 'user')?.content || '我有一个项目问题需要确认'
    const group = props.groupNo ? `\n客户群编号：${props.groupNo}` : ''
    const content = `【项目咨询转人工】${group}\n客户问题：${question}\nAI 未能从当前项目知识库确认，请工作人员协助。`
    uni.setClipboardData({
        data: content,
        success() {
            uni.showToast({ title:'问题已复制，请返回群内发送', icon:'none', duration:2600 })
        }
    })
}
watch(() => props.show, (value) => { if (value) { void loadCapability(); scrollBottom() } })
watch(() => props.projectId, () => { loadedProjectId = 0; messages.value = []; conversationId.value = 0 })
onBeforeUnmount(() => { cancel(); stopSpeaking(); audio?.destroy?.() })
</script>

<style lang="scss" scoped>
.project-ai-panel { display:flex; height:86vh; max-height:1180rpx; flex-direction:column; overflow:hidden; background:#f6f8fc; }
.project-ai-header { display:flex; flex:0 0 auto; align-items:center; justify-content:space-between; padding:24rpx 28rpx 22rpx; border-bottom:1rpx solid #e7ecf3; background:#fff; }
.assistant-brand { display:flex; min-width:0; align-items:center; gap:16rpx; }.assistant-mark,.message-avatar { display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; background:linear-gradient(145deg,#315cf5,#7938de); }
.assistant-mark { width:68rpx; height:68rpx; border-radius:22rpx; font-size:23rpx; box-shadow:0 8rpx 20rpx rgba(49,92,245,.2); }.assistant-heading { display:flex; min-width:0; flex-direction:column; gap:5rpx; }.assistant-heading text:first-child { overflow:hidden; color:#1d2939; font-size:29rpx; font-weight:650; text-overflow:ellipsis; white-space:nowrap; }.assistant-heading text:last-child { color:#98a2b3; font-size:21rpx; }
.close-button { display:flex; width:60rpx; height:60rpx; align-items:center; justify-content:center; border-radius:50%; background:#f2f4f7; }
.project-ai-chat { min-height:0; flex:1; box-sizing:border-box; padding:24rpx 24rpx 36rpx; }
.welcome-card { padding:28rpx; border:1rpx solid #e2e8ff; border-radius:22rpx; background:linear-gradient(145deg,#fff,#f4f6ff); box-shadow:0 10rpx 30rpx rgba(49,92,245,.07); }.welcome-title { color:#1d2939; font-size:31rpx; font-weight:650; }.welcome-text { margin-top:12rpx; color:#667085; font-size:25rpx; line-height:1.65; }.knowledge-note { display:flex; align-items:center; gap:8rpx; margin-top:20rpx; color:#315cf5; font-size:21rpx; }
.persistent-questions { margin:0 -2rpx 12rpx; }.question-heading { display:flex; align-items:center; justify-content:space-between; padding:0 4rpx 9rpx; }.question-heading text:first-child { color:#344054; font-size:21rpx; font-weight:650; }.question-heading text:last-child { color:#98a2b3; font-size:18rpx; }.question-scroll { width:100%; white-space:nowrap; }.question-list { display:inline-flex; gap:10rpx; padding:0 4rpx 3rpx; }.question-chip { display:inline-flex; min-height:58rpx; max-width:360rpx; box-sizing:border-box; align-items:center; padding:12rpx 18rpx; overflow:hidden; border:1rpx solid #dce3ff; border-radius:99rpx; color:#315cf5; font-size:21rpx; line-height:1.35; text-overflow:ellipsis; white-space:nowrap; background:#f8faff; }.question-chip.disabled { opacity:.45; }
.message-row { display:flex; align-items:flex-start; gap:12rpx; margin-bottom:22rpx; }.message-row.role-user { justify-content:flex-end; }.message-avatar { flex:0 0 52rpx; width:52rpx; height:52rpx; border-radius:17rpx; font-size:18rpx; }.message-main { max-width:82%; }.message-bubble { padding:19rpx 22rpx; border-radius:20rpx; background:#fff; box-shadow:0 5rpx 18rpx rgba(16,24,40,.05); }.role-user .message-bubble { color:#fff; background:#315cf5; }.message-text { display:block; font-size:25rpx; line-height:1.7; white-space:pre-wrap; }.answer-loading { display:flex; align-items:center; gap:10rpx; color:#667085; font-size:23rpx; }
.message-tools { display:flex; gap:20rpx; margin-top:9rpx; padding-left:5rpx; }.message-tool { display:flex; align-items:center; gap:5rpx; color:#667085; font-size:21rpx; }.message-tool.human { color:#315cf5; }.after-actions { display:flex; justify-content:center; margin:8rpx 0 18rpx; }.after-action { display:flex; align-items:center; gap:8rpx; padding:14rpx 20rpx; border:1rpx solid #dce3ff; border-radius:99rpx; color:#315cf5; font-size:22rpx; background:#fff; }.chat-bottom { height:10rpx; }
.project-ai-composer { flex:0 0 auto; padding:14rpx 22rpx calc(14rpx + env(safe-area-inset-bottom)); border-top:1rpx solid #e7ecf3; background:#fff; }.auto-read { display:flex; align-items:center; gap:6rpx; width:max-content; margin:0 0 10rpx 4rpx; color:#98a2b3; font-size:20rpx; }.auto-read.active { color:#315cf5; }.composer-row { display:flex; align-items:flex-end; gap:12rpx; }.composer-input { min-height:72rpx; max-height:180rpx; flex:1; box-sizing:border-box; padding:17rpx 20rpx; border:1rpx solid #dfe4ec; border-radius:20rpx; color:#1d2939; font-size:25rpx; line-height:1.45; background:#f8fafc; }.send-button { display:flex; flex:0 0 72rpx; width:72rpx; height:72rpx; align-items:center; justify-content:center; border-radius:22rpx; background:#315cf5; }.send-button.stop { background:#f04438; }.send-button.disabled { opacity:.4; }.ai-disclaimer { margin-top:10rpx; color:#a0a8b5; font-size:18rpx; text-align:center; }
</style>
