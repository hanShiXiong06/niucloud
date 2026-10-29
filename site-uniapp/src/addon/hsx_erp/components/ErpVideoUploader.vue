<template>
    <view class="erp-video">
        <view class="erp-video__row">
            <view class="erp-video__copy"><text class="erp-video__title">商品视频</text><text class="erp-video__hint">选填，最多 1 个，100MB 以内</text></view>
            <view v-if="currentUrl" class="erp-video__actions"><text class="erp-video__link" @click="preview">预览</text><text class="erp-video__link" :class="{ 'is-disabled': disabled || busy }" @click="choose">更换</text><text class="erp-video__remove" :class="{ 'is-disabled': disabled || busy }" @click="remove">删除</text></view>
            <view v-else class="erp-video__add" :class="{ 'is-disabled': disabled || busy }" @click="choose"><u-icon name="plus" size="16" color="#2563eb" /><text>{{ busy ? '处理中' : '添加视频' }}</text></view>
        </view>
        <view v-if="busy" class="erp-video__progress" role="status"><u-loading-icon size="15" color="#2563eb" /><text>{{ phase === 'choosing' ? '正在选择视频…' : `正在上传${fileSizeLabel ? ` · ${fileSizeLabel}` : ''}，请稍候…` }}</text></view>
        <view v-if="busy && phase === 'uploading'" class="erp-video__track"><view class="erp-video__track-bar"></view></view>
        <view v-if="error" class="erp-video__error" role="status"><text>{{ error }}{{ currentUrl ? '，原视频未更换。' : '' }}</text><text v-if="pendingVideo" class="erp-video__link" :class="{ 'is-disabled': disabled || busy }" @click="retry">重试上传</text><text class="erp-video__link" :class="{ 'is-disabled': disabled || busy }" @click="choose">重新选择</text></view>
        <text v-else-if="currentUrl && !busy" class="erp-video__saved">已添加 1 个视频</text>
        <u-popup :show="previewVisible" mode="center" :z-index="10160" :safe-area-inset-bottom="false" @close="closePreview">
            <view v-if="previewVisible" class="erp-video-preview" @touchmove.stop.prevent="noop"><view class="erp-video-preview__head"><text>视频预览</text><view class="erp-video-preview__close" role="button" aria-label="关闭视频预览" @click="closePreview"><u-icon name="close" size="22" color="#fff" /></view></view><video :id="videoId" class="erp-video-preview__player" :src="playbackUrl" :controls="true" :autoplay="true" :show-center-play-btn="true" object-fit="contain" /></view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { computed, getCurrentInstance, onBeforeUnmount, ref, watch } from 'vue'
import { uploadVideo } from '@/app/api/system'
import { img } from '@/utils/common'
const props = withDefaults(defineProps<{ modelValue?: string; disabled?: boolean }>(), { modelValue: '', disabled: false })
const emit = defineEmits<{ (event: 'update:modelValue', value: string): void; (event: 'busy-change', value: boolean): void }>()
type ChosenVideo = { path: string; size: number }
const currentUrl = computed(() => String(props.modelValue || '').trim())
const playbackUrl = computed(() => img(currentUrl.value))
const busy = ref(false), phase = ref<'choosing' | 'uploading'>('choosing'), error = ref(''), previewVisible = ref(false)
const pendingVideo = ref<ChosenVideo | null>(null)
const instance = getCurrentInstance(), videoId = `erp-listing-video-${instance?.uid || 0}`
const fileSizeLabel = computed(() => pendingVideo.value ? `${(pendingVideo.value.size / 1024 / 1024).toFixed(1)}MB` : '')
let disposed = false, requestSeq = 0, ownsLoading = false
function noop() {}
function setBusy(value: boolean) { busy.value = value; emit('busy-change', value) }
function toast(message: string) { if (typeof uni.showToast === 'function') uni.showToast({ title: message, icon: 'none' }) }
function hideLoading() { if (ownsLoading) { ownsLoading = false; uni.hideLoading() } }
function isCurrent(token: number) { return !disposed && token === requestSeq }
function messageOf(exception: any) { return String(exception?.message || exception?.msg || exception?.errMsg || '上传失败，请检查网络后重试').slice(0, 150) }
async function send(token: number) {
    const file = pendingVideo.value
    if (!file || !isCurrent(token)) return
    phase.value = 'uploading'; error.value = ''
    uni.showLoading({ title: '视频上传中…', mask: true }); ownsLoading = true
    try {
        const response: any = await uploadVideo({ filePath: file.path, name: 'file' })
        if (!isCurrent(token)) return
        const url = typeof response?.data?.url === 'string' ? response.data.url.trim() : ''
        if (!url) throw new Error('上传结果缺少视频地址，请重试')
        // 只有上传成功才替换旧视频；不触碰商品保存与数据库。
        emit('update:modelValue', url); pendingVideo.value = null; error.value = ''
    } catch (exception: any) {
        if (isCurrent(token)) error.value = messageOf(exception)
    } finally { hideLoading() }
}
async function choose() {
    if (props.disabled || busy.value || disposed) return
    const token = ++requestSeq
    setBusy(true); phase.value = 'choosing'; error.value = ''
    try {
        const result: any = await new Promise((resolve, reject) => uni.chooseVideo({ sourceType: ['album', 'camera'], compressed: true, success: resolve, fail: reject }))
        if (!isCurrent(token)) return
        const path = String(result?.tempFilePath || '').trim(), size = Number(result?.size)
        if (!path) throw new Error('未取得视频文件，请重新选择')
        if (!Number.isFinite(size) || size <= 0) throw new Error('无法读取视频大小，请重新选择')
        if (size > 100 * 1024 * 1024) { toast('视频大小不能超过100MB'); return }
        pendingVideo.value = { path, size }
        await send(token)
    } catch (exception: any) {
        if (isCurrent(token) && !/cancel/i.test(String(exception?.errMsg || ''))) error.value = messageOf(exception)
    } finally { if (isCurrent(token)) setBusy(false) }
}
async function retry() {
    if (props.disabled || busy.value || disposed || !pendingVideo.value) return
    const token = ++requestSeq
    setBusy(true)
    try { await send(token) } finally { if (isCurrent(token)) setBusy(false) }
}
function preview() { if (currentUrl.value) previewVisible.value = true }
function closePreview() { previewVisible.value = false; if (typeof uni.createVideoContext === 'function') uni.createVideoContext(videoId, instance?.proxy as any)?.pause?.() }
function remove() { if (props.disabled || busy.value) return; closePreview(); pendingVideo.value = null; error.value = ''; emit('update:modelValue', '') }
watch(currentUrl, value => { if (!value) closePreview() })
onBeforeUnmount(() => { disposed = true; requestSeq++; closePreview(); hideLoading(); if (busy.value) setBusy(false) })
</script>

<style scoped lang="scss">
.erp-video{padding:18rpx 0;min-width:0}.erp-video__row{display:flex;align-items:center;justify-content:space-between;gap:18rpx}.erp-video__copy{display:flex;flex-direction:column;gap:6rpx;min-width:0}.erp-video__title{font-size:27rpx;color:#334155}.erp-video__hint{font-size:22rpx;line-height:1.5;color:#94a3b8}.erp-video__actions{display:flex;align-items:center;gap:20rpx;flex-shrink:0}.erp-video__link{color:#2563eb;font-size:25rpx;line-height:1.6}.erp-video__remove{color:#64748b;font-size:25rpx}.erp-video__add{display:flex;align-items:center;gap:8rpx;flex-shrink:0;padding:16rpx 0;color:#2563eb;font-size:26rpx}.is-disabled{opacity:.45}.erp-video__progress{display:flex;align-items:center;gap:12rpx;margin-top:18rpx;color:#64748b;font-size:24rpx;line-height:1.6}.erp-video__track{height:5rpx;margin-top:12rpx;border-radius:6rpx;overflow:hidden;background:#e2e8f0}.erp-video__track-bar{height:100%;width:40%;border-radius:6rpx;background:#60a5fa;animation:erp-video-progress 1.4s ease-in-out infinite}.erp-video__error{display:flex;align-items:center;flex-wrap:wrap;gap:12rpx 20rpx;margin-top:14rpx;color:#b45309;font-size:24rpx;line-height:1.7}.erp-video__saved{display:block;margin-top:12rpx;color:#64748b;font-size:23rpx}.erp-video-preview{display:flex;flex-direction:column;width:100vw;height:76vh;background:#0f172a;color:#fff}.erp-video-preview__head{display:flex;align-items:center;justify-content:space-between;flex-shrink:0;padding:14rpx 20rpx 14rpx 28rpx;font-size:28rpx}.erp-video-preview__close{display:flex;align-items:center;justify-content:center;width:84rpx;height:84rpx}.erp-video-preview__player{width:100%;flex:1;min-height:0}@keyframes erp-video-progress{0%{transform:translateX(-100%)}100%{transform:translateX(350%)}}
</style>
