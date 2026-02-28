<template>
  <view class="bg-white rounded-lg shadow-sm mx-3 mb-3 overflow-hidden">
    <!-- 区块标题 -->
    <view class="flex items-center gap-1.5 px-4 pt-3 pb-2">
      <view class="w-1 h-4 rounded" :style="{ background: BRAND.primary }"></view>
      <text class="text-base font-bold text-gray-800">订单信息</text>
    </view>

    <view class="px-4 pb-3 space-y-2.5">
      <!-- 订单号 -->
      <view class="flex items-center justify-between">
        <text class="text-sm text-gray-400">订单号</text>
        <view class="flex items-center gap-1.5">
          <text class="text-sm text-gray-800 font-medium">{{ orderNo }}</text>
          <view
            class="w-5 h-5 flex items-center justify-center rounded bg-gray-100 active:bg-gray-200"
            @tap="handleCopyOrderNo"
          >
            <up-icon name="file-text" size="12" color="#94a3b8"></up-icon>
          </view>
        </view>
      </view>

      <!-- 设备数量 -->
      <view v-if="deviceCount > 0" class="flex items-center justify-between">
        <text class="text-sm text-gray-400">设备数量</text>
        <text class="text-sm text-gray-800">{{ deviceCount }} 台</text>
      </view>

      <!-- 快递单号 -->
      <view v-if="expressNo" class="flex items-center justify-between">
        <text class="text-sm text-gray-400">快递单号</text>
        <view class="flex items-center gap-1.5" @tap="handleShowExpressTracking">
          <text class="text-sm text-blue-500 font-medium">{{ expressNo }}</text>
          <up-icon name="arrow-right" size="12" color="#3b82f6"></up-icon>
        </view>
      </view>

      <!-- 总价值 -->
      <view class="flex items-center justify-between pt-1 border-t border-gray-50">
        <text class="text-sm text-gray-400">订单总价</text>
        <text class="text-lg font-bold" style="color: #ff6b00;">¥{{ totalPrice }}</text>
      </view>
    </view>

    <!-- 物流跟踪弹窗 -->
    <ExpressTrackingModal
      v-model:visible="showExpressModal"
      :expressNo="expressNo || ''"
      :mobile="mobile"
    />
  </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { BRAND } from '../../../utils/theme'
import { copyOrderNo } from '../../../utils/clipboard'
import ExpressTrackingModal from './ExpressTrackingModal.vue'

interface Props {
  orderNo: string
  deviceCount: number
  totalPrice: string
  expressNo?: string
  mobile?: string
}

const props = defineProps<Props>()

const showExpressModal = ref(false)

const handleCopyOrderNo = () => copyOrderNo(props.orderNo)

const handleShowExpressTracking = () => {
  showExpressModal.value = true
}
</script>
