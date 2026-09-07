<script lang="ts">
export default { name: 'HsxDialog', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { FullScreen, ScaleToOriginal } from '@element-plus/icons-vue'

type DialogSize = 'sm' | 'md' | 'lg' | 'xl'
type FooterAlign = 'left' | 'center' | 'right' | 'between'

const props = withDefaults(defineProps<{
    modelValue: boolean
    title?: string
    subtitle?: string
    width?: string | number
    size?: DialogSize
    fullscreen?: boolean
    showFullscreen?: boolean
    draggable?: boolean
    alignCenter?: boolean
    top?: string
    bodyMaxHeight?: string
    bodyLoading?: boolean
    closeOnClickModal?: boolean
    destroyOnClose?: boolean
    appendToBody?: boolean
    showFooter?: boolean
    footerAlign?: FooterAlign
    confirmText?: string
    cancelText?: string
    confirmLoading?: boolean
    confirmDisabled?: boolean
    beforeClose?: (done: () => void) => void
    closeOnPressEscape?: boolean
    showClose?: boolean
}>(), {
    title: '',
    subtitle: '',
    width: '',
    size: 'md',
    fullscreen: false,
    showFullscreen: true,
    draggable: true,
    alignCenter: false,
    top: '6vh',
    bodyMaxHeight: '70vh',
    bodyLoading: false,
    closeOnClickModal: false,
    destroyOnClose: true,
    appendToBody: true,
    showFooter: false,
    footerAlign: 'right',
    confirmText: '确定',
    cancelText: '取消',
    confirmLoading: false,
    confirmDisabled: false,
    closeOnPressEscape: true,
    showClose: true
})

const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void
    (event: 'update:fullscreen', value: boolean): void
    (event: 'open'): void
    (event: 'close'): void
    (event: 'closed'): void
    (event: 'confirm'): void
    (event: 'cancel'): void
}>()

const WIDTHS: Record<DialogSize, string> = {
    sm: 'min(480px, calc(100vw - 32px))',
    md: 'min(720px, calc(100vw - 32px))',
    lg: 'min(1040px, calc(100vw - 32px))',
    xl: 'min(1160px, calc(100vw - 32px))'
}

const innerFullscreen = ref(props.fullscreen)
const visible = computed({
    get: () => props.modelValue,
    set: (value: boolean) => emit('update:modelValue', value)
})
const resolvedWidth = computed(() => {
    if (typeof props.width === 'number') return `${props.width}px`
    return props.width || WIDTHS[props.size]
})
const bodyStyle = computed(() => ({
    maxHeight: innerFullscreen.value ? 'calc(100vh - 148px)' : props.bodyMaxHeight
}))

watch(() => props.fullscreen, (value) => (innerFullscreen.value = value))

function toggleFullscreen() {
    innerFullscreen.value = !innerFullscreen.value
    emit('update:fullscreen', innerFullscreen.value)
}

function requestClose(afterClose?: () => void) {
    if (props.confirmLoading) return
    const done = () => { afterClose?.(); visible.value = false }
    if (props.beforeClose) props.beforeClose(done)
    else done()
}
function close() { requestClose() }
function cancel() { requestClose(() => emit('cancel')) }
function confirm() { if (!props.confirmLoading && !props.confirmDisabled) emit('confirm') }

defineExpose({ close, toggleFullscreen, fullscreen: innerFullscreen })
</script>

<template>
    <el-dialog
        v-model="visible"
        v-bind="$attrs"
        class="hsx-dialog"
        :style="{ '--hsx-dialog-top': alignCenter || innerFullscreen ? '0px' : top }"
        :title="title"
        :class="{ 'hsx-dialog--fullscreen': innerFullscreen }"
        :width="resolvedWidth"
        :fullscreen="innerFullscreen"
        :draggable="draggable && !innerFullscreen"
        :align-center="alignCenter"
        :top="alignCenter ? undefined : top"
        :close-on-click-modal="closeOnClickModal && !confirmLoading"
        :close-on-press-escape="closeOnPressEscape && !confirmLoading"
        :show-close="showClose && !confirmLoading"
        :before-close="beforeClose"
        :destroy-on-close="destroyOnClose"
        :append-to-body="appendToBody"
        @open="emit('open')"
        @close="emit('close')"
        @closed="emit('closed')"
    >
        <template #header="scope">
            <div class="hsx-dialog__header">
                <slot name="header" v-bind="scope" :close="close" :title="title" :subtitle="subtitle" :fullscreen="innerFullscreen">
                    <div class="hsx-dialog__heading">
                        <div :id="scope.titleId" class="hsx-dialog__title">{{ title }}</div>
                        <div v-if="subtitle" class="hsx-dialog__subtitle">{{ subtitle }}</div>
                    </div>
                </slot>
                <el-button
                    v-if="showFullscreen"
                    class="hsx-dialog__fullscreen"
                    text
                    circle
                    :aria-label="innerFullscreen ? '退出全屏' : '全屏显示'"
                    :title="innerFullscreen ? '退出全屏' : '全屏显示'"
                    @click.stop="toggleFullscreen"
                >
                    <el-icon><ScaleToOriginal v-if="innerFullscreen" /><FullScreen v-else /></el-icon>
                </el-button>
            </div>
        </template>

        <div v-loading="bodyLoading" class="hsx-dialog__body hsx-scrollbar" :style="bodyStyle">
            <slot />
        </div>

        <template v-if="showFooter || $slots.footer" #footer>
            <div class="hsx-dialog__footer" :class="`hsx-dialog__footer--${footerAlign}`">
                <slot name="footer" :close="close" :confirm="confirm">
                    <el-button :disabled="confirmLoading" @click="cancel">{{ cancelText }}</el-button>
                    <el-button
                        type="primary"
                        :loading="confirmLoading"
                        :disabled="confirmDisabled"
                        @click="confirm"
                    >
                        {{ confirmText }}
                    </el-button>
                </slot>
            </div>
        </template>
    </el-dialog>
</template>

<style scoped>
.hsx-dialog__header {
    display: flex;
    min-width: 0;
    align-items: flex-start;
    gap: var(--hsx-space-3);
    padding-right: 34px;
}

.hsx-dialog__heading { min-width: 0; flex: 1; }
.hsx-dialog__title {
    overflow: hidden;
    color: var(--hsx-text-primary);
    font-size: var(--hsx-font-size-subtitle);
    font-weight: 600;
    line-height: 24px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.hsx-dialog__subtitle {
    margin-top: 3px;
    color: var(--hsx-text-secondary);
    font-size: var(--hsx-font-size-caption);
    line-height: var(--hsx-line-height-caption);
}
.hsx-dialog__fullscreen { flex: none; margin: -4px 0 0 auto; color: var(--hsx-text-secondary); }
.hsx-dialog__body { box-sizing: border-box; min-height: 0; flex: 1 1 auto; overflow: auto; }
.hsx-dialog__footer { display: flex; width: 100%; min-width: 0; align-items: center; gap: var(--hsx-space-2); }
.hsx-dialog__footer--left { justify-content: flex-start; }
.hsx-dialog__footer--center { justify-content: center; }
.hsx-dialog__footer--right { justify-content: flex-end; }
.hsx-dialog__footer--between { justify-content: space-between; }
:deep(.el-button + .el-button) { margin-left: 0; }
</style>

<style lang="scss">
.hsx-dialog {
    display: flex;
    flex-direction: column;
    max-width: calc(100vw - 32px);
    max-height: calc(100vh - var(--hsx-dialog-top, 6vh) - 16px);
    max-height: calc(100dvh - var(--hsx-dialog-top, 6vh) - 16px);
    overflow: hidden;
    border-radius: var(--hsx-radius-lg, 14px);
    box-shadow: var(--hsx-shadow-floating);

    .el-dialog__header {
        flex: none;
        margin: 0;
        padding: 18px 22px;
        border-bottom: 1px solid var(--hsx-border-color);
    }
    .el-dialog__headerbtn { top: 16px; right: 16px; }
    .el-dialog__body { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; overflow: hidden; padding: 20px 22px; }
    .el-dialog__footer {
        flex: none;
        padding: 14px 22px;
        border-top: 1px solid var(--hsx-border-color);
        background: var(--hsx-bg-surface);
    }
}

.hsx-dialog.hsx-dialog--fullscreen {
    max-width: 100vw;
    max-height: 100vh;
    max-height: 100dvh;
    border-radius: 0;
}

@media (max-width: 1366px) {
    .hsx-dialog {
        .el-dialog__header { padding: 15px 18px; }
        .el-dialog__body { padding: 16px 18px; }
        .el-dialog__footer { padding: 12px 18px; }
    }
}

@media (max-width: 640px) {
    .hsx-dialog:not(.is-fullscreen) { width: calc(100vw - 20px) !important; max-width: calc(100vw - 20px); }
    .hsx-dialog {
        .el-dialog__header { padding: 14px 16px; }
        .el-dialog__body { padding: 14px 16px; }
        .el-dialog__footer { padding: 12px 16px; }
    }
}
</style>
