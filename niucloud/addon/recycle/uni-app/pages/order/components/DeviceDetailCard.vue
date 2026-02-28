<template>
  <view class="bg-white rounded-lg shadow-sm overflow-hidden mb-2 ">
    <!-- 设备基本信息 -->
    <view class="p-2 flex items-start border-b border-gray-100 relative">
      <!-- 选择框 -->
      <view class="mr-2 mt-1" @tap.stop="$emit('toggle-select')">
        <up-checkbox :checked="isSelected" shape="square"></up-checkbox>
      </view>

      <!-- 设备信息 -->
      <view class="flex-1">
        <!-- 状态标签 -->
        <view
          :class="['absolute top-0 right-0 text-xs py-0.5 px-1.5 rounded-bl-lg rounded-tr-lg', `status-text-${device.status}`]"
        >
          {{ device.status_name }}
        </view>

        <!-- 设备名称和序号 -->
        <view class="mt-2 mb-1">
          <text class="text-sm font-medium text-gray-700">
            <text class="text-sm text-blue-500">{{ index + 1 }}. </text>
            {{ device.model || '待识别' }}
          </text>
        </view>

        <!-- IMEI -->
        <view class="flex items-center mb-1" @longpress="handleCopyIMEI">
          <text class="text-xs text-gray-600">IMEI: {{ device.imei }}</text>
          <up-icon
            name="cut"
            size="12"
            color="#000"
            @tap="handleCopyIMEI"
            class="ml-1 opacity-50"
          ></up-icon>
        </view>

        <!-- 价格信息 -->
        <view class="flex items-center gap-2 text-xs">
          <view v-if="device.initial_price && device.initial_price !== '0.00'">
            <text class="text-gray-500">预估:</text>
            <text class="text-orange-500 font-medium">¥{{ device.initial_price }}</text>
          </view>
          <view v-if="device.final_price && device.final_price !== '0.00'">
            <text class="text-gray-500">最终:</text>
            <text class="text-primary font-bold">¥{{ device.final_price }}</text>
          </view>
          <text v-if="!device.final_price || device.final_price === '0.00'" class="text-gray-400">
            待定价
          </text>
        </view>
      </view>
    </view>
    <up-line ></up-line>
    <!-- 设备详细信息（直接显示，不折叠） -->
    <view class="p-2 bg-[#fff] ">
      <!-- 检测图片 -->
      <view v-if="device.check_images" class="mb-2">
        <text class="text-xs font-medium text-gray-700 mb-1 block">检测图片</text>
        <scroll-view scroll-x class="whitespace-nowrap">
          <view class="inline-flex gap-1.5 py-1">
            <view
              v-for="(img_url, imgIndex) in imageList"
              :key="imgIndex"
              class="w-15 h-15 rounded overflow-hidden shadow-sm"
            >
              <image
                :src="img(img_url)"
                mode="aspectFill"
                class="w-full h-full object-cover"
                @tap.stop="handlePreviewImage(imgIndex)"
              />
            </view>
          </view>
        </scroll-view>
      </view>

      <!-- 检测结果 -->
      <view v-if="device.check_result" class="mb-2">
        <view class="flex text-xs">
          <text class="text-gray-500 w-16">检测结果:</text>
          <text class="text-gray-700 flex-1">{{ device.check_result }}</text>
        </view>
      </view>

      <!-- 价格说明 -->
      <view v-if="device.remark" class="mb-2">
        <view class="flex text-xs">
          <text class="text-gray-500 w-16">价格说明:</text>
          <text class="text-gray-700 flex-1">{{ device.remark }}</text>
        </view>
      </view>

      <!-- 检测时间 -->
      <view v-if="device.check_at" class="text-xs text-gray-500">
        检测时间: {{ formatTime(device.check_at) }}
      </view>
    </view>

    <!-- 操作按钮 -->
    <view v-if="showActions" class="p-2 flex gap-2 border-t border-gray-100">
      <button
        class="flex-1 h-8 rounded bg-gradient-to-r from-teal-500 to-green-600 flex items-center justify-center text-white text-xs"
        @tap.stop="$emit('negotiate')"
      >
        <up-icon name="chat-fill" size="14" color="#fff" class="mr-1"></up-icon>
        议价
      </button>
      <button
        class="flex-1 h-8 rounded bg-gradient-to-r from-pink-500 to-pink-600 flex items-center justify-center text-white text-xs"
        @tap.stop="$emit('confirm')"
      >
        <up-icon name="checkmark" size="14" color="#fff" class="mr-1"></up-icon>
        确认价格
      </button>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { OrderDetailDevice } from '../../../types/order'
import { timeStampTurnTime, img } from '@/utils/common'

interface Props {
  device: OrderDetailDevice
  index: number
  isSelected: boolean
}

const props = defineProps<Props>()

defineEmits<{
  'toggle-select': []
  'confirm': []
  'negotiate': []
}>()

// 图片列表
const imageList = computed(() => {
  if (!props.device.check_images) return []
  return props.device.check_images.split(',')
})

// 是否显示操作按钮（只有待确认状态显示）
const showActions = computed(() => {
  return props.device.status === 4
})

// 复制IMEI
const handleCopyIMEI = () => {
  uni.setClipboardData({
    data: props.device.imei,
    success: () => {
      uni.showToast({
        title: '已复制IMEI',
        icon: 'success'
      })
    }
  })
}

// 预览图片
const handlePreviewImage = (current: number) => {
  const formattedImages = imageList.value.map(item => img(item))
  uni.previewImage({
    urls: formattedImages,
    current: formattedImages[current]
  })
}

// 格式化时间
const formatTime = (timestamp: number) => {
  return timeStampTurnTime(timestamp)
}
</script>

<style scoped lang="scss">
/* 设备状态文本颜色 - 统一使用与 useOrderStatus 一致的配色 */
/* 设备状态: 1-待质检, 2-质检中, 3-已质检, 4-待确认, 5-已回收, 6-已退回, 7-已定价 */

.status-text-1 {
  background: rgba(245, 158, 11, 0.1);  /* 橙色 - 待质检 */
  color: #f59e0b;
}

.status-text-2 {
  background: rgba(59, 130, 246, 0.1);  /* 蓝色 - 质检中 */
  color: #3b82f6;
}

.status-text-3 {
  background: rgba(99, 102, 241, 0.1);  /* 靛蓝 - 已质检 */
  color: #6366f1;
}

.status-text-4 {
  background: rgba(139, 92, 246, 0.1);  /* 紫色 - 待确认 */
  color: #8b5cf6;
}

.status-text-5 {
  background: rgba(16, 185, 129, 0.1);  /* 绿色 - 已回收 */
  color: #10b981;
}

.status-text-6 {
  background: rgba(239, 68, 68, 0.1);  /* 红色 - 已退回 */
  color: #ef4444;
}

.status-text-7 {
  background: rgba(6, 182, 212, 0.1);  /* 青色 - 已定价 */
  color: #06b6d4;
}
</style>
