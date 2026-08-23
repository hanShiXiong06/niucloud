<script lang="ts">
export default { name: 'HsxImport', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Download, UploadFilled } from '@element-plus/icons-vue'
import HsxButton from '../HsxButton/index.vue'
import HsxDialog from '../HsxDialog/index.vue'
import type { HsxImportContext } from '../../types'

const props = withDefaults(
    defineProps<{
        importer?: (file: File, context: HsxImportContext) => Promise<any> | any
        accept?: string
        maxSizeMb?: number
        title?: string
        buttonText?: string
        tip?: string
        templateUrl?: string
        templateText?: string
        closeOnSuccess?: boolean
        disabled?: boolean
        permission?: string | string[]
    }>(),
    {
        accept: '.xlsx,.xls,.csv',
        maxSizeMb: 20,
        title: '批量导入',
        buttonText: '导入',
        tip: '支持 Excel 或 CSV 文件，请先下载模板并按模板填写。',
        templateUrl: '',
        templateText: '下载导入模板',
        closeOnSuccess: true,
        disabled: false
    }
)

const emit = defineEmits<{
    (event: 'change', file: File | null): void
    (event: 'submit', file: File, context: HsxImportContext): void
    (event: 'success', result: any, file: File): void
    (event: 'error', error: unknown, file: File | null): void
    (event: 'open'): void
    (event: 'close'): void
}>()

const visible = ref(false)
const uploadRef = ref<any>()
const fileList = ref<any[]>([])
const selectedFile = ref<File | null>(null)
const submitting = ref(false)
const errorText = ref('')
const allowedExtensions = computed(() => props.accept.split(',').map((item) => item.trim().toLowerCase().replace(/^\./, '')).filter(Boolean))

function open() {
    visible.value = true
    errorText.value = ''
    emit('open')
}

function close() {
    visible.value = false
}

function handleDialogClose() {
    clear()
    emit('close')
}

function clear() {
    selectedFile.value = null
    fileList.value = []
    errorText.value = ''
    uploadRef.value?.clearFiles?.()
    emit('change', null)
}

function validateFile(file: File) {
    const extension = file.name.split('.').pop()?.toLowerCase() || ''
    if (allowedExtensions.value.length && !allowedExtensions.value.includes(extension)) {
        return `仅支持 ${props.accept} 格式文件`
    }
    if (file.size > props.maxSizeMb * 1024 * 1024) return `文件不能超过 ${props.maxSizeMb}MB`
    return ''
}

function handleChange(uploadFile: any, files: any[]) {
    const raw = uploadFile?.raw as File | undefined
    if (!raw) return
    const message = validateFile(raw)
    if (message) {
        errorText.value = message
        fileList.value = []
        selectedFile.value = null
        uploadRef.value?.clearFiles?.()
        emit('error', new Error(message), raw)
        return
    }
    errorText.value = ''
    selectedFile.value = raw
    fileList.value = files.slice(-1)
    emit('change', raw)
}

function handleExceed(files: File[]) {
    clear()
    const file = files[0]
    if (!file) return
    const uploadFile = Object.assign(file, { uid: Date.now() })
    uploadRef.value?.handleStart?.(uploadFile)
}

function handleRemove() {
    selectedFile.value = null
    fileList.value = []
    errorText.value = ''
    emit('change', null)
}

function downloadTemplate() {
    if (!props.templateUrl) return
    const anchor = document.createElement('a')
    anchor.href = props.templateUrl
    anchor.download = ''
    anchor.click()
}

async function submit() {
    const file = selectedFile.value
    if (!file) {
        errorText.value = '请先选择需要导入的文件'
        return
    }
    if (!props.importer) {
        const error = new Error('请为 HsxImport 配置 importer 方法')
        errorText.value = error.message
        emit('error', error, file)
        return
    }
    const extension = file.name.split('.').pop()?.toLowerCase() || ''
    const context = { filename: file.name, size: file.size, extension }
    submitting.value = true
    errorText.value = ''
    emit('submit', file, context)
    try {
        const result = await props.importer(file, context)
        emit('success', result, file)
        if (props.closeOnSuccess) close()
        clear()
        return result
    } catch (error) {
        errorText.value = error instanceof Error ? error.message : '导入失败，请稍后重试'
        emit('error', error, file)
        return undefined
    } finally {
        submitting.value = false
    }
}

defineExpose({ open, close, clear, submit, loading: submitting, file: selectedFile })
</script>

<template>
    <span class="hsx-import">
        <HsxButton v-bind="$attrs" :action="open" :disabled="disabled" :permission="permission">
            <slot name="trigger">{{ buttonText }}</slot>
        </HsxButton>

        <HsxDialog v-model="visible" :title="title" width="620px" :show-fullscreen="false" @close="handleDialogClose">
            <slot name="before-upload" />
            <el-upload
                ref="uploadRef"
                v-model:file-list="fileList"
                drag
                action="#"
                :auto-upload="false"
                :accept="accept"
                :limit="1"
                :on-change="handleChange"
                :on-exceed="handleExceed"
                :on-remove="handleRemove"
            >
                <el-icon class="hsx-import__icon"><UploadFilled /></el-icon>
                <div class="el-upload__text">将文件拖到这里，或<em>点击选择文件</em></div>
                <template #tip>
                    <div class="hsx-import__tip">{{ tip }}</div>
                </template>
            </el-upload>

            <el-alert v-if="errorText" class="hsx-import__error" :title="errorText" type="error" :closable="false" show-icon />
            <slot :file="selectedFile" />

            <template #footer>
                <div class="hsx-import__footer">
                    <el-button v-if="templateUrl" link type="primary" :icon="Download" @click="downloadTemplate">
                        {{ templateText }}
                    </el-button>
                    <span class="hsx-import__footer-space" />
                    <el-button @click="close">取消</el-button>
                    <el-button type="primary" :loading="submitting" @click="submit">开始导入</el-button>
                </div>
            </template>
        </HsxDialog>
    </span>
</template>

<style scoped>
.hsx-import { display: inline-flex; }
.hsx-import__icon { margin: 28px 0 12px; color: var(--el-color-primary); font-size: 64px; }
.hsx-import__tip { color: var(--el-text-color-secondary); line-height: 1.6; }
.hsx-import__error { margin-top: 14px; }
.hsx-import__footer { display: flex; width: 100%; align-items: center; }
.hsx-import__footer-space { flex: 1; }
</style>
