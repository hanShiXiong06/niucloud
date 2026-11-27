<template>
  <HsxDialog
    :model-value="visible"
    :title="title"
    :subtitle="subtitle"
    :size="size"
    :width="customWidth"
    :destroy-on-close="destroyOnClose"
    :top="top"
    :body-loading="bodyLoading"
    :show-close="!loading"
    :close-on-press-escape="!loading"
    :confirm-loading="loading"
    :confirm-disabled="confirmDisabled"
    :confirm-text="confirmText"
    :cancel-text="cancelText"
    show-footer
    @update:model-value="value => emit('update:visible', value)"
    @confirm="emit('confirm')"
    @cancel="emit('cancel')"
    @closed="emit('closed')"
  >
    <slot />
    <template #footer>
      <slot name="footer">
        <el-button :disabled="loading" @click="onCancel">{{ cancelText }}</el-button>
        <el-button type="primary" :loading="loading" :disabled="(confirmDisabled) || (loading)" @click="emit('confirm')">{{ confirmText }}</el-button>
      </slot>
    </template>
  </HsxDialog>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { HsxDialog } from '@/addon/hsx_components/core'

// 业务接口留在回收插件，浮层布局、滚动、颜色统一交给公共组件。
const props = withDefaults(defineProps<{
  visible: boolean
  title: string
  subtitle?: string
  width?: 'sm' | 'md' | 'lg' | 'xl' | string
  loading?: boolean
  confirmDisabled?: boolean
  bodyLoading?: boolean
  confirmText?: string
  cancelText?: string
  destroyOnClose?: boolean
  top?: string
}>(), {
  subtitle: '', width: 'md', loading: false, confirmDisabled: false, bodyLoading: false,
  confirmText: '确定', cancelText: '取消', destroyOnClose: true, top: '6vh'
})
const emit = defineEmits<{
  (event: 'update:visible', value: boolean): void
  (event: 'confirm'): void
  (event: 'cancel'): void
  (event: 'closed'): void
}>()
const size = computed(() => ['sm', 'md', 'lg', 'xl'].includes(props.width) ? props.width as 'sm' | 'md' | 'lg' | 'xl' : 'md')
const customWidth = computed(() => ['sm', 'md', 'lg', 'xl'].includes(props.width) ? '' : props.width)
function onCancel() {
  if (props.loading) return
  emit('cancel')
  emit('update:visible', false)
}
</script>
