<template>
  <el-tooltip
    :content="nextStep"
    :disabled="!nextStep || !showHint"
    placement="top"
    effect="dark"
  >
    <span class="device-status-badge">
      <el-tag :type="tagType" size="small">{{ statusText }}</el-tag>
      <el-icon v-if="nextStep && showHint" class="device-status-badge__hint"><InfoFilled /></el-icon>
    </span>
  </el-tooltip>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { InfoFilled } from '@element-plus/icons-vue'
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
const tagType = computed(() => getDeviceStatusType(statusNum.value) as any)
const statusText = computed(() => props.statusName || getDeviceStatusText(statusNum.value) || '-')
const nextStep = computed(() => getDeviceNextStep(statusNum.value))
</script>

<style lang="scss" scoped>
.device-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  cursor: default;

  &__hint {
    color: var(--el-color-info);
    font-size: 13px;
    opacity: 0.65;
  }
}
</style>
