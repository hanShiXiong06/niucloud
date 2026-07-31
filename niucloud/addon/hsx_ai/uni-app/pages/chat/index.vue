<template>
    <view class="assistant-page" :style="themeColor()">
        <view class="assistant-header safe-area-inset-top">
            <view class="header-back" @click="goBack"><u-icon name="arrow-left" size="22" color="#1f2937" /></view>
            <view class="header-title"><text>AI 选机助手</text><text class="header-subtitle">仅查询本站实时商品</text></view>
            <view class="header-space" />
        </view>

        <scroll-view class="conversation" scroll-y :scroll-into-view="scrollTarget" :show-scrollbar="false">
            <view class="welcome-panel">
                <view class="welcome-mark">AI</view>
                <view><view class="welcome-title">想找什么机器？</view><view class="welcome-text">{{ capability.welcome }}</view></view>
            </view>

            <view v-for="(message, index) in messages" :id="`message-${index}`" :key="message.id" class="message-row" :class="message.role">
                <view class="message-bubble">
                    <text selectable>{{ message.content || (message.loading ? '正在查询本站商品…' : '') }}</text>
                    <view v-if="message.role === 'assistant' && message.content && capability.voice.tts" class="listen-action" @click="speak(message.content)">
                        <u-icon name="volume" size="15" color="#64748b" /><text>{{ speaking ? '播放中' : '听回复' }}</text>
                    </view>
                </view>
            </view>

            <scroll-view v-if="resources.length" scroll-x :show-scrollbar="false" class="product-strip">
                <view class="product-strip-inner">
                    <view v-for="item in resources" :key="item.goods_id" class="product-card" @click="openProduct(item)">
                        <image v-if="item.image" class="product-image" :src="img(item.image)" mode="aspectFill" />
                        <view class="product-name">{{ item.name }}</view>
                        <view class="product-meta">{{ [item.memory, item.condition].filter(Boolean).join(' · ') || item.sku_name }}</view>
                        <view class="product-bottom"><text class="product-price">¥{{ formatPrice(item.price) }}</text><text class="product-stock">库存 {{ item.stock }}</text></view>
                    </view>
                </view>
            </scroll-view>

            <view v-if="messages.length <= 1" class="suggestions">
                <view v-for="item in capability.suggestions" :key="item" class="suggestion" @click="submitSuggestion(item)">{{ item }}</view>
            </view>
            <view id="conversation-bottom" class="conversation-bottom" />
        </scroll-view>

        <view class="composer safe-area-inset-bottom">
            <view v-if="recording" class="recording-tip"><view class="recording-dot" />正在听，再点一次结束</view>
            <view class="composer-row">
                <view v-if="capability.voice.stt && voiceInputSupported" class="icon-button" :class="{ active: recording }" @click="toggleRecording">
                    <u-icon :name="recording ? 'pause-circle' : 'mic'" size="23" :color="recording ? '#ffffff' : '#334155'" />
                </view>
                <textarea v-model="draft" class="composer-input" :disabled="sending || recording" auto-height maxlength="500" confirm-type="send" placeholder="说说预算、品牌、内存或成色" @confirm="send" />
                <view class="send-button" :class="{ disabled: !draft.trim() || sending }" @click="send"><u-icon name="arrow-upward" size="21" color="#ffffff" /></view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, reactive, ref } from 'vue'
import { onLoad, onUnload } from '@dcloudio/uni-app'
import { img, redirect } from '@/utils/common'
import { getAiAssistantCapability, speechToText, streamAiAssistant, textToSpeech } from '@/addon/hsx_ai/api/assistant'

type ChatMessage = { id: string; role: 'user' | 'assistant'; content: string; loading?: boolean }
const capability = reactive<any>({ welcome: '告诉我你的预算和要求，我会按本站在售商品帮你挑选。', suggestions: [], voice: { stt: false, tts: false } })
const messages = ref<ChatMessage[]>([])
const resources = ref<any[]>([])
const draft = ref('')
const sending = ref(false)
const recording = ref(false)
const speaking = ref(false)
const scrollTarget = ref('conversation-bottom')
const voiceInputSupported = ref(false)
let recorder: any = null
let audio: any = null

const uid = () => `${Date.now()}_${Math.random().toString(36).slice(2)}`
const scrollBottom = () => nextTick(() => { scrollTarget.value = ''; nextTick(() => { scrollTarget.value = 'conversation-bottom' }) })
const goBack = () => uni.navigateBack({ fail: () => redirect({ url: '/addon/phone_shop/pages/index', mode: 'reLaunch' }) })
const submitSuggestion = (text: string) => { draft.value = text; send() }
const formatPrice = (value: any) => Number(value || 0).toFixed(0)
const openProduct = (item: any) => redirect({ url: '/addon/phone_shop/pages/goods/detail', param: { goods_id: item.goods_id } })

const send = async () => {
    const content = draft.value.trim()
    if (!content || sending.value) return
    draft.value = ''
    resources.value = []
    messages.value.push({ id: uid(), role: 'user', content })
    const assistant: ChatMessage = { id: uid(), role: 'assistant', content: '', loading: true }
    messages.value.push(assistant)
    sending.value = true
    scrollBottom()
    try {
        const history = messages.value.slice(0, -1).slice(-10).map(({ role, content }) => ({ role, content }))
        await streamAiAssistant({ request_id: `mall_${uid()}`, prompt: content, messages: history }, (event) => {
            if (event.type === 'content') { assistant.loading = false; assistant.content += event.delta || ''; scrollBottom() }
            if (event.type === 'resources') resources.value = event.items || []
            if (event.type === 'error') throw new Error(event.message || 'AI 响应失败')
        })
        if (!assistant.content) assistant.content = '暂时没有找到合适的商品，可以换个预算或品牌再问我。'
    } catch (error: any) {
        assistant.loading = false
        assistant.content = error?.msg || error?.message || '服务暂时没有响应，请稍后再试。'
    } finally { sending.value = false; scrollBottom() }
}

const initRecorder = () => {
    // #ifndef H5
    voiceInputSupported.value = true
    recorder = uni.getRecorderManager()
    recorder.onStop(async (result: any) => {
        recording.value = false
        uni.showLoading({ title: '识别中' })
        try {
            const response: any = await speechToText(result.tempFilePath)
            draft.value = String(response?.data?.text || '')
        } catch (error: any) {
            uni.showToast({ title: error?.msg || error?.message || '语音识别失败', icon: 'none' })
        } finally { uni.hideLoading() }
    })
    recorder.onError(() => { recording.value = false; uni.showToast({ title: '无法使用麦克风', icon: 'none' }) })
    // #endif
}
const toggleRecording = () => {
    if (!recorder) return
    if (recording.value) { recorder.stop(); return }
    recording.value = true
    recorder.start({ duration: 60000, sampleRate: 16000, numberOfChannels: 1, encodeBitRate: 48000, format: 'm4a' })
}

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
const speak = async (text: string) => {
    if (speaking.value) { audio?.stop(); speaking.value = false; return }
    uni.showLoading({ title: '生成语音' })
    try {
        const response: any = await textToSpeech(text)
        audio?.destroy()
        audio = uni.createInnerAudioContext()
        audio.src = audioSource(String(response?.data?.audio_base64 || ''))
        audio.onEnded(() => { speaking.value = false })
        audio.onError(() => { speaking.value = false; uni.showToast({ title: '语音播放失败', icon: 'none' }) })
        speaking.value = true
        audio.play()
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '语音合成失败', icon: 'none' })
    } finally { uni.hideLoading() }
}

onLoad(async () => {
    try {
        const response: any = await getAiAssistantCapability()
        Object.assign(capability, response?.data || {})
        if (!capability.available) return goBack()
        messages.value.push({ id: uid(), role: 'assistant', content: capability.welcome })
        initRecorder()
    } catch (_) { goBack() }
})
onUnload(() => { recorder?.stop(); audio?.destroy() })
onBeforeUnmount(() => audio?.destroy())
</script>

<style lang="scss" scoped>
.assistant-page { height: 100vh; overflow: hidden; background: #f4f6f8; color: #172033; }
.assistant-header { box-sizing: border-box; height: 112rpx; display: flex; align-items: center; justify-content: space-between; padding: 0 24rpx; background: #fff; border-bottom: 1rpx solid #e8ebef; }
.header-back, .header-space { width: 68rpx; height: 68rpx; display: flex; align-items: center; justify-content: center; }.header-title { display: flex; flex-direction: column; align-items: center; font-size: 30rpx; font-weight: 600; }.header-subtitle { margin-top: 3rpx; font-size: 20rpx; color: #8992a3; font-weight: 400; }
.conversation { height: calc(100vh - 112rpx - 154rpx - env(safe-area-inset-bottom)); box-sizing: border-box; padding: 24rpx; }
.welcome-panel { display: flex; gap: 20rpx; padding: 24rpx; margin-bottom: 28rpx; background: #fff; border: 1rpx solid #e6e9ee; border-radius: 8rpx; }.welcome-mark { flex: 0 0 64rpx; height: 64rpx; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #1f2937; color: #fff; font-size: 22rpx; font-weight: 700; }.welcome-title { font-size: 28rpx; font-weight: 600; }.welcome-text { margin-top: 8rpx; color: #667085; font-size: 24rpx; line-height: 1.55; }
.message-row { display: flex; margin: 18rpx 0; }.message-row.user { justify-content: flex-end; }.message-bubble { max-width: 82%; padding: 20rpx 24rpx; border-radius: 8rpx; background: #fff; font-size: 27rpx; line-height: 1.65; white-space: pre-wrap; word-break: break-word; }.user .message-bubble { background: var(--primary-color); color: #fff; }.listen-action { display: flex; align-items: center; gap: 6rpx; width: max-content; margin-top: 12rpx; color: #64748b; font-size: 22rpx; }
.product-strip { margin: 16rpx -24rpx 8rpx; width: calc(100% + 48rpx); }.product-strip-inner { display: flex; gap: 16rpx; padding: 0 24rpx; }.product-card { box-sizing: border-box; flex: 0 0 300rpx; padding: 16rpx; background: #fff; border: 1rpx solid #e3e7ec; border-radius: 8rpx; }.product-image { width: 100%; height: 190rpx; margin-bottom: 14rpx; border-radius: 6rpx; background: #f3f5f7; }.product-name { height: 76rpx; overflow: hidden; font-size: 26rpx; font-weight: 600; line-height: 38rpx; }.product-meta { margin-top: 8rpx; color: #778195; font-size: 22rpx; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }.product-bottom { display: flex; align-items: flex-end; justify-content: space-between; margin-top: 18rpx; }.product-price { color: #d92d20; font-size: 30rpx; font-weight: 700; }.product-stock { color: #98a2b3; font-size: 20rpx; }
.suggestions { display: flex; flex-wrap: wrap; gap: 12rpx; margin: 20rpx 0; }.suggestion { padding: 14rpx 20rpx; background: #fff; border: 1rpx solid #dce1e8; border-radius: 8rpx; color: #475467; font-size: 23rpx; }.conversation-bottom { height: 32rpx; }
.composer { position: fixed; left: 0; right: 0; bottom: 0; box-sizing: border-box; padding: 18rpx 22rpx; background: #fff; border-top: 1rpx solid #e5e8ed; }.composer-row { display: flex; align-items: flex-end; gap: 14rpx; }.composer-input { box-sizing: border-box; flex: 1; min-height: 76rpx; max-height: 180rpx; padding: 18rpx 20rpx; background: #f3f5f7; border-radius: 8rpx; font-size: 26rpx; line-height: 40rpx; }.icon-button, .send-button { flex: 0 0 76rpx; height: 76rpx; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #eef1f4; }.icon-button.active, .send-button { background: var(--primary-color); }.send-button.disabled { opacity: .38; }.recording-tip { display: flex; align-items: center; justify-content: center; gap: 10rpx; margin-bottom: 14rpx; color: #475467; font-size: 23rpx; }.recording-dot { width: 14rpx; height: 14rpx; border-radius: 50%; background: #ef4444; }
</style>
