<script lang="ts">
export default { name: 'HsxUpload' }
</script>

<script setup lang="ts">
import { ref, watch } from 'vue'
import type { MobileUploadContext, MobileUploadItem, MobileUploadResult } from '../../types'
import { deepClone, isDeepEqual } from '../../utils'

const props = withDefaults(
    defineProps<{
        modelValue?: MobileUploadItem[]
        uploader?: (file: any, context: MobileUploadContext) => Promise<MobileUploadResult>
        resultAdapter?: (result: MobileUploadResult, file: any) => MobileUploadItem
        accept?: 'all' | 'media' | 'image' | 'file' | 'video'
        capture?: string | string[]
        maxCount?: number
        maxSize?: number
        multiple?: boolean
        disabled?: boolean
        deletable?: boolean
        previewFullImage?: boolean
        width?: string | number
        height?: string | number
        uploadText?: string
        showMessage?: boolean
    }>(),
    {
        modelValue: () => [],
        accept: 'image',
        capture: () => ['album', 'camera'],
        maxCount: 9,
        maxSize: 10 * 1024 * 1024,
        multiple: true,
        disabled: false,
        deletable: true,
        previewFullImage: true,
        width: 80,
        height: 80,
        uploadText: '',
        showMessage: true
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: MobileUploadItem[]): void
    (event: 'change', value: MobileUploadItem[]): void
    (event: 'success', item: MobileUploadItem, result: MobileUploadResult): void
    (event: 'error', error: unknown, item: MobileUploadItem): void
    (event: 'remove', item: MobileUploadItem, index: number): void
    (event: 'progress', percentage: number, item: MobileUploadItem): void
    (event: 'oversize', detail: any): void
}>()

const fileList = ref<MobileUploadItem[]>(deepClone(props.modelValue))
const rawFiles = new Map<string | number, any>()
let uidSeed = 0

watch(
    () => props.modelValue,
    (value) => {
        if (!isDeepEqual(value, fileList.value)) fileList.value = deepClone(value || [])
    },
    { deep: true }
)

function syncValue() {
    const value = deepClone(fileList.value)
    emit('update:modelValue', value)
    emit('change', value)
    return value
}

function createUid() {
    uidSeed += 1
    return `${Date.now()}-${uidSeed}`
}

function localUrl(file: any) {
    return file?.url || file?.path || file?.tempFilePath || file?.thumb || ''
}

function sizeUnit(value: string | number) {
    return typeof value === 'number' ? `${value}px` : String(value)
}

function normalizeResult(result: MobileUploadResult, file: any): MobileUploadItem {
    if (props.resultAdapter) return props.resultAdapter(result, file)
    const payload: any = (result as any)?.data?.data ?? (result as any)?.data ?? result
    const url = typeof payload === 'string' ? payload : payload?.url || payload?.src || payload?.path
    if (!url) throw new Error('上传结果缺少 url，请配置 resultAdapter')
    return {
        ...(typeof payload === 'object' ? payload : {}),
        name: payload?.name || file?.name,
        url,
        size: file?.size,
        type: payload?.type || file?.type || props.accept,
        status: 'success',
        message: ''
    }
}

function updateItem(uid: string | number, patch: Partial<MobileUploadItem>) {
    const index = fileList.value.findIndex((item) => item.uid === uid)
    if (index < 0) return undefined
    fileList.value.splice(index, 1, { ...fileList.value[index], ...patch })
    return fileList.value[index]
}

async function uploadFile(file: any, existingUid?: string | number) {
    const uid = existingUid || createUid()
    rawFiles.set(uid, file)
    let item = updateItem(uid, {
        status: 'uploading',
        message: '上传中',
        percentage: 0
    })

    if (!item) {
        item = {
            uid,
            name: file?.name,
            url: localUrl(file),
            thumb: file?.thumb,
            size: file?.size,
            type: file?.type || props.accept,
            status: 'uploading',
            message: '上传中',
            percentage: 0
        }
        fileList.value.push(item)
    }
    syncValue()

    try {
        if (!props.uploader) throw new Error('HsxUpload 缺少 uploader，请在业务层提供上传适配器')
        const result = await props.uploader(file, {
            uid,
            index: fileList.value.findIndex((value) => value.uid === uid),
            onProgress(percentage) {
                const percent = Math.max(0, Math.min(100, Number(percentage) || 0))
                const current = updateItem(uid, { percentage: percent, message: `上传中 ${Math.round(percent)}%` })
                if (current) emit('progress', percent, deepClone(current))
            }
        })
        const successItem = { ...normalizeResult(result, file), uid }
        updateItem(uid, successItem)
        rawFiles.delete(uid)
        syncValue()
        emit('success', deepClone(successItem), result)
        return successItem
    } catch (error) {
        const failedItem = updateItem(uid, { status: 'failed', message: '上传失败，点击重试' })!
        syncValue()
        emit('error', error, deepClone(failedItem))
        if (props.showMessage) uni.showToast({ title: error instanceof Error ? error.message : '上传失败', icon: 'none' })
        return undefined
    }
}

async function handleAfterRead(detail: any) {
    const files = Array.isArray(detail.file) ? detail.file : [detail.file]
    for (const file of files) await uploadFile(file)
}

function handleDelete(detail: any) {
    const index = Number(detail.index)
    const [item] = fileList.value.splice(index, 1)
    if (!item) return
    if (item.uid !== undefined) rawFiles.delete(item.uid)
    syncValue()
    emit('remove', deepClone(item), index)
}

function handleOversize(detail: any) {
    emit('oversize', detail)
    if (props.showMessage) uni.showToast({ title: `文件不能超过 ${Math.ceil(props.maxSize / 1024 / 1024)}MB`, icon: 'none' })
}

function retry(index: number) {
    const item = fileList.value[index]
    if (!item?.uid) return
    const file = rawFiles.get(item.uid)
    if (file) return uploadFile(file, item.uid)
}

function clear() {
    fileList.value = []
    rawFiles.clear()
    return syncValue()
}

defineExpose({ fileList, retry, clear })
</script>

<template>
    <view class="hsx-upload">
        <u-upload
            :file-list="fileList"
            :accept="accept"
            :capture="capture"
            :max-count="maxCount"
            :max-size="maxSize"
            :multiple="multiple"
            :disabled="disabled"
            :deletable="deletable"
            :preview-full-image="previewFullImage"
            :width="width"
            :height="height"
            :upload-text="uploadText"
            @after-read="handleAfterRead"
            @delete="handleDelete"
            @oversize="handleOversize"
        >
            <slot>
                <view class="hsx-upload__button" :style="{ width: sizeUnit(width), height: sizeUnit(height) }">
                    <u-icon name="camera-fill" size="26" color="var(--hsx-mobile-text-secondary, #c8c9cc)" />
                    <text v-if="uploadText" class="hsx-upload__text">{{ uploadText }}</text>
                </view>
            </slot>
        </u-upload>

        <view v-for="(item, index) in fileList" :key="String(item.uid || item.url || index)">
            <view v-if="item.status === 'failed'" class="hsx-upload__retry" @click="retry(index)">
                第 {{ index + 1 }} 个文件上传失败，点击重试
            </view>
        </view>
    </view>
</template>

<style scoped lang="scss">
.hsx-upload__button {
    display: flex;
    box-sizing: border-box;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 8rpx;
    border: 1rpx dashed var(--hsx-mobile-border, #dfe4ec);
    background: var(--hsx-mobile-bg-muted, #f4f5f7);
}

.hsx-upload__text {
    margin-top: 8rpx;
    color: var(--hsx-mobile-text-secondary, #909399);
    font-size: 22rpx;
}

.hsx-upload__retry {
    margin-top: 12rpx;
    color: var(--hsx-mobile-danger, #f56c6c);
    font-size: 24rpx;
}
</style>
