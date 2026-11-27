<script lang="ts">
export default { name: 'HsxUpload', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useFeedback } from '../../hooks/useFeedback'
import type { HsxUploadContext, HsxUploadItem, HsxUploadResult } from '../../types'
import { deepClone, isDeepEqual } from '../../utils'

const props = withDefaults(
    defineProps<{
        modelValue?: HsxUploadItem[]
        uploader?: (file: File, context: HsxUploadContext) => Promise<HsxUploadResult>
        resultAdapter?: (result: HsxUploadResult, file: File) => HsxUploadItem
        accept?: string
        maxSizeMb?: number
        limit?: number
        multiple?: boolean
        disabled?: boolean
        drag?: boolean
        listType?: 'text' | 'picture' | 'picture-card'
        autoUpload?: boolean
        tip?: string
        showMessage?: boolean
        previewSize?: string | number
        previewWidth?: string | number
        previewHeight?: string | number
        previewRadius?: string | number
        shape?: 'square' | 'circle'
    }>(),
    {
        modelValue: () => [],
        accept: 'image/*',
        maxSizeMb: 10,
        limit: 9,
        multiple: true,
        disabled: false,
        drag: false,
        listType: 'picture-card',
        autoUpload: true,
        tip: '',
        showMessage: true,
        previewSize: 96,
        previewRadius: 10,
        shape: 'square'
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: HsxUploadItem[]): void
    (event: 'change', value: HsxUploadItem[]): void
    (event: 'success', item: HsxUploadItem, result: HsxUploadResult): void
    (event: 'error', error: unknown, file: File): void
    (event: 'remove', item: HsxUploadItem): void
    (event: 'preview', item: HsxUploadItem): void
    (event: 'exceed', files: File[]): void
    (event: 'progress', percentage: number, file: File): void
}>()

const uploadRef = ref<any>()
const fileList = ref<any[]>(deepClone(props.modelValue))
const feedback = useFeedback()

function toCssUnit(value: string | number | undefined, fallback: string | number) {
    const actual = value ?? fallback
    return typeof actual === 'number' ? `${actual}px` : actual
}

const uploadStyle = computed(() => ({
    '--hsx-upload-preview-width': toCssUnit(props.previewWidth, props.previewSize),
    '--hsx-upload-preview-height': toCssUnit(props.previewHeight, props.previewSize),
    '--hsx-upload-preview-radius': props.shape === 'circle' ? '50%' : toCssUnit(props.previewRadius, 10)
}))

const showTrigger = computed(() => props.limit <= 0 || fileList.value.length < props.limit)

watch(
    () => props.modelValue,
    (value) => {
        if (!isDeepEqual(value, serializeList())) fileList.value = deepClone(value || [])
    },
    { deep: true }
)

function normalizeResult(result: HsxUploadResult, file: File): HsxUploadItem {
    if (props.resultAdapter) return props.resultAdapter(result, file)
    const payload: any = (result as any)?.data?.data ?? (result as any)?.data ?? result
    const url = typeof payload === 'string' ? payload : payload?.url || payload?.src || payload?.path
    if (!url) throw new Error('上传结果缺少 url，请配置 resultAdapter')
    return {
        ...(typeof payload === 'object' ? payload : {}),
        name: payload?.name || file.name,
        url,
        size: file.size,
        type: file.type,
        status: 'success'
    }
}

function serializeItem(item: any): HsxUploadItem {
    const { raw: _raw, response: _response, ...value } = item || {}
    return {
        ...value,
        name: value.name || '',
        url: value.url || '',
        status: value.status === 'fail' ? 'fail' : value.status
    }
}

function serializeList() {
    return fileList.value.filter((item) => item.status !== 'ready').map(serializeItem)
}

function syncValue() {
    const value = serializeList()
    emit('update:modelValue', value)
    emit('change', value)
    return value
}

function beforeUpload(file: File) {
    if (props.maxSizeMb > 0 && file.size > props.maxSizeMb * 1024 * 1024) {
        if (props.showMessage) feedback.error(`文件不能超过 ${props.maxSizeMb}MB`)
        return false
    }
    return true
}

async function handleRequest(options: any) {
    const file = options.file as File & { uid?: string | number }
    const uid = file.uid || `${Date.now()}-${file.name}`
    try {
        if (!props.uploader) throw new Error('HsxUpload 缺少 uploader，请在业务层提供上传适配器')
        const result = await props.uploader(file, {
            uid,
            name: file.name,
            size: file.size,
            type: file.type,
            onProgress(percentage) {
                const percent = Math.max(0, Math.min(100, Number(percentage) || 0))
                options.onProgress?.({ percent })
                emit('progress', percent, file)
            }
        })
        const item = { ...normalizeResult(result, file), uid }
        options.onSuccess?.(result)
        await nextTick()
        const index = fileList.value.findIndex((value) => value.uid === uid)
        if (index >= 0) fileList.value.splice(index, 1, { ...fileList.value[index], ...item })
        else fileList.value.push(item)
        syncValue()
        emit('success', item, result)
        if (props.showMessage) feedback.success('上传成功')
        return result
    } catch (error) {
        options.onError?.(error)
        emit('error', error, file)
        if (props.showMessage) feedback.error(error instanceof Error ? error.message : '上传失败')
        throw error
    }
}

function handleRemove(uploadFile: any) {
    void nextTick(() => {
        const item = serializeItem(uploadFile)
        syncValue()
        emit('remove', item)
    })
}

function handlePreview(uploadFile: any) {
    emit('preview', serializeItem(uploadFile))
}

function handleExceed(files: File[]) {
    emit('exceed', files)
    if (props.showMessage) feedback.warning(`最多上传 ${props.limit} 个文件`)
}

function clearFiles() {
    uploadRef.value?.clearFiles()
    fileList.value = []
    return syncValue()
}

function submit() {
    uploadRef.value?.submit()
}

defineExpose({ uploadRef, fileList, submit, clearFiles })
</script>

<template>
    <div class="hsx-upload" :class="`hsx-upload--${shape}`" :style="uploadStyle">
        <el-upload
            ref="uploadRef"
            v-model:file-list="fileList"
            v-bind="$attrs"
            :http-request="handleRequest"
            :before-upload="beforeUpload"
            :accept="accept"
            :limit="limit"
            :multiple="multiple"
            :disabled="disabled"
            :drag="drag"
            :list-type="listType"
            :auto-upload="autoUpload"
            @remove="handleRemove"
            @preview="handlePreview"
            @exceed="handleExceed"
        >
            <template v-if="showTrigger">
                <slot>
                    <template v-if="drag">
                        <el-icon class="hsx-upload__icon"><UploadFilled /></el-icon>
                        <div class="el-upload__text">拖拽文件到这里，或<em>点击上传</em></div>
                    </template>
                    <el-icon v-else-if="listType === 'picture-card'"><Plus /></el-icon>
                    <el-button v-else type="primary">选择文件</el-button>
                </slot>
            </template>

            <template v-for="(_, name) in $slots" #[name]="slotProps">
                <slot v-if="name !== 'default'" :name="name" v-bind="slotProps || {}" />
            </template>
        </el-upload>
        <div v-if="tip" class="hsx-upload__tip">{{ tip }}</div>
    </div>
</template>

<style scoped lang="scss">
.hsx-upload {
    max-width: 100%;
    overflow: hidden;
}

.hsx-upload :deep(.el-upload-list--picture-card) {
    display: flex;
    max-width: 100%;
    flex-wrap: wrap;
    gap: 10px;
}

.hsx-upload :deep(.el-upload--picture-card),
.hsx-upload :deep(.el-upload-list--picture-card .el-upload-list__item) {
    width: var(--hsx-upload-preview-width);
    height: var(--hsx-upload-preview-height);
    margin: 0;
    overflow: hidden;
    border-radius: var(--hsx-upload-preview-radius);
}

.hsx-upload--circle :deep(.el-upload-list__item-thumbnail) {
    border-radius: 50%;
}

.hsx-upload__icon {
    font-size: 48px;
    color: var(--el-text-color-placeholder);
}

.hsx-upload__tip {
    margin-top: 6px;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    line-height: 1.5;
}
</style>
