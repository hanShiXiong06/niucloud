<template>
  <el-dialog
    :model-value="visible"
    :width="resolvedWidth"
    :close-on-click-modal="false"
    :destroy-on-close="destroyOnClose"
    :top="top"
    align-center
    class="rc-form-dialog"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    @closed="emit('closed')"
  >
    <template #header>
      <div class="rc-form-dialog__header">
        <div class="rc-form-dialog__title">{{ title }}</div>
        <div v-if="subtitle" class="rc-form-dialog__subtitle">{{ subtitle }}</div>
      </div>
    </template>

    <div class="rc-form-dialog__body" v-loading="bodyLoading">
      <slot />
    </div>

    <template #footer>
      <div class="rc-form-dialog__footer">
        <!-- 默认：取消 + 主操作（带 loading）。需要更多按钮时用 #footer 覆盖 -->
        <slot name="footer">
          <el-button @click="onCancel">{{ cancelText }}</el-button>
          <el-button
            type="primary"
            :loading="loading"
            :disabled="confirmDisabled"
            @click="emit('confirm')"
          >{{ confirmText }}</el-button>
        </slot>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'

/**
 * 回收插件统一业务弹窗外壳。
 *
 * 统一所有业务弹窗（签收/代下单/质检/定价/打款…）的：
 *  - 头部：标题 + 可选副标题（专业、无渐变花哨）
 *  - 底部：右对齐「取消 + 主操作」，主操作自带 loading 防重复提交
 *  - 宽度档位：sm/md/lg/xl，避免每个弹窗各写一个像素值
 *  - 内边距与滚动：统一 body 内边距，过高时内部滚动
 *
 * 复杂底部（如「暂存草稿 / 退回」）用具名插槽 #footer 覆盖默认按钮。
 */
const props = withDefaults(defineProps<{
  visible: boolean
  title: string
  subtitle?: string
  /** 宽度档位 sm=480 / md=720 / lg=1040 / xl=1160，或直接传像素/百分比 */
  width?: 'sm' | 'md' | 'lg' | 'xl' | string
  /** 主操作进行中（绑定到主按钮 loading + 禁用） */
  loading?: boolean
  /** 主操作是否禁用（如表单未通过校验） */
  confirmDisabled?: boolean
  /** body 区加载态（拉取详情时） */
  bodyLoading?: boolean
  confirmText?: string
  cancelText?: string
  destroyOnClose?: boolean
  top?: string
}>(), {
  subtitle: '',
  width: 'md',
  loading: false,
  confirmDisabled: false,
  bodyLoading: false,
  confirmText: '确定',
  cancelText: '取消',
  destroyOnClose: true,
  top: '6vh'
})

const emit = defineEmits<{
  'update:visible': [value: boolean]
  confirm: []
  cancel: []
  closed: []
}>()

const WIDTHS: Record<string, string> = { sm: '480px', md: '720px', lg: '1040px', xl: '1160px' }
const resolvedWidth = computed(() => WIDTHS[props.width] || props.width)

const onCancel = () => {
  emit('cancel')
  emit('update:visible', false)
}
</script>

<style lang="scss">
/* 非 scoped：统一覆盖 el-dialog 内部结构。用 .rc-form-dialog 作用域，避免影响其它弹窗 */
.rc-form-dialog {
  border-radius: 14px !important;
  overflow: hidden;

  .el-dialog__header {
    margin: 0;
    padding: 18px 22px;
    border-bottom: 1px solid var(--el-border-color-lighter);
  }
  .el-dialog__headerbtn { top: 16px; right: 18px; }
  .el-dialog__body { padding: 0; }
  .el-dialog__footer { padding: 0; }

  &__title {
    font-size: 16px;
    font-weight: 500;
    color: var(--el-text-color-primary);
    line-height: 1.4;
  }
  &__subtitle {
    margin-top: 3px;
    font-size: 12px;
    color: var(--el-text-color-secondary);
  }
  &__body {
    padding: 20px 22px;
    max-height: 70vh;
    overflow-y: auto;
  }
  &__footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 14px 22px;
    border-top: 1px solid var(--el-border-color-lighter);
    background: var(--el-fill-color-blank);
  }
}
</style>
