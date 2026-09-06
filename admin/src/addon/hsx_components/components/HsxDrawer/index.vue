<script lang="ts">export default { name: 'HsxDrawer', inheritAttrs: false }</script>
<script setup lang="ts">
import { computed } from 'vue'

type DrawerDirection = 'rtl' | 'ltr' | 'ttb' | 'btt'
const props = withDefaults(defineProps<{
    modelValue: boolean
    title?: string
    subtitle?: string
    bodyLoading?: boolean
    size?: string | number
    direction?: DrawerDirection
    beforeClose?: (done: () => void) => void
    closeOnClickModal?: boolean
    closeOnPressEscape?: boolean
    destroyOnClose?: boolean
    appendToBody?: boolean
    lockScroll?: boolean
    showClose?: boolean
    withHeader?: boolean
    showFooter?: boolean
    confirmText?: string
    cancelText?: string
    confirmLoading?: boolean
    confirmDisabled?: boolean
}>(), {
    title: '', subtitle: '', bodyLoading: false, size: 'md', direction: 'rtl', closeOnClickModal: false, closeOnPressEscape: true,
    destroyOnClose: true, appendToBody: true, lockScroll: true, showClose: true, withHeader: true,
    showFooter: false, confirmText: '确定', cancelText: '取消', confirmLoading: false, confirmDisabled: false
})
const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void
    (event: 'open'): void
    (event: 'opened'): void
    (event: 'close'): void
    (event: 'closed'): void
    (event: 'open-auto-focus', value?: Event): void
    (event: 'close-auto-focus', value?: Event): void
    (event: 'confirm'): void
    (event: 'cancel'): void
}>()
const visible = computed({ get: () => props.modelValue, set: (value) => emit('update:modelValue', value) })
const sizes: Record<string, string> = { sm: '480px', md: '720px', lg: '1040px', xl: '1160px' }
const resolvedSize = computed(() => {
    const size = typeof props.size === 'number' ? `${props.size}px` : sizes[props.size] || props.size
    return ['rtl', 'ltr'].includes(props.direction) ? `min(${size}, 100vw)` : `min(${size}, 100vh)`
})
function requestClose(afterClose?: () => void) {
    if (props.confirmLoading) return
    const done = () => { afterClose?.(); visible.value = false }
    if (props.beforeClose) props.beforeClose(done)
    else done()
}
function close() { requestClose() }
function cancel() { requestClose(() => emit('cancel')) }
function confirm() { if (!props.confirmLoading && !props.confirmDisabled) emit('confirm') }
defineExpose({ close, confirm })
</script>

<template>
    <el-drawer
        v-model="visible"
        v-bind="$attrs"
        class="hsx-drawer"
        :title="title"
        :size="resolvedSize"
        :direction="direction"
        :before-close="beforeClose"
        :close-on-click-modal="closeOnClickModal && !confirmLoading"
        :close-on-press-escape="closeOnPressEscape && !confirmLoading"
        :destroy-on-close="destroyOnClose"
        :append-to-body="appendToBody"
        :lock-scroll="lockScroll"
        :show-close="showClose && !confirmLoading"
        :with-header="withHeader"
        @open="emit('open')"
        @opened="emit('opened')"
        @close="emit('close')"
        @closed="emit('closed')"
        @open-auto-focus="emit('open-auto-focus', $event)"
        @close-auto-focus="emit('close-auto-focus', $event)"
    >
        <template #header="scope"><slot name="header" v-bind="scope" :close="close"><div class="hsx-drawer__heading"><div :id="scope.titleId" class="hsx-drawer__title">{{ title }}</div><div v-if="subtitle" class="hsx-drawer__subtitle">{{ subtitle }}</div></div></slot></template>
        <div v-loading="bodyLoading" class="hsx-drawer__body"><slot :close="close" :confirm="confirm" /></div>
        <template v-if="showFooter || $slots.footer" #footer>
            <slot name="footer" :close="close" :confirm="confirm">
                <el-button :disabled="confirmLoading" @click="cancel">{{ cancelText }}</el-button>
                <el-button type="primary" :loading="confirmLoading" :disabled="confirmDisabled" @click="confirm">{{ confirmText }}</el-button>
            </slot>
        </template>
    </el-drawer>
</template>

<style>
.hsx-drawer.el-drawer { color: var(--hsx-text-primary); background: var(--hsx-bg-surface); }
.hsx-drawer .el-drawer__header { min-height: 64px; box-sizing: border-box; margin: 0; padding: 16px 20px; border-bottom: 1px solid var(--hsx-border-color); color: var(--hsx-text-primary); }
.hsx-drawer .el-drawer__body { min-height: 0; padding: 0; }
.hsx-drawer .el-drawer__footer { padding: 14px 20px; border-top: 1px solid var(--hsx-border-color); background: var(--hsx-bg-surface); }
.hsx-drawer__body { min-height: 100%; box-sizing: border-box; padding: 20px; }
.hsx-drawer__heading { min-width: 0; }
.hsx-drawer__title { font-size: 16px; font-weight: 600; line-height: 24px; color: var(--hsx-text-primary); }
.hsx-drawer__subtitle { margin-top: 3px; font-size: 12px; line-height: 18px; color: var(--hsx-text-secondary); }
@media (max-width: 1366px) { .hsx-drawer__body { padding: 16px; } }
@media (max-width: 640px) { .hsx-drawer.rtl, .hsx-drawer.ltr { width: 100vw !important; } .hsx-drawer .el-drawer__header, .hsx-drawer .el-drawer__footer { padding: 14px 16px; } }
</style>
