<template>
    <view v-if="visible" class="ai-entry" :class="`layout-${layout}`" :style="panelStyle" @click="openAssistant">
        <view class="ai-mark" :style="markStyle">AI</view>
        <view class="ai-copy">
            <view class="ai-heading">
                <text class="ai-title" :style="titleStyle">{{ title }}</text>
                <text v-if="showVoice" class="voice-hint" :style="hintStyle">{{ voiceHint }}</text>
            </view>
            <text class="ai-subtitle" :style="subtitleStyle">{{ subtitle }}</text>
        </view>
        <view class="ai-action" :style="actionStyle">
            <text>{{ buttonText }}</text>
            <u-icon name="arrow-right" size="13" :color="buttonTextColor" />
        </view>
    </view>
    <AiProjectAssistantPopup v-if="assistantType === 'project_center' && projectId > 0" v-model:show="projectPopupVisible" :project-id="projectId" :group-no="groupNo" />
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { redirect } from '@/utils/common'
import useDiyStore from '@/app/stores/diy'
import AiProjectAssistantPopup from '@/addon/hsx_ai/components/AiProjectAssistantPopup.vue'
import { getAiAssistantCapability, getProjectAiCapability } from '@/addon/hsx_ai/api/assistant'

const props = defineProps({
    component: { type: Object, default: () => ({}) },
    index: { type: Number, default: 0 }
})
const emit = defineEmits(['update:componentIsShow'])
const diyStore = useDiyStore()
const visible = ref(true)
const voiceAvailable = ref(true)
const projectPopupVisible = ref(false)

const diyComponent = computed(() => {
    if (diyStore.mode === 'decorate') return diyStore.value[props.index] || props.component
    return props.component
})

const layout = computed(() => diyComponent.value.layout || 'card')
const title = computed(() => diyComponent.value.title || 'AI 选机助手')
const subtitle = computed(() => diyComponent.value.subtitle || '说预算、品牌和成色，帮你从本站在售商品里挑选')
const buttonText = computed(() => diyComponent.value.buttonText || '开始咨询')
const voiceHint = computed(() => diyComponent.value.voiceHint || '支持语音咨询')
const showVoice = computed(() => diyComponent.value.showVoiceHint !== 0 && voiceAvailable.value)
const panelColor = computed(() => diyComponent.value.panelColor || '#FFFFFF')
const accentColor = computed(() => diyComponent.value.accentColor || '#2563EB')
const titleColor = computed(() => diyComponent.value.titleColor || '#172033')
const subtitleColor = computed(() => diyComponent.value.subtitleColor || '#667085')
const buttonTextColor = computed(() => diyComponent.value.buttonTextColor || '#FFFFFF')
const assistantType = computed(() => String(diyComponent.value.assistantType || 'phone_shop'))
const projectId = computed(() => Number(diyComponent.value.projectId || 0))
const groupNo = computed(() => String(diyComponent.value.groupNo || ''))

const panelStyle = computed(() => ({ backgroundColor: panelColor.value }))
const markStyle = computed(() => ({ backgroundColor: accentColor.value }))
const titleStyle = computed(() => ({ color: titleColor.value }))
const subtitleStyle = computed(() => ({ color: subtitleColor.value }))
const hintStyle = computed(() => ({ color: accentColor.value, borderColor: accentColor.value, backgroundColor: panelColor.value }))
const actionStyle = computed(() => ({ color: buttonTextColor.value, backgroundColor: accentColor.value }))

const openAssistant = () => {
    if (diyStore.mode === 'decorate') return
    if (assistantType.value === 'project_center' && projectId.value > 0) {
        projectPopupVisible.value = true
        return
    }
    redirect({ url: '/addon/hsx_ai/pages/chat/index' })
}

onMounted(async () => {
    if (diyStore.mode === 'decorate') return
    try {
        const response: any = assistantType.value === 'project_center' && projectId.value > 0
            ? await getProjectAiCapability(projectId.value, groupNo.value)
            : await getAiAssistantCapability()
        const capability = response?.data || {}
        visible.value = Boolean(capability.available)
        voiceAvailable.value = Boolean(capability.voice?.stt || capability.voice?.tts)
    } catch (_) {
        visible.value = false
    }
    emit('update:componentIsShow', visible.value)
})
</script>

<style lang="scss" scoped>
.ai-entry { display: flex; box-sizing: border-box; min-height: 176rpx; align-items: center; gap: 20rpx; padding: 26rpx; border: 1rpx solid #e5e9f0; border-radius: 16rpx; }
.ai-mark { display: flex; flex: 0 0 76rpx; width: 76rpx; height: 76rpx; align-items: center; justify-content: center; border-radius: 50%; color: #fff; font-size: 24rpx; font-weight: 700; }
.ai-copy { min-width: 0; flex: 1; }.ai-heading { display: flex; min-width: 0; align-items: center; gap: 12rpx; }.ai-title { overflow: hidden; font-size: 30rpx; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
.voice-hint { flex: 0 0 auto; padding: 5rpx 10rpx; border: 1rpx solid; border-radius: 6rpx; font-size: 19rpx; line-height: 1.2; }
.ai-subtitle { display: block; margin-top: 10rpx; font-size: 23rpx; line-height: 1.55; }
.ai-action { display: flex; flex: 0 0 auto; height: 64rpx; box-sizing: border-box; align-items: center; justify-content: center; gap: 4rpx; padding: 0 20rpx; border-radius: 8rpx; font-size: 23rpx; font-weight: 600; }
.layout-compact { min-height: 124rpx; padding: 20rpx 24rpx; }.layout-compact .ai-mark { flex-basis: 64rpx; width: 64rpx; height: 64rpx; }.layout-compact .ai-subtitle { max-width: 390rpx; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.layout-compact .voice-hint { display: none; }
</style>
