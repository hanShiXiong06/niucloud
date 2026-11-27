<template>
  <el-tooltip
    :content="nextStep"
    :disabled="!nextStep || !showHint"
    placement="top"
    effect="dark"
  >
    <span class="device-status-badge" :class="`is-${tagType}`">
      <span class="device-status-badge__dot"></span>
      <span class="device-status-badge__text">{{ statusText }}</span>
    </span>
  </el-tooltip>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRecycleOrderUi } from '@/addon/hsx_recycle/hooks/useRecycleOrderUi'

const props = withDefaults(defineProps<{
  status: number | string
  /** 后端返回的状态名，优先使用；缺失时用内置文案兜底 */
  statusName?: string
  /** 是否显示「下一步」悬停提示（默认显示） */
  showHint?: boolean
}>(), { statusName: '', showHint: true })

const { getDeviceStatusType, getDeviceStatusText, getDeviceNextStep } = useRecycleOrderUi()

const statusNum = computed(() => Number(props.status))
const tagType = computed(() => getDeviceStatusType(statusNum.value))
const statusText = computed(() => props.statusName || getDeviceStatusText(statusNum.value) || '-')
const nextStep = computed(() => getDeviceNextStep(statusNum.value))
</script>

<style lang="scss" scoped>
/* 柔和状态药丸：浅底 + 同色字 + 状态色圆点，B 端专业现代风。
   使用 Element Plus 语义色变量，浅色/深色主题均自动适配。 */
.device-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 2px 10px 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  line-height: 20px;
  cursor: default;
  white-space: nowrap;

  &__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    flex-shrink: 0;
  }

  &.is-primary { color: var(--el-color-primary); background: var(--el-color-primary-light-9); }
  &.is-success { color: var(--el-color-success); background: var(--el-color-success-light-9); }
  &.is-warning { color: var(--el-color-warning); background: var(--el-color-warning-light-9); }
  &.is-danger  { color: var(--el-color-danger);  background: var(--el-color-danger-light-9); }
  &.is-info    { color: var(--el-color-info);     background: var(--el-color-info-light-9); }
}
</style>
