<script lang="ts">export default { name: 'HsxDrawer', inheritAttrs: false }</script>
<script setup lang="ts">
import { computed } from 'vue'

type DrawerDirection = 'rtl' | 'ltr' | 'ttb' | 'btt'
const props = withDefaults(defineProps<{
    modelValue: boolean
    title?: string
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
}>(), {
    title: '', size: '420px', direction: 'rtl', closeOnClickModal: false, closeOnPressEscape: true,
    destroyOnClose: true, appendToBody: true, lockScroll: true, showClose: true, withHeader: true,
    showFooter: false, confirmText: '确定', cancelText: '取消', confirmLoading: false
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
function close() { visible.value = false }
function cancel() { emit('cancel'); close() }
function confirm() { emit('confirm') }
defineExpose({ close, confirm })
</script>

<template>
    <el-drawer
        v-model="visible"
        v-bind="$attrs"
        class="hsx-drawer"
        :title="title"
        :size="size"
        :direction="direction"
        :before-close="beforeClose"
        :close-on-click-modal="closeOnClickModal"
        :close-on-press-escape="closeOnPressEscape"
        :destroy-on-close="destroyOnClose"
        :append-to-body="appendToBody"
        :lock-scroll="lockScroll"
        :show-close="showClose"
        :with-header="withHeader"
        @open="emit('open')"
        @opened="emit('opened')"
        @close="emit('close')"
        @closed="emit('closed')"
        @open-auto-focus="emit('open-auto-focus', $event)"
        @close-auto-focus="emit('close-auto-focus', $event)"
    >
        <template v-if="$slots.header" #header="scope"><slot name="header" v-bind="scope" :close="close" /></template>
        <div class="hsx-drawer__body"><slot :close="close" :confirm="confirm" /></div>
        <template v-if="showFooter || $slots.footer" #footer>
            <slot name="footer" :close="close" :confirm="confirm">
                <el-button @click="cancel">{{ cancelText }}</el-button>
                <el-button type="primary" :loading="confirmLoading" @click="confirm">{{ confirmText }}</el-button>
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
</style>
