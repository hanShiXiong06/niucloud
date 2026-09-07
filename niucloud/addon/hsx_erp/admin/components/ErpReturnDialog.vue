<template>
    <HsxDialog :confirm-loading="loading"
        :model-value="modelValue"
        class="erp-return-dialog"
        width="92vw"
        top="4vh"
        destroy-on-close
        :close-on-click-modal="false"
        :close-on-press-escape="false"
        @update:model-value="visible => !visible && $emit('close')"
    >
        <template #header>
            <div class="return-dialog-header">
                <div class="return-dialog-title">{{ title }}</div>
                <div v-if="subtitle" class="return-dialog-subtitle">{{ subtitle }}</div>
            </div>
        </template>

        <div class="return-dialog-content">
            <slot />
        </div>

        <template #footer>
            <div class="return-dialog-footer">
                <span v-if="tip" class="return-dialog-tip">{{ tip }}</span>
                <div class="return-dialog-actions">
                    <el-button :disabled="loading" @click="$emit('close')">{{ cancelText }}</el-button>
                    <el-button type="primary" :loading="loading" :disabled="(disabled) || (loading)" @click="$emit('confirm')">{{ confirmText }}</el-button>
                </div>
            </div>
        </template>
    </HsxDialog>
</template>

<script setup lang="ts">
import { HsxDialog } from '@/addon/hsx_components/core'
withDefaults(defineProps<{
    modelValue: boolean
    title: string
    subtitle?: string
    confirmText?: string
    cancelText?: string
    tip?: string
    loading?: boolean
    disabled?: boolean
}>(), {
    subtitle: '',
    confirmText: '确认',
    cancelText: '取消',
    tip: '',
    loading: false,
    disabled: false,
})

defineEmits<{
    (event: 'close'): void
    (event: 'confirm'): void
}>()
</script>

<style scoped>
:global(.erp-return-dialog) { max-width: 1440px; margin-bottom: 4vh; }
:global(.erp-return-dialog .el-dialog__header) { margin-right: 0; padding: 18px 24px 14px; border-bottom: 1px solid #ebeef5; }
:global(.erp-return-dialog .el-dialog__body) { max-height: calc(92vh - 154px); overflow: auto; padding: 18px 24px; }
:global(.erp-return-dialog .el-dialog__footer) { padding: 14px 24px; border-top: 1px solid #ebeef5; }
.return-dialog-title { color: #111827; font-size: 18px; font-weight: 600; }
.return-dialog-subtitle { margin-top: 4px; color: #909399; font-size: 13px; }
.return-dialog-footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.return-dialog-tip { color: #909399; font-size: 12px; text-align: left; }
.return-dialog-actions { display: flex; flex: none; gap: 8px; }
@media (max-width: 768px) {
    :global(.erp-return-dialog) { width: calc(100vw - 16px) !important; }
    :global(.erp-return-dialog .el-dialog__header), :global(.erp-return-dialog .el-dialog__body), :global(.erp-return-dialog .el-dialog__footer) { padding-left: 14px; padding-right: 14px; }
    .return-dialog-footer { align-items: stretch; flex-direction: column; }
    .return-dialog-actions { justify-content: flex-end; }
}
</style>
