<template>
  <view class="mx-3 mt-2 mb-3 rounded-lg overflow-hidden shadow-sm">
    <!-- 渐变横幅 -->
    <view class="px-4 py-4 text-white" :style="{ background: gradient }">
      <view class="flex items-center justify-between mb-1">
        <text class="text-lg font-bold">{{ statusName }}</text>
        <text class="text-xs opacity-80">{{ createTime }}</text>
      </view>
      <text class="text-sm opacity-90">{{ statusDescription }}</text>
    </view>

    <!-- 步骤进度条 -->
    <view class="bg-white px-3 py-3">
      <view v-if="isCancelled" class="flex items-center justify-center py-1">
        <view class="flex items-center gap-1 text-sm text-gray-400">
          <up-icon name="close-circle" size="16" color="#9ca3af"></up-icon>
          <text>订单已取消</text>
        </view>
      </view>
      <view v-else class="flex items-center justify-between relative">
        <!-- 底线（灰） -->
        <view class="progress-track absolute top-3 h-0.5 bg-gray-200 z-0"></view>
        <!-- 进度线（彩色），用 scaleX 控制宽度 -->
        <view
          class="progress-track absolute top-3 h-0.5 z-1"
          :style="{ background: statusColor, transform: `scaleX(${progressRatio})`, transformOrigin: 'left center' }"
        ></view>

        <!-- 步骤节点 -->
        <view
          v-for="(step, index) in steps"
          :key="index"
          class="flex flex-col items-center z-10 flex-1"
        >
          <view
            class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold mb-1"
            :class="index < currentStep
              ? 'text-white shadow-sm'
              : index === currentStep
                ? 'text-white shadow-sm'
                : 'bg-gray-200 text-gray-400'"
            :style="index <= currentStep ? { background: statusColor } : {}"
          >
            <up-icon v-if="index < currentStep" name="checkmark" size="12" color="#fff"></up-icon>
            <text v-else-if="index === currentStep && isCompleted">✓</text>
            <text v-else>{{ index + 1 }}</text>
          </view>
          <text
            :class="index <= currentStep ? 'text-xs font-medium text-gray-700' : 'text-xs text-gray-400'"
            style="font-size: 20rpx;"
          >{{ step.name }}</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { getOrderStatusInfo, ORDER_STATUS } from '../../../utils/theme'

interface Props {
  status: number
  statusName: string
  createTime: string
}

const props = defineProps<Props>()

const steps = [
  { name: '已下单' },
  { name: '待质检' },
  { name: '待确认' },
  { name: '待打款' },
  { name: '已完成' }
]

const isCancelled = computed(() => props.status === 8 || props.status === 9)
const isCompleted = computed(() => props.status === 7)

const statusInfo = computed(() => getOrderStatusInfo(props.status))
const statusColor = computed(() => statusInfo.value.color)
const gradient = computed(() => {
  const info = ORDER_STATUS[props.status as keyof typeof ORDER_STATUS]
  return info?.gradient || 'linear-gradient(135deg, #90a4ae, #607d8b)'
})

const currentStep = computed(() => {
  switch (props.status) {
    case 1: return 0
    case 2:
    case 3: return 1
    case 4:
    case 5: return 2
    case 6: return 3
    case 7: return 4  // 最后一个索引
    default: return 0
  }
})

// 进度比例 0~1，用于 scaleX
const progressRatio = computed(() => {
  const total = steps.length - 1
  return Math.min(currentStep.value / total, 1)
})

const statusDescription = computed(() => {
  const map: Record<number, string> = {
    1: '您的订单已提交，等待商家确认',
    2: '您的订单已签收，等待商家质检',
    3: '商家正在质检您的设备，请耐心等待',
    4: '设备已完成质检，等待您确认价格',
    5: '您已确认部分设备价格，等待确认剩余设备',
    6: '价格已确认，等待商家打款',
    7: '交易已完成，感谢您的使用',
    9: '订单已取消'
  }
  return map[props.status] || '订单状态未知'
})
</script>

<style scoped>
/* 进度线：从第一个节点中心到最后一个节点中心 */
.progress-track {
  left: 10%;
  right: 10%;
}
</style>
