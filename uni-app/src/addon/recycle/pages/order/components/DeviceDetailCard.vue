<template>
  <view class="bg-white rounded-lg shadow-sm overflow-hidden mb-2">
    <!-- 设备头部 -->
    <view class="p-3 flex items-start relative">
      <!-- 选择框 -->
      <view class="mr-2 mt-0.5" @tap.stop="$emit('toggle-select')">
        <up-checkbox :checked="isSelected" shape="square"></up-checkbox>
      </view>

      <!-- 设备信息 -->
      <view class="flex-1 pr-16">
        <!-- 设备名称 -->
        <view class="mb-1">
          <text class="text-sm font-medium text-gray-800">
            <text class="text-blue-500">{{ index + 1 }}.</text>
            {{ device.model || '待识别' }}
          </text>
        </view>

        <!-- IMEI -->
        <view class="flex items-center mb-1.5" @longpress="handleCopyIMEI">
          <text class="text-xs text-gray-500">IMEI: {{ device.imei }}</text>
          <view
            class="ml-1 w-4 h-4 flex items-center justify-center rounded bg-gray-100"
            @tap.stop="handleCopyIMEI"
          >
            <up-icon name="file-text" size="10" color="#94a3b8"></up-icon>
          </view>
        </view>

        <!-- 价格信息 -->
        <view class="flex items-center gap-3 text-xs">
          <view v-if="device.initial_price && device.initial_price !== '0.00'" class="flex items-center gap-0.5">
            <text class="text-gray-400">预估</text>
            <text class="text-orange-500 font-medium">¥{{ device.initial_price }}</text>
          </view>
          <view v-if="device.final_price && device.final_price !== '0.00'" class="flex items-center gap-0.5">
            <text class="text-gray-400">最终</text>
            <text class="font-bold" style="color: #ff6b00;">¥{{ device.final_price }}</text>
          </view>
          <text v-if="!device.final_price || device.final_price === '0.00'" class="text-gray-400">
            待定价
          </text>
        </view>
      </view>

      <!-- 状态标签 -->
      <view
        class="absolute top-0 right-0 text-xs py-1 px-2 rounded-bl-lg"
        :style="{ color: statusColor, background: statusBg }"
      >
        {{ device.status_name }}
      </view>
    </view>

   

    <!-- 检测结果 / 价格说明 -->
    <view v-if="checkResult || device.remark || device.price_remark || device.check_at" class="px-3 pb-2 space-y-1">
      <view v-if="checkResult" class="flex text-xs">
        <text class="text-gray-400 w-14 flex-shrink-0">检测结果</text>
        <text class="text-gray-600 flex-1">{{ checkResult }}</text>
      </view>
      <view v-if="device.remark" class="flex text-xs">
        <text class="text-gray-400 w-14 flex-shrink-0">扣费说明</text>
        <text class="text-gray-600 flex-1">{{ device.remark }}</text>
      </view>
      <view v-if="device.price_remark" class="flex text-xs">
        <text class="text-gray-400 w-14 flex-shrink-0">价格说明</text>
        <text class="text-gray-600 flex-1">{{ device.price_remark }}</text>
      </view>
      <view v-if="device.check_at" class="flex text-xs">
        <text class="text-gray-400 w-14 flex-shrink-0">检测时间</text>
        <text class="text-gray-500">{{ formatTime(device.check_at) }}</text>
      </view>
    </view>
     <!-- 检测图片 -->
    <view v-if="hasCheckImages" class="px-3 pb-2">
      <scroll-view scroll-x class="whitespace-nowrap">
        <view class="inline-flex gap-1.5 py-0.5">
          <view
            v-for="(img_url, imgIndex) in imageThumbList"
            :key="imgIndex"
            class="w-14 h-14 rounded-lg overflow-hidden bg-gray-100"
          >
            <image
              :src="img(img_url)"
              mode="aspectFill"
              class="w-full h-full"
              @tap.stop="handlePreviewImage(imgIndex)"
            />
          </view>
        </view>
      </scroll-view>
    </view>

    <!-- 操作按钮 -->
    <view v-if="showActions" class="flex gap-2 px-3 py-2 border-t border-gray-50">
      <!-- #ifdef MP-WEIXIN -->
      <button
        v-if="useWechatContact"
        class="flex-1 h-8 rounded-full flex items-center justify-center text-white text-xs"
        style="background: linear-gradient(135deg, #14b8a6, #0d9488);"
        open-type="contact"
      >
        <up-icon name="chat-fill" size="13" color="#fff" class="mr-1"></up-icon>
        议价
      </button>
      <button
        v-else
        class="flex-1 h-8 rounded-full flex items-center justify-center text-white text-xs"
        style="background: linear-gradient(135deg, #14b8a6, #0d9488);"
        @tap.stop="$emit('negotiate')"
      >
        <up-icon name="chat-fill" size="13" color="#fff" class="mr-1"></up-icon>
        议价
      </button>
      <!-- #endif -->
      <!-- #ifndef MP-WEIXIN -->
      <button
        class="flex-1 h-8 rounded-full flex items-center justify-center text-white text-xs"
        style="background: linear-gradient(135deg, #14b8a6, #0d9488);"
        @tap.stop="$emit('negotiate')"
      >
        <up-icon name="chat-fill" size="13" color="#fff" class="mr-1"></up-icon>
        议价
      </button>
      <!-- #endif -->
      <button
        v-if="allowRejectSale"
        class="flex-1 h-8 rounded-full flex items-center justify-center text-white text-xs"
        style="background: linear-gradient(135deg, #f97316, #ef4444);"
        @tap.stop="$emit('reject-sale')"
      >
        <up-icon name="close" size="13" color="#fff" class="mr-1"></up-icon>
        拒绝出售
      </button>
      <button
        class="flex-1 h-8 rounded-full flex items-center justify-center text-white text-xs"
        style="background: linear-gradient(135deg, #f472b6, #ec4899);"
        @tap.stop="$emit('confirm')"
      >
        <up-icon name="checkmark" size="13" color="#fff" class="mr-1"></up-icon>
        确认价格
      </button>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { OrderDetailDevice } from '../../../types/order'
import { timeStampTurnTime, img } from '@/utils/common'
import { getDeviceStatusInfo } from '../../../utils/theme'

interface Props {
  device: OrderDetailDevice
  index: number
  isSelected: boolean
  allowRejectSale?: boolean
  useWechatContact?: boolean
}

const props = defineProps<Props>()

defineEmits<{
  'toggle-select': []
  'confirm': []
  'negotiate': []
  'reject-sale': []
}>()

const statusColor = computed(() => getDeviceStatusInfo(props.device.status).color)
const statusBg = computed(() => getDeviceStatusInfo(props.device.status).bgColor)

// 质检结果：优先取 check_result_seller，fallback 到 check_result
const checkResult = computed(() => {
  return props.device.check_result_seller || props.device.check_result || ''
})

// 原图列表（用于预览大图）：优先取 check_images_seller，fallback 到 check_images
const imageList = computed(() => {
  const raw = props.device.check_images_seller || props.device.check_images
  if (!raw) return []
  return raw.split(',').map(s => s.trim()).filter(s => s)
})

// 缩略图列表（用于列表展示，节省CDN）：优先取后端返回的缩略图，fallback 到原图
const imageThumbList = computed(() => {
  const thumbs = props.device.check_images_seller_thumb_small
  if (thumbs && Array.isArray(thumbs) && thumbs.length > 0) {
    return thumbs
  }
  return imageList.value
})

// 是否有质检图片
const hasCheckImages = computed(() => imageList.value.length > 0)

const showActions = computed(() => [3, 4, 7, 8].includes(Number(props.device.status)))

const handleCopyIMEI = () => {
  uni.setClipboardData({
    data: props.device.imei,
    success: () => {
      uni.showToast({ title: '已复制IMEI', icon: 'success' })
    }
  })
}

const handlePreviewImage = (current: number) => {
  const formattedImages = imageList.value.map(item => img(item))
  uni.previewImage({
    urls: formattedImages,
    current: formattedImages[current]
  })
}

const formatTime = (timestamp: number) => timeStampTurnTime(timestamp)
</script>
