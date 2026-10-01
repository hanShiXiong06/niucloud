<script lang="ts">
export default { name: 'HsxPdfPrint', inheritAttrs: false }
</script>

<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { Printer } from '@element-plus/icons-vue'
import HsxButton from '../HsxButton/index.vue'
import HsxDialog from '../HsxDialog/index.vue'

const props = withDefaults(defineProps<{
    loader: () => Promise<{ blob: Blob; filename?: string }>
    contextKey: string | number
    buttonText?: string
    title?: string
    disabled?: boolean
}>(), { buttonText: '打印面单', title: '打印面单', disabled: false })

const visible = ref(false), loading = ref(false), ready = ref(false)
const error = ref(''), hint = ref(''), filename = ref(''), pdfUrl = ref('')
const frame = ref<HTMLIFrameElement>()
let sequence = 0
let loadTimer: ReturnType<typeof setTimeout> | undefined
let autoPrint = false

function clearTimer() { if (loadTimer !== undefined) clearTimeout(loadTimer); loadTimer = undefined }
function release() {
    sequence++
    clearTimer()
    autoPrint = false
    loading.value = false
    ready.value = false
    const previous = pdfUrl.value
    pdfUrl.value = ''
    // Remove the PDF viewer before releasing its local URL. Never revoke on afterprint:
    // that event also fires on cancellation, and the customer may need to retry.
    frame.value?.remove()
    frame.value = undefined
    if (previous) URL.revokeObjectURL(previous)
}

async function open() {
    if (props.disabled || loading.value) return
    release()
    const current = sequence
    visible.value = true
    error.value = ''; hint.value = ''; filename.value = ''
    if (navigator.pdfViewerEnabled === false) {
        error.value = '当前浏览器未启用 PDF 预览，请使用电脑版 Chrome 或 Edge，并在浏览器设置中启用“在浏览器中打开 PDF”。也可使用原页面的下载按钮。'
        return
    }
    loading.value = true
    try {
        // Business permissions and authentication stay in the caller's loader.
        const result = await props.loader()
        if (current !== sequence || !visible.value || props.disabled) return
        if (!(result.blob instanceof Blob) || await result.blob.slice(0, 5).text() !== '%PDF-') {
            throw new Error('未取得有效 PDF，请刷新原任务后重试')
        }
        if (current !== sequence || !visible.value || props.disabled) return
        filename.value = result.filename || props.title
        autoPrint = true
        // Some download endpoints use application/octet-stream. Force inline PDF
        // locally without exposing the provider URL or putting credentials in a URL.
        pdfUrl.value = URL.createObjectURL(new Blob([result.blob], { type: 'application/pdf' }))
        loadTimer = setTimeout(() => {
            if (current !== sequence) return
            loading.value = false
            autoPrint = false
            hint.value = 'PDF 预览加载较慢。显示面单后点击“打开打印窗口”，或使用 PDF 预览内的打印图标。'
        }, 20000)
    } catch (cause: any) {
        if (current !== sequence || !visible.value) return
        loading.value = false
        error.value = cause?.msg || cause?.message || '面单读取失败，请关闭后刷新原任务；无需重新取号。'
    }
}

function requestPrint() {
    if (!visible.value || !ready.value || props.disabled || !frame.value?.contentWindow) return
    try {
        frame.value.contentWindow.focus()
        frame.value.contentWindow.print()
        hint.value = '请在打印窗口中选择打印机并确认。若未弹出，可点击“打开打印窗口”或 PDF 内的打印图标。'
    } catch {
        hint.value = '浏览器未允许自动打开打印窗口，请点击 PDF 预览内的打印图标。无需下载文件。'
    }
    // Calling print (including cancelling its dialog) never means paper was printed.
}

function loaded(event: Event) {
    if (!visible.value || !pdfUrl.value || event.target !== frame.value) return
    clearTimer()
    ready.value = true
    loading.value = false
    if (autoPrint) { autoPrint = false; requestPrint() }
}
function failed(event: Event) {
    if (event.target !== frame.value) return
    clearTimer(); loading.value = false; autoPrint = false
    error.value = '浏览器无法预览 PDF，请关闭后重试，或使用原页面的下载按钮。'
}
watch(visible, value => { if (!value) release() }, { flush: 'sync' })
watch(() => props.contextKey, () => { visible.value = false; release() }, { flush: 'sync' })
watch(() => props.disabled, value => { if (value) { visible.value = false; release() } }, { flush: 'sync' })
onBeforeUnmount(release)
</script>

<template>
    <HsxButton v-bind="$attrs" :icon="Printer" :action="open" :loading="loading" :disabled="disabled">{{ buttonText }}</HsxButton>
    <HsxDialog v-model="visible" :title="title" :subtitle="filename || '无需下载，面单加载后自动打开打印窗口'" size="md" :draggable="false" :show-fullscreen="false" :close-on-click-modal="false">
        <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon />
        <template v-else>
            <p class="hsx-pdf-print__note">选择实际使用的打印机及匹配纸张，核对预览后打印。关闭或取消打印不会改变业务状态。</p>
            <div v-loading="loading" element-loading-text="正在加载面单，请稍候…" class="hsx-pdf-print__preview">
                <iframe v-if="pdfUrl" :key="pdfUrl" ref="frame" :src="pdfUrl" :title="filename || title" @load="loaded" @error="failed" />
            </div>
            <p v-if="hint" role="status" class="hsx-pdf-print__note">{{ hint }}</p>
        </template>
        <template #footer>
            <el-button @click="visible = false">关闭</el-button>
            <el-button v-if="!error" type="primary" :icon="Printer" :disabled="!ready || disabled" @click="requestPrint">打开打印窗口</el-button>
        </template>
    </HsxDialog>
</template>

<style scoped>
.hsx-pdf-print__preview{height:55vh;min-height:240px;background:#f3f4f6;border:1px solid var(--el-border-color-light);border-radius:6px;overflow:hidden}
.hsx-pdf-print__preview iframe{display:block;width:100%;height:100%;border:0}
.hsx-pdf-print__note{margin:0 0 12px;color:var(--el-text-color-secondary);font-size:12px;line-height:1.7}
.hsx-pdf-print__preview + .hsx-pdf-print__note{margin:12px 0 0}
</style>
